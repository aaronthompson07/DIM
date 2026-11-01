<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use Throwable;

final class MigrationRunner
{
    public static function runPending(PDO $pdo, string $migrationPath): array
    {
        $result = [
            'ok' => true,
            'limited_mode' => false,
            'error' => null,
            'applied' => [],
        ];

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS schema_migrations (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $appliedMap = array_fill_keys($applied, true);

            $files = glob($migrationPath . DIRECTORY_SEPARATOR . '*.sql') ?: [];
            sort($files, SORT_STRING);

            foreach ($files as $file) {
                $migrationName = basename($file);
                if (isset($appliedMap[$migrationName])) {
                    continue;
                }

                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new \RuntimeException('Cannot read migration: ' . $migrationName);
                }

                // MySQL commits DDL implicitly; wrapping in a transaction often breaks commit().
                self::execMigrationSql($pdo, $sql);
                $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
                $stmt->execute(['migration' => $migrationName]);

                $result['applied'][] = $migrationName;
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $result['ok'] = false;
            $result['limited_mode'] = true;
            $result['error'] = $exception->getMessage();
        }

        return $result;
    }

    /** PDO::exec() runs only one statement on MySQL; migration files may contain several. */
    private static function execMigrationSql(PDO $pdo, string $sql): void
    {
        $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = explode(';', $withoutComments);
        foreach ($parts as $part) {
            $statement = trim($part);
            if ($statement === '') {
                continue;
            }
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                if (!self::isIgnorableMigrationSqlError($e)) {
                    throw $e;
                }
            }
        }
    }

    /** Idempotent re-runs (e.g. column/table already exists from a partial deploy). */
    private static function isIgnorableMigrationSqlError(PDOException $e): bool
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if (in_array($code, [1050, 1060, 1061, 1062, 1091, 1826], true)) {
            return true;
        }

        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'duplicate column')
            || str_contains($msg, 'duplicate key name')
            || str_contains($msg, 'already exists');
    }
}
