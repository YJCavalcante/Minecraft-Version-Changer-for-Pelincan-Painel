<?php

namespace Pelican\Versions\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class McJarsService
{
    private const BASE_URL = 'https://versions.mcjars.app/api/v2';

    /**
     * Get all available software types grouped by category.
     * Categories include: recommended, established, experimental, miscellaneous, limbos.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getTypes(): array
    {
        $ttl = (int) config('versions.api_cache_ttl', 300);

        return Cache::remember('versions:mcjars:types', $ttl, function () {
            try {
                $response = Http::timeout(15)
                    ->acceptJson()
                    ->get(self::BASE_URL . '/types');

                if ($response->successful()) {
                    return (array) ($response->json('types') ?? []);
                }

                Log::warning('[Versions] Failed to fetch MCJars types: ' . $response->status());
            } catch (Throwable $e) {
                Log::error('[Versions] Exception fetching MCJars types: ' . $e->getMessage());
            }

            return [];
        });
    }

    /**
     * Get all Minecraft versions available for a given software type.
     * Returns an array keyed by version ID with version metadata, sorted from newest to oldest.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getVersions(string $type): array
    {
        $typeKey = strtoupper(trim($type));
        $ttl = (int) config('versions.api_cache_ttl', 300);

        return Cache::remember("versions:mcjars:versions:{$typeKey}", $ttl, function () use ($typeKey) {
            try {
                $response = Http::timeout(15)
                    ->acceptJson()
                    ->get(self::BASE_URL . "/builds/{$typeKey}");

                if ($response->successful()) {
                    $rawBuilds = (array) ($response->json('builds') ?? []);
                    return $this->sortVersions($rawBuilds);
                }

                Log::warning("[Versions] Failed to fetch versions for {$typeKey}: " . $response->status());
            } catch (Throwable $e) {
                Log::error("[Versions] Exception fetching versions for {$typeKey}: " . $e->getMessage());
            }

            return [];
        });
    }

    /**
     * Get all builds available for a given software type and Minecraft version.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBuilds(string $type, string $mcVersion): array
    {
        $typeKey = strtoupper(trim($type));
        $mcVer = trim($mcVersion);
        $ttl = (int) config('versions.api_cache_ttl', 300);

        return Cache::remember("versions:mcjars:builds:{$typeKey}:{$mcVer}", $ttl, function () use ($typeKey, $mcVer) {
            try {
                $response = Http::timeout(15)
                    ->acceptJson()
                    ->get(self::BASE_URL . "/builds/{$typeKey}/{$mcVer}");

                if ($response->successful()) {
                    return (array) ($response->json('builds') ?? []);
                }

                Log::warning("[Versions] Failed to fetch builds for {$typeKey}/{$mcVer}: " . $response->status());
            } catch (Throwable $e) {
                Log::error("[Versions] Exception fetching builds for {$typeKey}/{$mcVer}: " . $e->getMessage());
            }

            return [];
        });
    }

    /**
     * Resolve the direct download URL and size from a build payload.
     *
     * @param array<string, mixed> $build
     * @return array{url: ?string, size: ?int, name: ?string, build_number: ?int}
     */
    public function resolveJarDetails(array $build): array
    {
        $url = $build['jarUrl'] ?? null;
        $size = isset($build['jarSize']) ? (int) $build['jarSize'] : null;

        if (empty($url) && !empty($build['installation'][0][0]['url'])) {
            $url = $build['installation'][0][0]['url'];
            $size = isset($build['installation'][0][0]['size']) ? (int) $build['installation'][0][0]['size'] : $size;
        }

        return [
            'url'          => $url,
            'size'         => $size,
            'name'         => $build['name'] ?? ('#' . ($build['buildNumber'] ?? '')),
            'build_number' => isset($build['buildNumber']) ? (int) $build['buildNumber'] : null,
        ];
    }

    /**
     * Sort versions semantically from newest to oldest.
     *
     * @param array<string, mixed> $versions
     * @return array<string, mixed>
     */
    private function sortVersions(array $versions): array
    {
        uksort($versions, function (string $a, string $b): int {
            // Clean versions for comparison (e.g. 1.21.4 vs 1.20.1)
            $cleanA = preg_replace('/[^0-9.]/', '', $a);
            $cleanB = preg_replace('/[^0-9.]/', '', $b);

            if (!empty($cleanA) && !empty($cleanB) && $cleanA !== $cleanB) {
                return version_compare($cleanB, $cleanA);
            }

            return strnatcasecmp($b, $a);
        });

        return $versions;
    }
}
