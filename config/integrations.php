<?php

declare(strict_types=1);

/**
 * Third-party integrations (env-driven).
 * AdSense client/slot IDs are public in the page source when ads run.
 *
 * ArrowChat autoload URL defaults in code; optional .env override. Footer injection and
 * /api/auth/me integrations use the subscription feature matrix (chat.arrowchat).
 */
return [
    'google_adsense' => [
        'client' => env('GOOGLE_ADSENSE_CLIENT', ''),
        'slot_dashboard' => env('GOOGLE_ADSENSE_SLOT_DASHBOARD', ''),
    ],
];
