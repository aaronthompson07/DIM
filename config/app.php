<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'DoItMeow'),
    /** Shown in the site footer and on the changelog page. Default: root VERSION file; override with APP_VERSION in .env if needed. */
    'version' => app_version(),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => env('APP_URL', ''),
    'session_name' => env('SESSION_NAME', 'doitmeow_session'),
    'session_ttl' => (int) env('SESSION_TTL', 86400),
    /** e.g. `.example.com` so ArrowChat on another subdomain receives the same session cookie (HTTPS). */
    'session_cookie_domain' => env('SESSION_COOKIE_DOMAIN', ''),
];
