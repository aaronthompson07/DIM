<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/helpers.php';
require dirname(__DIR__) . '/app/Core/Env.php';
require dirname(__DIR__) . '/app/Core/Database.php';
require dirname(__DIR__) . '/app/Core/MigrationRunner.php';
require dirname(__DIR__) . '/app/Services/AppointmentBoardService.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\MigrationRunner;
use App\Services\AppointmentBoardService;

Env::load(dirname(__DIR__) . '/.env');

$pdo = Database::connection();
$migrationResult = MigrationRunner::runPending($pdo, dirname(__DIR__) . '/database/migrations');
if (!empty($migrationResult['applied'])) {
    AppointmentBoardService::clearSchemaReadyCache();
}

echo 'Migration ok: ' . ($migrationResult['ok'] ? 'yes' : 'no') . PHP_EOL;
if (!$migrationResult['ok']) {
    echo 'Migration error: ' . (string) $migrationResult['error'] . PHP_EOL;
}
echo 'Schema ready: ' . (AppointmentBoardService::schemaReady($pdo) ? 'yes' : 'no') . PHP_EOL;

$applied = $pdo->query('SELECT migration FROM schema_migrations ORDER BY id DESC LIMIT 8')->fetchAll(PDO::FETCH_COLUMN);
echo "Recent migrations:\n- " . implode("\n- ", $applied) . PHP_EOL;

try {
    $pdo->query('SELECT board_public_key FROM tenants LIMIT 1');
    echo "Column board_public_key: yes\n";
} catch (Throwable $e) {
    echo 'Column board_public_key: no (' . $e->getMessage() . ")\n";
}

try {
    $pdo->query('SELECT id FROM appointment_board_entries LIMIT 1');
    echo "Table appointment_board_entries: yes\n";
} catch (Throwable $e) {
    echo 'Table appointment_board_entries: no (' . $e->getMessage() . ")\n";
}

$tid = (int) $pdo->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetchColumn();
if ($tid > 0) {
    $svc = new AppointmentBoardService();
    $key = $svc->ensureTenantPublicKey($pdo, $tid);
    echo "Tenant {$tid} board key: {$key}\n";
    $entries = $svc->listActiveForDisplay($pdo, $tid);
    echo 'Active entries: ' . count($entries) . PHP_EOL;
}
