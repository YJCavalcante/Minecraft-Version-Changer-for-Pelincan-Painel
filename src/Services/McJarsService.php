<?php

namespace Pelican\Versions\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class McJarsService
{
    private const BASE_URL = 'https://versions.mcjars.app/api/v2';

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

    public function resolveJarDetails(array $build): array
    {
        $url = $build['jarUrl'] ?? null;
        if (empty($url) && !empty($build['zipUrl'])) {
            $url = $build['zipUrl'];
        }
        $size = isset($build['jarSize']) ? (int) $build['jarSize'] : (isset($build['zipSize']) ? (int) $build['zipSize'] : null);

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

    private function sortVersions(array $versions): array
    {
        uksort($versions, function (string $a, string $b): int {
            $cleanA = trim((string) preg_replace('/[^0-9.]/', '', $a), '.');
            $cleanB = trim((string) preg_replace('/[^0-9.]/', '', $b), '.');

            if (!empty($cleanA) && !empty($cleanB) && $cleanA !== $cleanB) {
                return version_compare($cleanB, $cleanA);
            }

            return strnatcasecmp($b, $a);
        });

        return $versions;
    }
}
