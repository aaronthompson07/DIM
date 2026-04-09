<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PDO;

/**
 * Calendar dates and clocks for task scheduling use the tenant's IANA timezone, not the PHP server default.
 */
final class TenantTime
{
    /** @var array<int, DateTimeZone> */
    private static array $zoneObjectCache = [];

    public static function clearZoneCache(): void
    {
        self::$zoneObjectCache = [];
    }

    public static function isValidTimezone(string $tz): bool
    {
        $tz = trim($tz);
        if ($tz === '') {
            return false;
        }
        $len = function_exists('mb_strlen') ? mb_strlen($tz) : strlen($tz);
        if ($len > 80) {
            return false;
        }
        try {
            new DateTimeZone($tz);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Normalize a stored or user-provided identifier; invalid values become UTC.
     */
    public static function validateTimezoneIdentifier(string $tz): string
    {
        $tz = trim($tz);
        if ($tz === '') {
            return 'UTC';
        }
        try {
            new DateTimeZone($tz);

            return $tz;
        } catch (\Exception) {
            return 'UTC';
        }
    }

    public static function timezoneStringForTenant(PDO $pdo, int $tenantId): string
    {
        if ($tenantId < 1) {
            return 'UTC';
        }
        $stmt = $pdo->prepare('SELECT timezone FROM tenants WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $tenantId]);
        $row = $stmt->fetch();

        return self::validateTimezoneIdentifier($row && isset($row['timezone']) ? (string) $row['timezone'] : 'UTC');
    }

    public static function timezoneObject(PDO $pdo, int $tenantId): DateTimeZone
    {
        if ($tenantId < 1) {
            return new DateTimeZone('UTC');
        }
        if (!isset(self::$zoneObjectCache[$tenantId])) {
            $id = self::timezoneStringForTenant($pdo, $tenantId);
            self::$zoneObjectCache[$tenantId] = new DateTimeZone($id);
        }

        return self::$zoneObjectCache[$tenantId];
    }

    public static function todayYmd(PDO $pdo, int $tenantId): string
    {
        return (new DateTimeImmutable('now', self::timezoneObject($pdo, $tenantId)))->format('Y-m-d');
    }

    /**
     * @return array{today: string, dow: int, dom: int, last: int}
     */
    public static function calendarPartsNow(PDO $pdo, int $tenantId): array
    {
        $now = new DateTimeImmutable('now', self::timezoneObject($pdo, $tenantId));
        $today = $now->format('Y-m-d');

        return [
            'today' => $today,
            'dow' => (int) $now->format('w'),
            'dom' => (int) $now->format('j'),
            'last' => (int) $now->format('t'),
        ];
    }

    /** Wall-clock datetime in the tenant zone, for storing in MySQL DATETIME (no offset). */
    public static function nowMysqlInTenantTimezone(PDO $pdo, int $tenantId): string
    {
        return (new DateTimeImmutable('now', self::timezoneObject($pdo, $tenantId)))->format('Y-m-d H:i:s');
    }

    /**
     * Interpret a naive "Y-m-d H:i:s" as wall time in the tenant zone and return RFC3339 / Atom (with offset).
     * Used when reading task_occurrences.completed_at written in tenant local time.
     */
    public static function mysqlNaiveDateTimeToAtom(PDO $pdo, int $tenantId, string $mysql): ?string
    {
        $mysql = trim($mysql);
        if (strlen($mysql) < 19) {
            return null;
        }
        $naive = substr($mysql, 0, 19);
        $tz = self::timezoneObject($pdo, $tenantId);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $naive, $tz);
        if ($dt === false) {
            return null;
        }

        return $dt->format(DateTimeInterface::ATOM);
    }

    /**
     * IANA zones grouped by region (first path segment) for HTML selects.
     *
     * @return array<string, array<string, string>> region label => [ id => option label ]
     */
    public static function groupedIdentifiers(): array
    {
        $list = \DateTimeZone::listIdentifiers();
        $grouped = [];
        foreach ($list as $id) {
            if (str_contains($id, '/')) {
                $region = explode('/', $id, 2)[0];
                $rest = explode('/', $id, 2)[1];
                $label = str_replace('_', ' ', $rest) . ' — ' . $id;
            } else {
                $region = 'Other';
                $label = $id;
            }
            if (!isset($grouped[$region])) {
                $grouped[$region] = [];
            }
            $grouped[$region][$id] = $label;
        }
        ksort($grouped);
        foreach ($grouped as &$zones) {
            ksort($zones);
        }
        unset($zones);

        return $grouped;
    }
}
