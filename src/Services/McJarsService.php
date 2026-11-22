<?php

namespace Pelican\Versions\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class McJarsService
{
    private const BASE_URL = 'https://versions.mcjars.app/api/v2';

    public function getTypes(bool $refresh = false): array
    {
        $ttl = (int) config('versions.api_cache_ttl', 21600);

        return $this->remember('types', $ttl, $refresh, function (): ?array {
            $json = $this->fetch('/types');

            return $json === null ? null : (array) ($json['types'] ?? []);
        });
    }

    public function getVersions(string $type, bool $refresh = false): array
    {
        $typeKey = $this->normalizeType($type);

        if ($typeKey === null) {
            return [];
        }

        $ttl = (int) config('versions.api_cache_ttl', 21600);

        return $this->remember("versions:{$typeKey}", $ttl, $refresh, function () use ($typeKey): ?array {
            $json = $this->fetch('/builds/' . rawurlencode($typeKey));

            if ($json === null) {
                return null;
            }

            $releases  = [];
            $snapshots = [];

            foreach ((array) ($json['builds'] ?? []) as $versionId => $v) {
                $meta = [
                    'type'      => $v['type'] ?? 'RELEASE',
                    'supported' => (bool) ($v['supported'] ?? true),
                    'builds'    => (int) ($v['builds'] ?? 0),
                    'java'      => isset($v['java']) ? (int) $v['java'] : null,
                ];

                if (($v['type'] ?? '') === 'RELEASE') {
                    $releases[$versionId] = $meta;
                } else {
                    $snapshots[$versionId] = $meta;
                }
            }

            return $this->sortReleases($releases) + array_slice($snapshots, 0, 25, true);
        });
    }

    public function getBuilds(string $type, string $mcVersion, bool $refresh = false): array
    {
        $typeKey = $this->normalizeType($type);
        $mcVer   = trim($mcVersion);

        if ($typeKey === null || !preg_match('~^[0-9A-Za-z._+-]{1,64}$~', $mcVer)) {
            return [];
        }

        $ttl   = (int) config('versions.builds_cache_ttl', 900);
        $limit = max(1, (int) config('versions.builds_limit', 60));

        return $this->remember("builds:{$typeKey}:{$mcVer}", $ttl, $refresh, function () use ($typeKey, $mcVer, $limit): ?array {
            $json = $this->fetch('/builds/' . rawurlencode($typeKey) . '/' . rawurlencode($mcVer));

            if ($json === null) {
                return null;
            }

            $builds = [];

            foreach (array_slice((array) ($json['builds'] ?? []), 0, $limit) as $b) {
                $url  = $b['jarUrl'] ?? ($b['zipUrl'] ?? null);
                $size = isset($b['jarSize']) ? (int) $b['jarSize'] : (isset($b['zipSize']) ? (int) $b['zipSize'] : null);

                if (empty($url) && !empty($b['installation'][0][0]['url'])) {
                    $url    = $b['installation'][0][0]['url'];
                    $size ??= isset($b['installation'][0][0]['size']) ? (int) $b['installation'][0][0]['size'] : null;
                }

                $builds[] = [
                    'id'           => $b['id'] ?? null,
                    'name'         => $b['name'] ?? ('#' . ($b['buildNumber'] ?? '')),
                    'buildNumber'  => isset($b['buildNumber']) ? (string) $b['buildNumber'] : ($b['name'] ?? ''),
                    'jarUrl'       => $url,
                    'jarSize'      => $size,
                    'created'      => isset($b['created']) ? substr((string) $b['created'], 0, 10) : null,
                    'experimental' => !empty($b['experimental']),
                    'changes'      => array_slice($b['changes'] ?? [], 0, 3),
                ];
            }

            return $builds;
        });
    }

    public function lastFetchFailed(string $key): bool
    {
        return Cache::has("versions:mcjars:{$key}:fail");
    }

    public function resolveJarDetails(array $build): array
    {
        $url = $build['jarUrl'] ?? null;
        if (empty($url) && !empty($build['zipUrl'])) {
            $url = $build['zipUrl'];
        }
        $size = isset($build['jarSize']) ? (int) $build['jarSize'] : (isset($build['zipSize']) ? (int) $build['zipSize'] : null);

        if (empty($url) && !empty($build['installation'][0][0]['url'])) {
            $url  = $build['installation'][0][0]['url'];
            $size = isset($build['installation'][0][0]['size']) ? (int) $build['installation'][0][0]['size'] : $size;
        }

        return [
            'url'          => $url,
            'size'         => $size,
            'name'         => $build['name'] ?? ('#' . ($build['buildNumber'] ?? '')),
            'build_number' => isset($build['buildNumber']) && is_numeric($build['buildNumber']) ? (int) $build['buildNumber'] : null,
        ];
    }

    public function isAllowedDownloadUrl(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $parts = parse_url($url);

        if (!is_array($parts) || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);

        if ($host === 'localhost'
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_starts_with($host, '[')
            || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        $allowed = (array) config('versions.allowed_download_hosts', []);

        if ($allowed === []) {
            return true;
        }

        foreach ($allowed as $pattern) {
            if (Str::is(strtolower((string) $pattern), $host)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeType(string $type): ?string
    {
        $key = strtoupper(trim($type));

        return preg_match('~^[A-Z0-9_-]{1,64}$~', $key) ? $key : null;
    }

    private function remember(string $key, int $ttl, bool $refresh, callable $loader): array
    {
        $cacheKey = "versions:mcjars:{$key}";

        if (!$refresh) {
            $hit = Cache::get($cacheKey);

            if (is_array($hit) && $hit !== []) {
                return $hit;
            }

            if (Cache::has($cacheKey . ':fail')) {
                return $this->stale($cacheKey);
            }
        }

        $value = $loader();

        if (is_array($value) && $value !== []) {
            Cache::put($cacheKey, $value, $ttl);
            Cache::put($cacheKey . ':stale', $value, now()->addDays(7));
            Cache::forget($cacheKey . ':fail');

            return $value;
        }

        Cache::put($cacheKey . ':fail', 1, 30);

        return $this->stale($cacheKey);
    }

    private function stale(string $cacheKey): array
    {
        $stale = Cache::get($cacheKey . ':stale');

        return is_array($stale) ? $stale : [];
    }

    private function fetch(string $path): ?array
    {
        try {
            $response = Http::withUserAgent('Pelican-Versions-Plugin')
                ->timeout(15)
                ->retry(2, 300, throw: false)
                ->acceptJson()
                ->get(self::BASE_URL . $path);

            if ($response->successful()) {
                $json = $response->json();

                return is_array($json) ? $json : null;
            }

            Log::warning("[Versions] MCJars request {$path} failed: " . $response->status());
        } catch (Throwable $e) {
            Log::error("[Versions] MCJars request {$path} exception: " . $e->getMessage());
        }

        return null;
    }

    private function sortReleases(array $versions): array
    {
        uksort($versions, function ($a, $b): int {
            $a = (string) $a;
            $b = (string) $b;

            $numericA = (bool) preg_match('~^[0-9]+(?:[.][0-9]+)*$~', $a);
            $numericB = (bool) preg_match('~^[0-9]+(?:[.][0-9]+)*$~', $b);

            if ($numericA && $numericB) {
                return version_compare($b, $a);
            }

            if ($numericA !== $numericB) {
                return $numericA ? -1 : 1;
            }

            return strnatcasecmp($b, $a);
        });

        return $versions;
    }
}
