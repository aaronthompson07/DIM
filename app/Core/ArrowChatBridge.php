<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Optional bridge so ArrowChat (hosted on another subdomain, e.g. doitmeow.com/arrowchat) can
 * identify the logged-in DoItMeow user. Browser requests to ArrowChat do not include DoItMeow’s
 * PHP session cookie, so ArrowChat’s integration.php must read this cookie in get_user_id().
 *
 * Copy the sample block at the bottom of this file into arrowchat/includes/integration.php
 * (and set the same ARROWCHAT_BRIDGE_SECRET in ArrowChat’s config or integration file).
 *
 * ArrowChat config.php must also point at DoItMeow’s MySQL database with:
 *   DB_USERTABLE = users
 *   DB_USERTABLE_USERID = id
 *   DB_USERTABLE_NAME = display_name
 * Consider scoping searches to tenant_id in custom functions if multiple companies share one ArrowChat DB.
 *
 * Updated integration.php (friend/online lists, platform tenant OR for owner/super_admin visibility,
 * impersonation scope) lives at resources/arrowchat/integration.example.php — copy to ArrowChat.
 */
final class ArrowChatBridge
{
    private const COOKIE_VERSION = '1';

    public static function isConfigured(): bool
    {
        if (!self::envBool('ARROWCHAT_BRIDGE_ENABLED')) {
            return false;
        }
        $secret = trim((string) env('ARROWCHAT_BRIDGE_SECRET', ''));
        if (strlen($secret) < 32) {
            return false;
        }
        $domain = trim((string) env('ARROWCHAT_BRIDGE_COOKIE_DOMAIN', ''));

        return $domain !== '';
    }

    public static function cookieName(): string
    {
        $n = trim((string) env('ARROWCHAT_BRIDGE_COOKIE_NAME', 'doitmeow_ac'));

        return $n !== '' ? $n : 'doitmeow_ac';
    }

    public static function setBridgeCookie(int $userId, int $tenantId): void
    {
        if (!self::isConfigured() || $userId < 1 || $tenantId < 1) {
            return;
        }
        if (headers_sent()) {
            return;
        }

        $ttl = (int) config('app.session_ttl', 86400);
        $exp = time() + max(300, $ttl);
        $payload = self::COOKIE_VERSION . ':' . $userId . ':' . $tenantId . ':' . $exp;
        $secret = trim((string) env('ARROWCHAT_BRIDGE_SECRET', ''));
        $sig = hash_hmac('sha256', $payload, $secret);
        $value = base64_encode($payload . "\n" . $sig);

        $domain = trim((string) env('ARROWCHAT_BRIDGE_COOKIE_DOMAIN', ''));
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie(self::cookieName(), $value, [
            'expires' => $exp,
            'path' => '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'None',
        ]);
    }

    public static function clearBridgeCookie(): void
    {
        if (!self::isConfigured() || headers_sent()) {
            return;
        }
        $domain = trim((string) env('ARROWCHAT_BRIDGE_COOKIE_DOMAIN', ''));
        if ($domain === '') {
            return;
        }
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie(self::cookieName(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'None',
        ]);
    }

    private static function envBool(string $key): bool
    {
        $v = env($key, false);
        if (is_bool($v)) {
            return $v;
        }
        $s = strtolower(trim((string) $v));

        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }
}

/*
 * --- Sample for arrowchat/includes/integration.php (adjust constant paths) --------------------
 *
 * function doitmeow_arrowchat_bridge_user_id() {
 *     $name = 'doitmeow_ac'; // same as ARROWCHAT_BRIDGE_COOKIE_NAME
 *     $secret = 'YOUR_ARROWCHAT_BRIDGE_SECRET'; // same as in DoItMeow .env
 *     if (empty($_COOKIE[$name])) {
 *         return null;
 *     }
 *     $raw = base64_decode((string) $_COOKIE[$name], true);
 *     if ($raw === false || !str_contains($raw, "\n")) {
 *         return null;
 *     }
 *     [$payload, $sig] = explode("\n", $raw, 2);
 *     $calc = hash_hmac('sha256', $payload, $secret);
 *     if (!hash_equals($calc, $sig)) {
 *         return null;
 *     }
 *     $parts = explode(':', $payload);
 *     if (count($parts) !== 4 || $parts[0] !== '1') {
 *         return null;
 *     }
 *     $userId = (int) $parts[1];
 *     $exp = (int) $parts[3];
 *     if ($exp < time() || $userId < 1) {
 *         return null;
 *     }
 *     return $userId;
 * }
 *
 * function get_user_id() {
 *     $id = doitmeow_arrowchat_bridge_user_id();
 *     return $id > 0 ? $id : null;
 * }
 *
 * If receive_search.php still returns 500, enable display_errors temporarily on the ArrowChat host
 * or check the PHP error log — usually wrong DB_USERTABLE_* constants or a missing column.
 */
