<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Fetches active NOAA/NWS alerts and current conditions for a tenant's US ZIP via api.weather.gov.
 * Alert responses are cached ~5 minutes; current conditions ~10 minutes.
 */
final class WeatherAlertsService
{
    private const CACHE_TTL_SEC = 300;

    private const CACHE_TTL_CURRENT_SEC = 600;

    /** Identifiable User-Agent (required by weather.gov API). */
    private const NWS_USER_AGENT = 'DoItMeow/1.0 (https://github.com/)';

    /**
     * @return array{ok: bool, configured: bool, alerts: list<array<string, mixed>>, fetched_at: string|null, error: string|null}
     */
    public function getAlertsForTenant(PDO $pdo, int $tenantId): array
    {
        if ($tenantId < 1) {
            return [
                'ok' => false,
                'configured' => false,
                'alerts' => [],
                'fetched_at' => null,
                'error' => 'Invalid tenant.',
            ];
        }

        $stmt = $pdo->prepare('SELECT zip_code FROM tenants WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $tenantId]);
        $row = $stmt->fetch();
        $zipRaw = $row && $row['zip_code'] !== null ? trim((string) $row['zip_code']) : '';

        $zip5 = self::normalizeUsZip5($zipRaw);
        if ($zip5 === null) {
            return [
                'ok' => true,
                'configured' => false,
                'alerts' => [],
                'fetched_at' => null,
                'error' => null,
            ];
        }

        $cachePath = self::cachePath($tenantId);
        $now = time();

        if (is_file($cachePath)) {
            $cached = json_decode((string) file_get_contents($cachePath), true);
            if (
                is_array($cached)
                && ($cached['zip5'] ?? '') === $zip5
                && ($now - (int) ($cached['ts'] ?? 0)) < self::CACHE_TTL_SEC
                && isset($cached['payload']) && is_array($cached['payload'])
            ) {
                return $cached['payload'];
            }
        }

        $latLon = self::geocodeZipUs($zip5);
        if ($latLon === null) {
            $payload = [
                'ok' => true,
                'configured' => true,
                'alerts' => [],
                'fetched_at' => gmdate('c'),
                'error' => 'Could not look up that ZIP code.',
            ];
            self::writeCache($cachePath, $zip5, $payload);

            return $payload;
        }

        [$lat, $lon] = $latLon;
        $nwsUrl = sprintf(
            'https://api.weather.gov/alerts/active?point=%s,%s',
            rawurlencode((string) $lat),
            rawurlencode((string) $lon)
        );

        $json = self::httpGetJson($nwsUrl, [
            'User-Agent: ' . self::NWS_USER_AGENT,
            'Accept: application/geo+json',
        ]);

        if ($json === null) {
            $payload = [
                'ok' => true,
                'configured' => true,
                'alerts' => [],
                'fetched_at' => gmdate('c'),
                'error' => 'Weather service temporarily unavailable.',
            ];
            self::writeCache($cachePath, $zip5, $payload);

            return $payload;
        }

        $features = $json['features'] ?? [];
        if (!is_array($features)) {
            $features = [];
        }

        $alerts = [];
        foreach ($features as $feat) {
            if (!is_array($feat)) {
                continue;
            }
            $p = $feat['properties'] ?? null;
            if (!is_array($p)) {
                continue;
            }
            $alerts[] = [
                'event' => isset($p['event']) ? (string) $p['event'] : 'Alert',
                'headline' => isset($p['headline']) ? (string) $p['headline'] : '',
                'description' => isset($p['description']) ? (string) $p['description'] : '',
                'severity' => isset($p['severity']) ? (string) $p['severity'] : '',
                'urgency' => isset($p['urgency']) ? (string) $p['urgency'] : '',
                'area_desc' => isset($p['areaDesc']) ? (string) $p['areaDesc'] : '',
                'effective' => isset($p['effective']) ? (string) $p['effective'] : '',
                'expires' => isset($p['expires']) ? (string) $p['expires'] : '',
                'instruction' => isset($p['instruction']) ? (string) $p['instruction'] : '',
                'url' => isset($p['id']) ? (string) $p['id'] : '',
            ];
        }

        $payload = [
            'ok' => true,
            'configured' => true,
            'alerts' => $alerts,
            'fetched_at' => gmdate('c'),
            'error' => null,
        ];

        self::writeCache($cachePath, $zip5, $payload);

        return $payload;
    }

    /**
     * Latest NWS observation near the tenant ZIP (temperature + short description).
     *
     * @return array{ok: bool, configured: bool, temp_f: float|null, description: string, fetched_at: string|null, error: string|null}
     */
    public function getCurrentConditionsForTenant(PDO $pdo, int $tenantId): array
    {
        if ($tenantId < 1) {
            return [
                'ok' => false,
                'configured' => false,
                'temp_f' => null,
                'description' => '',
                'fetched_at' => null,
                'error' => 'Invalid tenant.',
            ];
        }

        $stmt = $pdo->prepare('SELECT zip_code FROM tenants WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $tenantId]);
        $row = $stmt->fetch();
        $zipRaw = $row && $row['zip_code'] !== null ? trim((string) $row['zip_code']) : '';

        $zip5 = self::normalizeUsZip5($zipRaw);
        if ($zip5 === null) {
            return [
                'ok' => true,
                'configured' => false,
                'temp_f' => null,
                'description' => '',
                'fetched_at' => null,
                'error' => null,
            ];
        }

        $cachePath = self::cachePathCurrent($tenantId);
        $now = time();

        if (is_file($cachePath)) {
            $cached = json_decode((string) file_get_contents($cachePath), true);
            if (
                is_array($cached)
                && ($cached['zip5'] ?? '') === $zip5
                && ($now - (int) ($cached['ts'] ?? 0)) < self::CACHE_TTL_CURRENT_SEC
                && isset($cached['payload']) && is_array($cached['payload'])
            ) {
                return $cached['payload'];
            }
        }

        $latLon = self::geocodeZipUs($zip5);
        if ($latLon === null) {
            $payload = [
                'ok' => true,
                'configured' => true,
                'temp_f' => null,
                'description' => '',
                'fetched_at' => gmdate('c'),
                'error' => 'Could not look up that ZIP code.',
            ];
            self::writeCacheCurrent($cachePath, $zip5, $payload);

            return $payload;
        }

        [$lat, $lon] = $latLon;
        $obs = self::nwsLatestObservation($lat, $lon);
        if ($obs === null) {
            $payload = [
                'ok' => true,
                'configured' => true,
                'temp_f' => null,
                'description' => '',
                'fetched_at' => gmdate('c'),
                'error' => 'Weather observations temporarily unavailable.',
            ];
            self::writeCacheCurrent($cachePath, $zip5, $payload);

            return $payload;
        }

        $payload = [
            'ok' => true,
            'configured' => true,
            'temp_f' => $obs['temp_f'],
            'description' => $obs['description'],
            'fetched_at' => gmdate('c'),
            'error' => null,
        ];
        self::writeCacheCurrent($cachePath, $zip5, $payload);

        return $payload;
    }

    public static function invalidateCache(int $tenantId): void
    {
        foreach ([self::cachePathAlerts($tenantId), self::cachePathCurrent($tenantId)] as $p) {
            if (is_file($p)) {
                @unlink($p);
            }
        }
    }

    private static function cacheDir(): string
    {
        $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($base)) {
            @mkdir($base, 0755, true);
        }

        return $base;
    }

    private static function cachePathAlerts(int $tenantId): string
    {
        return self::cacheDir() . DIRECTORY_SEPARATOR . 'weather_alerts_' . $tenantId . '.json';
    }

    private static function cachePathCurrent(int $tenantId): string
    {
        return self::cacheDir() . DIRECTORY_SEPARATOR . 'weather_current_' . $tenantId . '.json';
    }

    /** @deprecated use cachePathAlerts */
    private static function cachePath(int $tenantId): string
    {
        return self::cachePathAlerts($tenantId);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function writeCache(string $path, string $zip5, array $payload): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $wrap = [
            'zip5' => $zip5,
            'ts' => time(),
            'payload' => $payload,
        ];
        @file_put_contents($path, json_encode($wrap, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function writeCacheCurrent(string $path, string $zip5, array $payload): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $wrap = [
            'zip5' => $zip5,
            'ts' => time(),
            'payload' => $payload,
        ];
        @file_put_contents($path, json_encode($wrap, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{temp_f: float|null, description: string}|null
     */
    private static function nwsLatestObservation(float $lat, float $lon): ?array
    {
        $pointsUrl = sprintf(
            'https://api.weather.gov/points/%s,%s',
            rawurlencode((string) $lat),
            rawurlencode((string) $lon)
        );
        $points = self::httpGetJson($pointsUrl, [
            'User-Agent: ' . self::NWS_USER_AGENT,
            'Accept: application/geo+json',
        ]);
        if ($points === null) {
            return null;
        }
        $stationsUrl = $points['properties']['observationStations'] ?? null;
        if (!is_string($stationsUrl) || $stationsUrl === '') {
            return null;
        }

        $stations = self::httpGetJson($stationsUrl, [
            'User-Agent: ' . self::NWS_USER_AGENT,
            'Accept: application/geo+json',
        ]);
        if ($stations === null) {
            return null;
        }
        $features = $stations['features'] ?? [];
        if (!is_array($features)) {
            return null;
        }

        $tried = 0;
        foreach ($features as $feat) {
            if ($tried >= 6) {
                break;
            }
            if (!is_array($feat)) {
                continue;
            }
            $stationUrl = $feat['id'] ?? null;
            if (!is_string($stationUrl) || $stationUrl === '') {
                continue;
            }
            ++$tried;
            $latestUrl = rtrim($stationUrl, '/') . '/observations/latest';
            $obs = self::httpGetJson($latestUrl, [
                'User-Agent: ' . self::NWS_USER_AGENT,
                'Accept: application/geo+json',
            ]);
            if ($obs === null) {
                continue;
            }
            $props = $obs['properties'] ?? null;
            if (!is_array($props)) {
                continue;
            }
            $norm = self::normalizeObservationProperties($props);
            if ($norm !== null) {
                return $norm;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $props
     * @return array{temp_f: float|null, description: string}|null
     */
    private static function normalizeObservationProperties(array $props): ?array
    {
        $desc = isset($props['textDescription']) ? trim((string) $props['textDescription']) : '';
        $tempF = null;
        $t = $props['temperature'] ?? null;
        if (is_array($t) && array_key_exists('value', $t) && $t['value'] !== null && is_numeric($t['value'])) {
            $val = (float) $t['value'];
            $unit = isset($t['unitCode']) ? (string) $t['unitCode'] : '';
            if (str_contains($unit, 'degC')) {
                $tempF = $val * 9.0 / 5.0 + 32.0;
            } elseif (str_contains($unit, 'degF')) {
                $tempF = $val;
            } elseif (str_contains($unit, 'K')) {
                $tempF = ($val - 273.15) * 9.0 / 5.0 + 32.0;
            }
        }

        if ($tempF === null && $desc === '') {
            return null;
        }

        return ['temp_f' => $tempF, 'description' => $desc];
    }

    public static function normalizeUsZip5(string $raw): ?string
    {
        $s = preg_replace('/\s+/', '', $raw) ?? '';
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(\d{5})(?:-\d{4})?$/', $s, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function isValidUsZipInput(string $raw): bool
    {
        $s = preg_replace('/\s+/', '', $raw) ?? '';

        return $s === '' || (bool) preg_match('/^\d{5}(?:-\d{4})?$/', $s);
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private static function geocodeZipUs(string $zip5): ?array
    {
        $url = 'https://api.zippopotam.us/us/' . rawurlencode($zip5);
        $json = self::httpGetJson($url, [
            'User-Agent: ' . self::NWS_USER_AGENT,
            'Accept: application/json',
        ]);
        if ($json === null || empty($json['places']) || !is_array($json['places'])) {
            return null;
        }
        $place = $json['places'][0] ?? null;
        if (!is_array($place)) {
            return null;
        }
        $lat = $place['latitude'] ?? null;
        $lon = $place['longitude'] ?? null;
        if ($lat === null || $lon === null) {
            return null;
        }

        return [(float) $lat, (float) $lon];
    }

    /**
     * @param list<string> $headers
     * @return array<string, mixed>|null
     */
    private static function httpGetJson(string $url, array $headers): ?array
    {
        $body = self::httpGetBody($url, $headers);
        if ($body === null || $body === '') {
            return null;
        }
        $data = json_decode($body, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param list<string> $headers
     */
    private static function httpGetBody(string $url, array $headers): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (!is_string($body) || $code < 200 || $code >= 300) {
                return null;
            }

            return $body;
        }

        $ctx = stream_context_create([
            'http' => [
                'header' => implode("\r\n", $headers) . "\r\n",
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (!is_string($body) || $body === '') {
            return null;
        }

        return $body;
    }
}
