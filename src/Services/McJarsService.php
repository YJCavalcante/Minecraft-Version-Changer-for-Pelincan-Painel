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

                    // Extract lightweight version metadata (matches Pterodactyl VersionController.php)
                    // Keeps all RELEASE versions and top 25 recent snapshots/experimentals.
                    // This reduces snapshot size from 1.8MB down to <9KB, preventing Livewire 413/500 errors!
                    $releases = [];
                    $snapshots = [];

                    foreach ($rawBuilds as $versionId => $v) {
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

                    // Keep all releases, plus top 25 recent snapshots
                    $trimmedSnapshots = array_slice($snapshots, 0, 25, true);
                    $allVersions = $releases + $trimmedSnapshots;

                    return $this->sortVersions($allVersions);
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
     * Returns a lightweight array of up to 40 latest builds.
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
                    $rawBuilds = (array) ($response->json('builds') ?? []);
                    $builds = [];

                    // Keep top 40 latest builds and strip heavy fields (matches Pterodactyl VersionController.php)
                    $slice = array_slice($rawBuilds, 0, 40);
                    foreach ($slice as $b) {
                        $url = $b['jarUrl'] ?? ($b['zipUrl'] ?? null);
                        if (empty($url) && !empty($b['installation'][0][0]['url'])) {
                            $url = $b['installation'][0][0]['url'];
                        }

                        $builds[] = [
                            'id'           => $b['id'] ?? null,
                            'name'         => $b['name'] ?? ('#' . ($b['buildNumber'] ?? '')),
                            'buildNumber'  => isset($b['buildNumber']) ? (string) $b['buildNumber'] : ($b['name'] ?? ''),
                            'jarUrl'       => $url,
                            'jarSize'      => isset($b['jarSize']) ? (int) $b['jarSize'] : (isset($b['zipSize']) ? (int) $b['zipSize'] : null),
                            'created'      => isset($b['created']) ? substr($b['created'], 0, 10) : null,
                            'experimental' => !empty($b['experimental']),
                            'changes'      => array_slice($b['changes'] ?? [], 0, 3),
                        ];
                    }

                    return $builds;
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
        if (empty($url) && !empty($build['zipUrl'])) {
            $url = $build['zipUrl'];
        }
        $size = isset($build['jarSize']) ? (int) $build['jarSize'] : null;

        if (empty($url) && !empty($build['installation'][0][0]['url'])) {
            $url = $build['installation'][0][0]['url'];
            $size = isset($build['installation'][0][0]['size']) ? (int) $build['installation'][0][0]['size'] : $size;
        }

        return [
            'url'          => $url,
            'size'         => $size,
            'name'         => $build['name'] ?? ('#' . ($build['buildNumber'] ?? '')),
            'build_number' => isset($build['buildNumber']) && is_numeric($build['buildNumber']) ? (int) $build['buildNumber'] : null,
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
