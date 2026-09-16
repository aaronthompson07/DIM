<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantTime;
use DateTimeImmutable;
use PDO;

final class AppointmentBoardService
{
    private const PURGE_MINUTES = 30;
    private const SOON_HOURS = 2;

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

    public function ensureTenantPublicKey(PDO $pdo, int $tenantId): string
    {
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

    public function purgeExpired(PDO $pdo, int $tenantId): void
    {
        $tz = TenantTime::timezoneObject($pdo, $tenantId);
        $cutoff = (new DateTimeImmutable('now', $tz))
            ->modify('-' . self::PURGE_MINUTES . ' minutes')
            ->format('Y-m-d H:i:s');

        $del = $pdo->prepare(
            'DELETE FROM appointment_board_entries
             WHERE tenant_id = :tid AND appointment_at < :cutoff'
        );
        $del->execute(['tid' => $tenantId, 'cutoff' => $cutoff]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveForDisplay(PDO $pdo, int $tenantId): array
    {
        $this->purgeExpired($pdo, $tenantId);

        $tz = TenantTime::timezoneObject($pdo, $tenantId);
        $now = new DateTimeImmutable('now', $tz);
        $cutoff = $now->modify('-' . self::PURGE_MINUTES . ' minutes')->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'SELECT e.id, e.patient_name, e.appointment_at, e.notes, u.display_name AS assigned_name
             FROM appointment_board_entries e
             INNER JOIN users u ON u.id = e.assigned_user_id
             WHERE e.tenant_id = :tid AND e.appointment_at >= :cutoff
             ORDER BY e.appointment_at ASC'
        );
        $stmt->execute(['tid' => $tenantId, 'cutoff' => $cutoff]);
        $rows = $stmt->fetchAll();

        $soonUntil = $now->modify('+' . self::SOON_HOURS . ' hours');
        $out = [];
        foreach ($rows as $row) {
            $apptMysql = (string) $row['appointment_at'];
            $appt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr($apptMysql, 0, 19), $tz);
            if ($appt === false) {
                continue;
            }

            if ($appt < $now) {
                $status = 'overdue';
            } elseif ($appt <= $soonUntil) {
                $status = 'soon';
            } else {
                $status = 'upcoming';
            }

            $atom = TenantTime::mysqlNaiveDateTimeToAtom($pdo, $tenantId, $apptMysql);
            $out[] = [
                'id' => (int) $row['id'],
                'patient_name' => (string) $row['patient_name'],
                'assigned_name' => (string) $row['assigned_name'],
                'appointment_at' => $atom ?? $appt->format(DATE_ATOM),
                'appointment_display' => $appt->format('M j, Y g:i A'),
                'notes' => $row['notes'] !== null ? (string) $row['notes'] : '',
                'status' => $status,
            ];
        }

        return $out;
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
        ?string $notes,
        ?int $createdByUserId
    ): int {
        $chk = $pdo->prepare(
            'SELECT id FROM users WHERE id = :uid AND tenant_id = :tid AND is_active = 1 LIMIT 1'
        );
        $chk->execute(['uid' => $assignedUserId, 'tid' => $tenantId]);
        if (!$chk->fetch()) {
            throw new \InvalidArgumentException('Assigned team member not found.');
        }

        $notesTrim = $notes !== null ? trim($notes) : '';
        if (strlen($notesTrim) > 5000) {
            throw new \InvalidArgumentException('Notes are too long (max 5000 characters).');
        }

        $ins = $pdo->prepare(
            'INSERT INTO appointment_board_entries
                (tenant_id, patient_name, assigned_user_id, appointment_at, notes, created_by_user_id)
             VALUES
                (:tid, :pn, :uid, :at, :notes, :cb)'
        );
        $ins->execute([
            'tid' => $tenantId,
            'pn' => self::randomPatientName(),
            'uid' => $assignedUserId,
            'at' => $appointmentAtMysql,
            'notes' => $notesTrim === '' ? null : $notesTrim,
            'cb' => $createdByUserId,
        ]);

        return (int) $pdo->lastInsertId();
    }
}
