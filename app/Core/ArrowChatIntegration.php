<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class ArrowChatIntegration
{
    private const DEFAULT_AUTOLOAD_URL = 'https://doitmeow.com/arrowchat/autoload.php';

    /** Validated autoload script URL from env, or null if unset or invalid. */
    public static function resolvedAutoloadUrl(): ?string
    {
        $u = trim((string) env('ARROWCHAT_AUTOLOAD_URL', self::DEFAULT_AUTOLOAD_URL));
        if ($u === '') {
            $u = self::DEFAULT_AUTOLOAD_URL;
        }
        if (!preg_match('#^https?://#i', $u)) {
            return null;
        }

        return $u;
    }

    /** Footer `<script src>` URL when the plan includes ArrowChat and autoload URL is valid. */
    public static function footerScriptSrcIfEnabled(PDO $pdo, int $tenantId): ?string
    {
        $url = self::resolvedAutoloadUrl();
        if ($url === null || !SubscriptionFeatures::tenantHasFeature($pdo, $tenantId, 'chat.arrowchat')) {
            return null;
        }

        return $url;
    }
}
