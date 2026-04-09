<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class SubscriptionFeatures
{
    /** Whether the tenant's plan has a feature flag enabled in the subscription matrix. */
    public static function tenantHasFeature(PDO $pdo, int $tenantId, string $featureKey): bool
    {
        if ($tenantId <= 0) {
            return false;
        }
        $stmt = $pdo->prepare(
            'SELECT slf.is_enabled
             FROM tenants t
             INNER JOIN subscription_levels sl ON sl.level_key = t.plan_key AND sl.is_active = 1
             INNER JOIN subscription_level_features slf ON slf.level_id = sl.id
             INNER JOIN feature_flags ff ON ff.id = slf.feature_id AND ff.feature_key = :fk
             WHERE t.id = :tid
             LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'fk' => $featureKey]);
        $row = $stmt->fetch();

        return $row !== false && ((int) $row['is_enabled']) === 1;
    }
}
