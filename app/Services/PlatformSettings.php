<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Site-wide key/value settings (owner / super_admin). Affects every tenant.
 */
final class PlatformSettings
{
    public const HOLIDAY_THEME_MODE = 'holiday_theme_mode';
    public const HOLIDAY_THEME_SKIN = 'holiday_theme_skin';

    public const MODE_OFF = 'off';
    public const MODE_AUTO = 'auto';
    public const MODE_MANUAL = 'manual';

    /** @var array<string, string> */
    private static array $cache = [];

    private static bool $loaded = false;

    public static function get(PDO $pdo, string $key, string $default = ''): string
    {
        self::ensureLoaded($pdo);

        return self::$cache[$key] ?? $default;
    }

    public static function set(PDO $pdo, string $key, string $value, ?int $userId = null): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value, updated_by_user_id)
             VALUES (:k, :v, :u)
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_by_user_id = VALUES(updated_by_user_id),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'k' => $key,
            'v' => $value,
            'u' => $userId,
        ]);
        self::$cache[$key] = $value;
        self::$loaded = true;
    }

    /**
     * @param array<string, string> $pairs
     */
    public static function setMany(PDO $pdo, array $pairs, ?int $userId = null): void
    {
        foreach ($pairs as $key => $value) {
            self::set($pdo, (string) $key, (string) $value, $userId);
        }
    }

    /** Clear in-request cache (tests / after migrations). */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$loaded = false;
    }

    private static function ensureLoaded(PDO $pdo): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        self::$cache = [];
        try {
            $stmt = $pdo->query('SELECT setting_key, setting_value FROM platform_settings');
            if ($stmt === false) {
                return;
            }
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                self::$cache[(string) $row['setting_key']] = (string) $row['setting_value'];
            }
        } catch (\Throwable) {
            // Table may not exist yet during a failed migration window.
        }
    }
}
