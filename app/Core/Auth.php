<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) config('app.session_name', 'doitmeow_session'));
        $cookieParams = [
            'lifetime' => (int) config('app.session_ttl', 86400),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ];
        $cookieDomain = trim((string) config('app.session_cookie_domain', ''));
        if ($cookieDomain !== '') {
            $cookieParams['domain'] = $cookieDomain;
        }
        session_set_cookie_params($cookieParams);
        session_start();
    }

    public static function companyLogin(PDO $pdo, string $companyUsername, string $password): bool
    {
        $stmt = $pdo->prepare('SELECT id, password_hash FROM tenants WHERE company_username = :username AND is_active = 1 LIMIT 1');
        $stmt->execute(['username' => $companyUsername]);
        $tenant = $stmt->fetch();

        if (!$tenant || !password_verify($password, $tenant['password_hash'])) {
            return false;
        }

        $_SESSION['tenant_id'] = (int) $tenant['id'];
        $_SESSION['company_authenticated'] = true;
        $_SESSION['user_id'] = null;

        return true;
    }

    public static function pinLookupHash(string $pin): string
    {
        return hash('sha256', $pin);
    }

    /**
     * Resolve user by 6-digit PIN within the current company (home tenant).
     * Prefers pin_lookup_hash (fast + unique per tenant), then falls back to bcrypt scan
     * so legacy/manual rows with wrong or empty pin_lookup_hash still work; on success
     * pin_lookup_hash is repaired.
     */
    public static function pinLoginByPin(PDO $pdo, string $pin): array
    {
        $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            return ['ok' => false, 'message' => 'Company session is missing.'];
        }

        $lookupHash = self::pinLookupHash($pin);
        $stmt = $pdo->prepare(
            'SELECT id, role, display_name, pin_hash, failed_pin_attempts, pin_locked_until
             FROM users
             WHERE tenant_id = :tenant_id AND pin_lookup_hash = :lookup AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'lookup' => $lookupHash]);
        $user = $stmt->fetch() ?: null;

        if ($user === null) {
            $user = self::findUserByPinVerifyFallback($pdo, $tenantId, $pin);
            if ($user !== null) {
                $fix = $pdo->prepare('UPDATE users SET pin_lookup_hash = :h WHERE id = :id AND tenant_id = :tid');
                $fix->execute(['h' => $lookupHash, 'id' => $user['id'], 'tid' => $tenantId]);
            }
        }

        if ($user === null) {
            return ['ok' => false, 'message' => 'Invalid PIN.'];
        }

        $lockedUntil = $user['pin_locked_until'];
        if ($lockedUntil !== null && strtotime((string) $lockedUntil) > time()) {
            return ['ok' => false, 'message' => 'PIN is locked. Contact your manager or admin.'];
        }

        if (!password_verify($pin, $user['pin_hash'])) {
            $attempts = (int) $user['failed_pin_attempts'] + 1;
            $lockUntil = $attempts >= 5 ? date('Y-m-d H:i:s', strtotime('+30 minutes')) : null;
            $fail = $pdo->prepare(
                'UPDATE users
                 SET failed_pin_attempts = :attempts, pin_locked_until = :lock_until
                 WHERE id = :id'
            );
            $fail->execute([
                'attempts' => $attempts,
                'lock_until' => $lockUntil,
                'id' => $user['id'],
            ]);
            return ['ok' => false, 'message' => $attempts >= 5 ? 'PIN locked after 5 failed attempts.' : 'Invalid PIN.'];
        }

        $reset = $pdo->prepare(
            'UPDATE users
             SET failed_pin_attempts = 0, pin_locked_until = NULL, last_login_at = NOW()
             WHERE id = :id'
        );
        $reset->execute(['id' => $user['id']]);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['display_name'] = (string) $user['display_name'];
        unset($_SESSION['ui_theme']);

        return ['ok' => true, 'message' => 'Logged in.'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function findUserByPinVerifyFallback(PDO $pdo, int $tenantId, string $pin): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, role, display_name, pin_hash, failed_pin_attempts, pin_locked_until
             FROM users
             WHERE tenant_id = :tenant_id AND is_active = 1
             LIMIT 500'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();
        $match = null;
        foreach ($rows as $row) {
            if (!password_verify($pin, $row['pin_hash'])) {
                continue;
            }
            if ($match !== null) {
                return null;
            }
            $match = $row;
        }

        return $match;
    }

    /** Company tenant from login (never swapped by impersonation). */
    public static function homeTenantId(): int
    {
        return (int) ($_SESSION['tenant_id'] ?? 0);
    }

    /**
     * Tenant used for data (tasks, etc.). Owner/super_admin may impersonate another tenant.
     */
    public static function effectiveTenantId(): int
    {
        $home = self::homeTenantId();
        $role = (string) ($_SESSION['role'] ?? '');
        $imp = $_SESSION['impersonate_tenant_id'] ?? null;
        if ($imp !== null && (int) $imp > 0 && in_array($role, ['owner', 'super_admin'], true)) {
            return (int) $imp;
        }

        return $home;
    }

    public static function canImpersonate(): bool
    {
        $role = (string) ($_SESSION['role'] ?? '');

        return in_array($role, ['owner', 'super_admin'], true);
    }

    public static function setImpersonateTenant(?int $tenantId): void
    {
        if ($tenantId === null || $tenantId < 1) {
            unset($_SESSION['impersonate_tenant_id']);
            return;
        }
        $_SESSION['impersonate_tenant_id'] = $tenantId;
    }

    public static function requireUser(): bool
    {
        return !empty($_SESSION['tenant_id']) && !empty($_SESSION['user_id']);
    }

    /** Reports: tenant_manager, tenant_admin, owner, super_admin — not tenant_user. */
    public static function canViewReports(): bool
    {
        if (!self::requireUser()) {
            return false;
        }
        $role = (string) ($_SESSION['role'] ?? '');

        return in_array($role, ['owner', 'super_admin', 'tenant_admin', 'tenant_manager'], true);
    }
}
