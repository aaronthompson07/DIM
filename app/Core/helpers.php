<?php

declare(strict_types=1);

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = dirname(__DIR__, 2);
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . $path;
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        return $value === null ? $default : $value;
    }
}

/**
 * Deployed app version for footer / changelog. Optional env APP_VERSION overrides the VERSION file.
 */
if (!function_exists('app_version')) {
    function app_version(): string
    {
        $override = env('APP_VERSION');
        if ($override !== null && trim((string) $override) !== '') {
            return trim((string) $override);
        }
        $path = base_path('VERSION');
        if (!is_file($path)) {
            return '1.0.0';
        }
        $v = trim((string) file_get_contents($path));
        return $v !== '' ? $v : '1.0.0';
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $items = [];
        if (empty($items)) {
            $files = glob(base_path('config/*.php'));
            foreach ($files as $file) {
                $items[basename($file, '.php')] = require $file;
            }
        }

        $segments = explode('.', $key);
        $value = $items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('json_response')) {
    function json_response(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
