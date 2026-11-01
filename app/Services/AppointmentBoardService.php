<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantTime;
use DateTimeImmutable;
use PDO;

final class AppointmentBoardService
{
    private const SOON_HOURS = 2;

    /** @var list<string> */
    private const APPOINTMENT_TYPES = ['room', 'treatment', 'anesthesia'];

    /**
     * Create missing appointment-board schema (safe to run every request after migrations).
     */
    public static function ensureSchema(PDO $pdo): bool
    {
        try {
            $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($dbName === '') {
                return false;
            }

            $colStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = :db AND table_name = \'tenants\' AND column_name = \'board_public_key\''
            );
            $colStmt->execute(['db' => $dbName]);
            if ((int) $colStmt->fetchColumn() === 0) {
                $pdo->exec('ALTER TABLE tenants ADD COLUMN board_public_key VARCHAR(64) NULL');
            }

            $idxStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = :db AND table_name = \'tenants\' AND index_name = \'uq_tenants_board_public_key\''
            );
            $idxStmt->execute(['db' => $dbName]);
            if ((int) $idxStmt->fetchColumn() === 0) {
                try {
                    $pdo->exec('ALTER TABLE tenants ADD UNIQUE KEY uq_tenants_board_public_key (board_public_key)');
                } catch (\PDOException) {
                    // Another request may have added the index.
                }
            }

            $tblStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = :db AND table_name = \'appointment_board_entries\''
            );
            $tblStmt->execute(['db' => $dbName]);
            if ((int) $tblStmt->fetchColumn() === 0) {
                $tenantIdType = self::idColumnType($pdo, 'tenants');
                $userIdType = self::idColumnType($pdo, 'users');
                $pdo->exec(
                    'CREATE TABLE appointment_board_entries (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        tenant_id ' . $tenantIdType . ' NOT NULL,
                        patient_name VARCHAR(120) NOT NULL,
                        appointment_type ENUM(\'room\', \'treatment\', \'anesthesia\') NOT NULL DEFAULT \'room\',
                        assigned_user_id ' . $userIdType . ' NOT NULL,
                        appointment_at DATETIME NOT NULL,
                        notes TEXT NULL,
                        completed_at DATETIME NULL,
                        created_by_user_id ' . $userIdType . ' NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_abe_tenant_appt (tenant_id, appointment_at),
                        INDEX idx_abe_assigned_user (assigned_user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
            }

            self::ensureEntryColumns($pdo);
            self::ensureAppointmentTypeEnum($pdo);

            $pdo->exec(
                'UPDATE tenants
                 SET board_public_key = LOWER(REPLACE(UUID(), \'-\', \'\'))
                 WHERE board_public_key IS NULL OR board_public_key = \'\''
            );

            return self::schemaReady($pdo);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function schemaReady(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT board_public_key FROM tenants LIMIT 0');
            $pdo->query('SELECT id FROM appointment_board_entries LIMIT 0');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function clearSchemaReadyCache(): void
    {
        // No-op: schema checks are not cached across calls (avoids stale state under PHP-FPM).
    }

    /** Match tenants.id / users.id exactly (avoids MySQL errno 150 on mismatched FK types). */
    private static function idColumnType(PDO $pdo, string $table): string
    {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return 'BIGINT UNSIGNED';
        }

        $stmt = $pdo->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND COLUMN_NAME = \'id\''
        );
        $stmt->execute(['db' => $dbName, 'tbl' => $table]);
        $type = $stmt->fetchColumn();
        if (!is_string($type) || trim($type) === '') {
            return 'BIGINT UNSIGNED';
        }

        $type = trim($type);
        if (!preg_match('/^[a-z0-9 ()]+$/i', $type)) {
            return 'BIGINT UNSIGNED';
        }

        return $type;
    }

    /** @var list<string> */
    private const ADJECTIVES = [
        'Sunny', 'Calm', 'Brave', 'Gentle', 'Happy', 'Lucky', 'Misty', 'Swift',
        'Cosy', 'Bright', 'Quiet', 'Clever', 'Friendly', 'Golden', 'Silver',
    ];

    /** @var list<string> */
    private const NOUNS = [
        'Maple', 'River', 'Cloud', 'Pebble', 'Willow', 'Harbor', 'Breeze', 'Clover',
        'Summit', 'Meadow', 'Cedar', 'Aspen', 'Brook', 'Starling', 'Poppy',
    ];

    public static function generatePublicKey(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function randomPatientName(): string
    {
        $adj = self::ADJECTIVES[random_int(0, count(self::ADJECTIVES) - 1)];
        $noun = self::NOUNS[random_int(0, count(self::NOUNS) - 1)];

        return $adj . ' ' . $noun;
    }

    public static function resolvePatientName(?string $raw): string
    {
        $name = trim((string) $raw);
        if ($name === '') {
            return self::randomPatientName();
        }
        $len = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($len > 120) {
            throw new \InvalidArgumentException('Patient name must be 120 characters or fewer.');
        }

        return $name;
    }

    public static function normalizeAppointmentType(?string $raw): string
    {
        $type = strtolower(trim((string) $raw));
        if ($type === 'surgery') {
            return 'treatment';
        }
        if (in_array($type, self::APPOINTMENT_TYPES, true)) {
            return $type;
        }

        return 'room';
    }

    public static function appointmentTypeLabel(string $type): string
    {
        return match (self::normalizeAppointmentType($type)) {
            'treatment' => 'Treatment',
            'anesthesia' => 'Anesthesia',
            default => 'Room',
        };
    }

    private static function ensureAppointmentTypeEnum(PDO $pdo): void
    {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = \'appointment_board_entries\' AND COLUMN_NAME = \'appointment_type\''
        );
        $stmt->execute(['db' => $dbName]);
        $columnType = $stmt->fetchColumn();
        if (!is_string($columnType) || str_contains(strtolower($columnType), 'treatment')) {
            return;
        }

        try {
            $pdo->exec(
                "UPDATE appointment_board_entries SET appointment_type = 'treatment' WHERE appointment_type = 'surgery'"
            );
            $pdo->exec(
                "ALTER TABLE appointment_board_entries
                 MODIFY appointment_type ENUM('room','treatment','anesthesia') NOT NULL DEFAULT 'room'"
            );
        } catch (\PDOException) {
            // Concurrent deploy or partial schema.
        }
    }

    private static function ensureEntryColumns(PDO $pdo): void
    {
        self::addColumnIfMissing(
            $pdo,
            'appointment_board_entries',
            'appointment_type',
            "ENUM('room','treatment','anesthesia') NOT NULL DEFAULT 'room' AFTER patient_name"
        );
        self::addColumnIfMissing(
            $pdo,
            'appointment_board_entries',
            'completed_at',
            'DATETIME NULL AFTER notes'
        );
    }

    private static function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
    {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND COLUMN_NAME = :col'
        );
        $stmt->execute(['db' => $dbName, 'tbl' => $table, 'col' => $column]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        try {
            $pdo->exec('ALTER TABLE `' . str_replace('`', '', $table) . '` ADD COLUMN `' . str_replace('`', '', $column) . '` ' . $definition);
        } catch (\PDOException) {
            // Duplicate or concurrent add.
        }
    }

    public function ensureTenantPublicKey(PDO $pdo, int $tenantId): string
    {
        if (!self::schemaReady($pdo)) {
            throw new \RuntimeException('Appointment board schema is not ready.');
        }

        $stmt = $pdo->prepare('SELECT board_public_key FROM tenants WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $tenantId]);
        $row = $stmt->fetch();
        $key = $row && !empty($row['board_public_key']) ? (string) $row['board_public_key'] : '';
        if ($key !== '') {
            return $key;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = self::generatePublicKey();
            try {
                $upd = $pdo->prepare(
                    'UPDATE tenants SET board_public_key = :k WHERE id = :id AND (board_public_key IS NULL OR board_public_key = \'\')'
                );
                $upd->execute(['k' => $candidate, 'id' => $tenantId]);
                if ($upd->rowCount() > 0) {
                    return $candidate;
                }
                $stmt->execute(['id' => $tenantId]);
                $row = $stmt->fetch();
                if ($row && !empty($row['board_public_key'])) {
                    return (string) $row['board_public_key'];
                }
            } catch (\PDOException) {
                continue;
            }
        }

        throw new \RuntimeException('Could not assign board public key.');
    }

    public function regeneratePublicKey(PDO $pdo, int $tenantId): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = self::generatePublicKey();
            try {
                $upd = $pdo->prepare('UPDATE tenants SET board_public_key = :k WHERE id = :id');
                $upd->execute(['k' => $candidate, 'id' => $tenantId]);

                return $candidate;
            } catch (\PDOException) {
                continue;
            }
        }

        throw new \RuntimeException('Could not regenerate board public key.');
    }

    public function tenantIdForPublicKey(PDO $pdo, string $publicKey): ?int
    {
        $publicKey = strtolower(trim($publicKey));
        if ($publicKey === '' || strlen($publicKey) > 64) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id FROM tenants WHERE board_public_key = :k AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['k' => $publicKey]);
        $row = $stmt->fetch();

        return $row ? (int) $row['id'] : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveForDisplay(PDO $pdo, int $tenantId): array
    {
        $tz = TenantTime::timezoneObject($pdo, $tenantId);
        $now = new DateTimeImmutable('now', $tz);

        $stmt = $pdo->prepare(
            'SELECT e.id, e.patient_name, e.appointment_type, e.appointment_at,
                    e.assigned_user_id, u.display_name AS assigned_name
             FROM appointment_board_entries e
             INNER JOIN users u ON u.id = e.assigned_user_id
             WHERE e.tenant_id = :tid
               AND e.completed_at IS NULL
             ORDER BY e.appointment_at ASC'
        );
        $stmt->execute(['tid' => $tenantId]);
        $rows = $stmt->fetchAll();

        $soonUntil = $now->modify('+' . self::SOON_HOURS . ' hours');
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->mapRowForDisplay($pdo, $tenantId, $row, $now, $soonUntil, $tz);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapRowForDisplay(
        PDO $pdo,
        int $tenantId,
        array $row,
        DateTimeImmutable $now,
        DateTimeImmutable $soonUntil,
        \DateTimeZone $tz
    ): array {
        $apptMysql = (string) $row['appointment_at'];
        $appt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr($apptMysql, 0, 19), $tz);
        if ($appt === false) {
            $appt = $now;
        }

        if ($appt < $now) {
            $status = 'overdue';
        } elseif ($appt <= $soonUntil) {
            $status = 'soon';
        } else {
            $status = 'upcoming';
        }

        $type = self::normalizeAppointmentType($row['appointment_type'] ?? 'room');
        $atom = TenantTime::mysqlNaiveDateTimeToAtom($pdo, $tenantId, $apptMysql);

        return [
            'id' => (int) $row['id'],
            'patient_name' => (string) $row['patient_name'],
            'appointment_type' => $type,
            'appointment_type_label' => self::appointmentTypeLabel($type),
            'assigned_user_id' => (int) $row['assigned_user_id'],
            'assigned_name' => (string) $row['assigned_name'],
            'appointment_at' => $atom ?? $appt->format(DATE_ATOM),
            'appointment_display' => $appt->format('M j, Y g:i A'),
            'appointment_time_display' => $appt->format('g:i A'),
            'appointment_at_local' => $appt->format('Y-m-d\TH:i'),
            'status' => $status,
        ];
    }

    public function parseAppointmentInput(PDO $pdo, int $tenantId, string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $tz = TenantTime::timezoneObject($pdo, $tenantId);
        $normalized = str_replace('T', ' ', $raw);
        if (strlen($normalized) === 16) {
            $normalized .= ':00';
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalized, $tz);
        if ($dt === false) {
            return null;
        }

        return $dt->format('Y-m-d H:i:s');
    }

    public function createEntry(
        PDO $pdo,
        int $tenantId,
        int $assignedUserId,
        string $appointmentAtMysql,
        ?string $patientName,
        string $appointmentType,
        ?int $createdByUserId
    ): int {
        $this->assertAssignableUser($pdo, $tenantId, $assignedUserId);
        $patient = self::resolvePatientName($patientName);
        $type = self::normalizeAppointmentType($appointmentType);

        $ins = $pdo->prepare(
            'INSERT INTO appointment_board_entries
                (tenant_id, patient_name, appointment_type, assigned_user_id, appointment_at, created_by_user_id)
             VALUES
                (:tid, :pn, :type, :uid, :at, :cb)'
        );
        $ins->execute([
            'tid' => $tenantId,
            'pn' => $patient,
            'type' => $type,
            'uid' => $assignedUserId,
            'at' => $appointmentAtMysql,
            'cb' => $createdByUserId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function updateEntry(
        PDO $pdo,
        int $tenantId,
        int $entryId,
        int $assignedUserId,
        string $appointmentAtMysql,
        ?string $patientName,
        string $appointmentType
    ): void {
        $this->assertActiveEntry($pdo, $tenantId, $entryId);
        $this->assertAssignableUser($pdo, $tenantId, $assignedUserId);
        $patient = self::resolvePatientName($patientName);
        $type = self::normalizeAppointmentType($appointmentType);

        $upd = $pdo->prepare(
            'UPDATE appointment_board_entries
             SET patient_name = :pn,
                 appointment_type = :type,
                 assigned_user_id = :uid,
                 appointment_at = :at
             WHERE id = :id AND tenant_id = :tid AND completed_at IS NULL'
        );
        $upd->execute([
            'pn' => $patient,
            'type' => $type,
            'uid' => $assignedUserId,
            'at' => $appointmentAtMysql,
            'id' => $entryId,
            'tid' => $tenantId,
        ]);
    }

    public function completeEntry(PDO $pdo, int $tenantId, int $entryId): bool
    {
        $now = TenantTime::nowMysqlInTenantTimezone($pdo, $tenantId);
        $upd = $pdo->prepare(
            'UPDATE appointment_board_entries
             SET completed_at = :ca
             WHERE id = :id AND tenant_id = :tid AND completed_at IS NULL'
        );
        $upd->execute(['ca' => $now, 'id' => $entryId, 'tid' => $tenantId]);

        return $upd->rowCount() > 0;
    }

    private function assertAssignableUser(PDO $pdo, int $tenantId, int $assignedUserId): void
    {
        if ($assignedUserId < 1) {
            throw new \InvalidArgumentException('Select a team member.');
        }
        $chk = $pdo->prepare(
            'SELECT id FROM users WHERE id = :uid AND tenant_id = :tid AND is_active = 1 LIMIT 1'
        );
        $chk->execute(['uid' => $assignedUserId, 'tid' => $tenantId]);
        if (!$chk->fetch()) {
            throw new \InvalidArgumentException('Assigned team member not found.');
        }
    }

    private function assertActiveEntry(PDO $pdo, int $tenantId, int $entryId): void
    {
        if ($entryId < 1) {
            throw new \InvalidArgumentException('Invalid appointment.');
        }
        $chk = $pdo->prepare(
            'SELECT id FROM appointment_board_entries
             WHERE id = :id AND tenant_id = :tid AND completed_at IS NULL LIMIT 1'
        );
        $chk->execute(['id' => $entryId, 'tid' => $tenantId]);
        if (!$chk->fetch()) {
            throw new \InvalidArgumentException('Appointment not found or already completed.');
        }
    }

}
