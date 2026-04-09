<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
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

                $pdo->beginTransaction();
                $pdo->exec($sql);
                $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
                $stmt->execute(['migration' => $migrationName]);
                $pdo->commit();

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
}
