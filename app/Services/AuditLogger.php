<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AuditLogger
{
    public static function log(PDO $pdo, ?int $tenantId, ?int $userId, string $action, array $meta = []): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (tenant_id, user_id, action, ip_address, user_agent, meta_json, created_at)
             VALUES (:tenant_id, :user_id, :action, :ip, :ua, :meta_json, NOW())'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'meta_json' => json_encode($meta, JSON_UNESCAPED_SLASHES),
        ]);
    }
}
