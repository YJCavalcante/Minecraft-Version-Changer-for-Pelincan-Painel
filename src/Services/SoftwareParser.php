<?php

namespace Pelican\Versions\Services;

final class SoftwareParser
{
    public const HYBRIDS = [
        'Youer'     => ['files' => ['youer.yml', 'youer.toml', 'youer-config'], 'jar' => 'youer(?:-server)?'],
        'Mohist'    => ['files' => ['mohist.yml', 'mohist-config'], 'jar' => 'mohist(?:-server)?'],
        'Magma'     => ['files' => ['magma.yml'], 'jar' => 'magma(?:-server)?'],
        'Arclight'  => ['files' => ['arclight.conf'], 'jar' => 'arclight(?:-(?:forge|neoforge|fabric))?'],
        'CatServer' => ['files' => ['catserver.yml'], 'jar' => 'catserver'],
        'Ketting'   => ['files' => ['ketting.yml'], 'jar' => 'ketting(?:-server|-launcher)?'],
        'Banner'    => ['files' => ['banner.yml', 'banner-config'], 'jar' => 'banner(?:-server)?'],
        'Thermos'   => ['files' => ['thermos.yml', 'cauldron.yml', 'kcauldron.yml'], 'jar' => '(?:thermos|cauldron|kcauldron)'],
        'Crucible'  => ['files' => ['crucible.yml'], 'jar' => 'crucible'],
        'Cardboard' => ['files' => ['cardboard.yml'], 'jar' => 'cardboard'],
    ];

    public const HYBRID_MARKERS = [
        'Youer'     => '~youer~i',
        'Banner'    => '~mohistmc[/.]banner|banner-server|banner-[0-9]~i',
        'Mohist'    => '~mohist~i',
        'Arclight'  => '~arclight|izzel~i',
        'Ketting'   => '~ketting~i',
        'CatServer' => '~catserver|foxlaunch~i',
        'Magma'     => '~magmafoundation|magma-[0-9]|magma-server~i',
        'Thermos'   => '~thermos|kcauldron|cauldron~i',
        'Crucible'  => '~crucible~i',
        'Cardboard' => '~cardboard~i',
    ];

    private const TRANSIENT_LAUNCH_FILES = [
        'unix_args.txt',
        'run.sh',
        'user_jvm_args.txt',
        'fabric-server-launch.jar',
        'fabric-server-launcher.properties',
        'quilt-server-launch.jar',
        'quilt-server-launcher.properties',
    ];

    public static function cleanMc(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('~^[0-9]+([.][0-9]+)+$~', $value) || preg_match('~^[0-9]{2}w[0-9]{2}[a-z]$~i', $value)) {
            return $value;
        }

        return null;
    }

    public static function neoForgeToMinecraft(string $version): ?string
    {
        if (!preg_match('~^([0-9]+)[.]([0-9]+)~', $version, $m)) {
            return null;
        }

        $major = (int) $m[1];
        $minor = (int) $m[2];

        if ($major >= 22) {
            $core  = (string) preg_replace('~[-+].*$~', '', $version);
            $parts = array_slice(explode('.', $core), 0, 3);

            if (count($parts) === 3 && $parts[2] === '0') {
                array_pop($parts);
            }

            return implode('.', $parts);
        }

        if ($major === 20 || $major === 21) {
            return $minor === 0 ? "1.{$major}" : "1.{$major}.{$minor}";
        }

        return null;
    }

    public static function parseNeoForgeVersion(string $version): array
    {
        if (preg_match('~^(1[.][0-9]+(?:[.][0-9]+)?)-(.+)$~', $version, $m)) {
            return ['version' => $m[1], 'build' => '#' . $m[2]];
        }

        return ['version' => self::neoForgeToMinecraft($version), 'build' => '#' . $version];
    }

    public static function parseForgeVersion(string $version): ?array
    {
        if (preg_match('~^([0-9]+[.][0-9]+(?:[.][0-9]+)?)-(.+)~', $version, $m)) {
            return ['version' => $m[1], 'build' => '#' . $m[2]];
        }

        return null;
    }

    public static function formatHistoryBuild(?string $mcVersion, ?string $buildName, string|int|null $buildNumber): ?string
    {
        $raw = trim((string) ($buildName ?: ($buildNumber !== null && $buildNumber !== '' ? $buildNumber : '')));

        if ($raw === '') {
            return null;
        }

        $raw = ltrim($raw, '#');
        $mc  = trim((string) $mcVersion);

        if ($mc !== '' && str_starts_with($raw, $mc . '-')) {
            $stripped = substr($raw, strlen($mc) + 1);
            if ($stripped !== '') {
                $raw = $stripped;
            }
        }

        return '#' . $raw;
    }

    public static function matchesSearch(string $haystack, string $query): bool
    {
        $h = strtolower($haystack);
        $q = strtolower(trim($query));

        if ($q === '') {
            return true;
        }

        if (str_contains($h, $q)) {
            return true;
        }

        $collapse   = static fn (string $s): string => (string) preg_replace('/(.)\1+/u', '$1', $s);
        $collapsedH = $collapse($h);
        $collapsedQ = $collapse($q);

        if ($collapsedQ !== '' && str_contains($collapsedH, $collapsedQ)) {
            return true;
        }

        $tokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($tokens) > 1) {
            foreach ($tokens as $token) {
                if (!str_contains($h, $token) && !str_contains($collapsedH, $collapse($token))) {
                    return false;
                }
            }

            return true;
        }

        if (strlen($q) >= 5 && strlen($q) <= 32) {
            $words = preg_split('/[^a-z0-9]+/', $h, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($words as $word) {
                if (strlen($word) >= 4 && abs(strlen($word) - strlen($q)) <= 1 && levenshtein($q, $word) <= 1) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function isTransientRootArtifact(string $fileName, array $keepNames = []): bool
    {
        if (in_array($fileName, $keepNames, true)) {
            return false;
        }

        $lower = strtolower($fileName);

        if (in_array($lower, self::TRANSIENT_LAUNCH_FILES, true)) {
            return true;
        }

        if (preg_match('~^(?:neoforge|forge)-[0-9].*[.]jar$~i', $fileName)) {
            return true;
        }

        if (preg_match('~^fabric-server-mc[.].*[.]jar$~i', $fileName)) {
            return true;
        }

        return false;
    }

    public static function isZipDownloadUrl(string $url): bool
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));

        if (str_ends_with($path, '.zip')) {
            return true;
        }

        $query = (string) (parse_url($url, PHP_URL_QUERY) ?? '');

        if ($query !== '') {
            parse_str($query, $params);

            foreach ($params as $value) {
                if (is_string($value) && str_ends_with(strtolower($value), '.zip')) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function normalizeLoader(string $raw): string
    {
        return match (strtolower(trim($raw))) {
            'neoforge' => 'NeoForge',
            'forge'    => 'Forge',
            'fabric', 'fabric-loader' => 'Fabric',
            'quilt', 'quilt-loader'   => 'Quilt',
            default    => ucfirst(strtolower(trim($raw))),
        };
    }

    public static function result(string $software, ?string $version, ?string $build): array
    {
        return [
            'software'     => $software,
            'version'      => $version,
            'build'        => $build,
            'installed_at' => null,
            'source'       => 'disk',
        ];
    }

    public static function parseLaunchContent(string $txt, array $variables = []): ?array
    {
        $fallbackMc = self::cleanMc($variables['MINECRAFT_VERSION'] ?? null);

        if (preg_match('~libraries/net/neoforged/(?:neoforge|forge)/([0-9][0-9A-Za-z.+-]*)/~i', $txt, $m)) {
            return self::neoForgeResult($m[1], $fallbackMc);
        }

        if (preg_match('~neoforge-([0-9][0-9A-Za-z.+-]*?)(?:-installer|-universal|-server|-shim)?[.]jar~i', $txt, $m)) {
            return self::neoForgeResult($m[1], $fallbackMc);
        }

        if (preg_match('~NEOFORGE_VERSION[[:space:]]*=[[:space:]]*["\']?([0-9][0-9A-Za-z.+-]*)~i', $txt, $m)) {
            return self::neoForgeResult($m[1], $fallbackMc);
        }

        if (preg_match('~libraries/net/minecraftforge/forge/([0-9]+[.][0-9]+(?:[.][0-9]+)?-[0-9A-Za-z.+-]+)/~i', $txt, $m)) {
            $parsed = self::parseForgeVersion($m[1]);
            if ($parsed) {
                return self::result('Forge', $parsed['version'], $parsed['build']);
            }
        }

        if (preg_match('~(?<![a-z])forge-([0-9]+[.][0-9]+(?:[.][0-9]+)?)-([0-9A-Za-z.+-]+?)(?:-installer|-universal|-server|-shim)?[.]jar~i', $txt, $m)) {
            return self::result('Forge', $m[1], '#' . $m[2]);
        }

        if (preg_match('~(?<![a-z])FORGE_VERSION[[:space:]]*=[[:space:]]*["\']?([0-9]+[.][0-9]+(?:[.][0-9]+)?)-([0-9A-Za-z.+-]+)~i', $txt, $m)) {
            return self::result('Forge', $m[1], '#' . $m[2]);
        }

        if (preg_match('~(?<![a-z])FORGE_VERSION[[:space:]]*=[[:space:]]*["\']?([0-9][0-9.]*[0-9])~i', $txt, $m)) {
            return self::result('Forge', $fallbackMc, '#' . $m[1]);
        }

        return null;
    }

    public static function parseJarName(string $name, array $variables = []): ?array
    {
        if (!str_ends_with(strtolower($name), '.jar')) {
            return null;
        }

        $found = self::parseLaunchContent($name, $variables);

        if ($found) {
            return $found;
        }

        if (preg_match('~fabric-server-mc[.]([0-9]+(?:[.][0-9]+)*)-loader[.]([0-9]+(?:[.][0-9]+)*)~i', $name, $m)) {
            return self::result('Fabric', $m[1], '#loader-' . $m[2]);
        }

        return null;
    }

    public static function parseModLoaderId(string $id): ?array
    {
        if (preg_match('~^(neoforge|forge|fabric|quilt)[-_](.+)$~i', $id, $m)) {
            return ['software' => self::normalizeLoader($m[1]), 'build' => '#' . $m[2]];
        }

        return null;
    }

    public static function matchHybridMarker(string $text): ?string
    {
        foreach (self::HYBRID_MARKERS as $name => $pattern) {
            if (preg_match($pattern, $text)) {
                return $name;
            }
        }

        return null;
    }

    public static function hybridFromJarName(string $fileName): ?array
    {
        foreach (self::HYBRIDS as $name => $conf) {
            $pattern = '~^' . $conf['jar'] . '(?:-([0-9]+[.][0-9]+(?:[.][0-9]+)?))?(?:-(?!(?:server|installer|universal|shim|launcher)(?:[.]jar|-))([0-9A-Za-z.]+))?.*[.]jar$~i';

            if (preg_match($pattern, $fileName, $m)) {
                $version = ($m[1] ?? '') !== '' ? $m[1] : null;
                $build   = ($m[2] ?? '') !== '' ? '#' . $m[2] : null;

                return ['software' => $name, 'version' => $version, 'build' => $build];
            }
        }

        return null;
    }

    public static function hybridFromConfigName(string $lowerFileName): ?string
    {
        foreach (self::HYBRIDS as $name => $conf) {
            if (in_array($lowerFileName, $conf['files'], true)) {
                return $name;
            }
        }

        return null;
    }

    public static function highestVersion(array $versions): ?string
    {
        $versions = array_values(array_filter(array_map('strval', $versions), fn (string $v): bool => $v !== ''));

        if ($versions === []) {
            return null;
        }

        usort($versions, fn (string $a, string $b): int => version_compare($a, $b));

        return $versions[count($versions) - 1];
    }

    private static function neoForgeResult(string $version, ?string $fallbackMc): array
    {
        $parsed = self::parseNeoForgeVersion($version);

        return self::result('NeoForge', $parsed['version'] ?? $fallbackMc ?? 'Minecraft', $parsed['build']);
    }
}
