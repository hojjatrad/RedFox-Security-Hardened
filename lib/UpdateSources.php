<?php
declare(strict_types=1);

final class RedFoxUpdateSources
{
    public static function settings(PDO $pdo): array
    {
        $row = $pdo->query('SELECT * FROM update_sources WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    public static function save(PDO $pdo, string $repo, string $pattern, bool $unsigned, bool $auto, string $token = ''): void
    {
        $repo = trim(preg_replace('#^https?://github\.com/#', '', $repo), '/');
        if ($repo !== '' && !preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
            throw new RuntimeException('GitHub repository must be owner/repo');
        }
        if ($pattern === '' || strlen($pattern) > 191) throw new RuntimeException('Asset pattern invalid');
        if ($unsigned) throw new RuntimeException('Unsigned update packages are forbidden');
        $pdo->prepare('UPDATE update_sources SET github_repo=?,github_token=?,asset_pattern=?,allow_unsigned_local=0,auto_check=?,updated_at=? WHERE id=1')
            ->execute([$repo, $token !== '' ? $token : null, $pattern, $auto ? 1 : 0, time()]);
    }

    public static function checkGitHub(PDO $pdo): array
    {
        $settings = self::settings($pdo);
        $repo = (string)($settings['github_repo'] ?? '');
        if ($repo === '') throw new RuntimeException('GitHub repository is not configured');

        $url = 'https://api.github.com/repos/' . $repo . '/releases?per_page=10';
        $raw = '';
        $tooLarge = false;
        $token = (string)($settings['github_token'] ?? '');
        $headers = ['Accept: application/vnd.github+json'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'RedFox-Updater/3.0',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($curl, string $chunk) use (&$raw, &$tooLarge): int {
            if (strlen($raw) + strlen($chunk) > 4194304) { $tooLarge = true; return 0; }
            $raw .= $chunk;
            return strlen($chunk);
        });
        $policy = redfox_apply_curl_url_policy($ch, $url, false, false);
        if (empty($policy['ok'])) { curl_close($ch); throw new RuntimeException('GitHub API endpoint rejected'); }
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($tooLarge || $errno !== 0 || $code !== 200) {
            throw new RuntimeException('GitHub API request failed');
        }

        $releases = json_decode($raw, true);
        if (!is_array($releases)) throw new RuntimeException('GitHub response invalid');
        $channel = (string)(rx_env('REDFOX_UPDATE_CHANNEL') ?: 'stable');
        $chosen = null;
        foreach ($releases as $release) {
            if (!empty($release['draft'])) continue;
            $isBeta = !empty($release['prerelease']);
            if (($channel === 'stable' && $isBeta) || ($channel === 'beta' && !$isBeta)) continue;
            $chosen = $release;
            break;
        }
        if (!$chosen) throw new RuntimeException('No release found for channel ' . $channel);

        $pattern = (string)($settings['asset_pattern'] ?? 'RedFox*.zip');
        $download = '';
        $assetName = '';
        foreach ((array)($chosen['assets'] ?? []) as $asset) {
            $name = (string)($asset['name'] ?? '');
            if (fnmatch($pattern, $name, FNM_CASEFOLD) && str_ends_with(strtolower($name), '.zip')) {
                $download = (string)$asset['browser_download_url'];
                $assetName = $name;
                break;
            }
        }
        if ($download === '') {
            $download = (string)($chosen['zipball_url'] ?? '');
            $assetName = 'github-source.zip';
        }
        $version = ltrim((string)($chosen['tag_name'] ?? ''), 'vV');
        $pdo->prepare("UPDATE update_sources SET last_check_at=?,last_version=?,last_url=?,last_source='github',last_file=NULL,last_error=NULL WHERE id=1")
            ->execute([time(), $version, $download]);
        return [
            'version' => $version,
            'url' => $download,
            'name' => $assetName,
            'notes' => (string)($chosen['body'] ?? ''),
            'published_at' => (string)($chosen['published_at'] ?? ''),
            'channel' => $channel,
        ];
    }

    public static function checkLocalFolders(PDO $pdo, string $root, string $current, bool $allowUnsigned): ?array
    {
        $files = array_merge(glob($root . '/updates/*.zip') ?: [], glob($root . '/Upload/*.zip') ?: []);
        $best = null;
        // The legacy flag is retained only for schema/signature compatibility.
        // Local and uploaded packages are always subject to the same Ed25519 check.
        $allowUnsigned = false;
        foreach ($files as $file) {
            try {
                $info = RedFoxSecureUpdater::inspect($file, $current);
            } catch (Throwable $ignored) {
                continue;
            }
            if ($best === null || version_compare($info['version'], $best['version'], '>')) {
                $best = [
                    'version' => $info['version'],
                    'file' => $file,
                    'source' => basename(dirname($file)),
                    'signed' => !empty($info['signed']),
                    'blocked_unsigned' => !empty($info['blocked_unsigned']),
                ];
            }
        }
        if ($best) {
            $existing = (string)$pdo->query('SELECT last_version FROM update_sources WHERE id=1')->fetchColumn();
            if ($existing === '' || version_compare($best['version'], $existing, '>=')) {
                $pdo->prepare("UPDATE update_sources SET last_check_at=?,last_version=?,last_source='folder',last_file=?,last_url=NULL,last_error=NULL WHERE id=1")
                    ->execute([time(), $best['version'], $best['file']]);
            } else {
                $pdo->prepare('UPDATE update_sources SET last_check_at=? WHERE id=1')->execute([time()]);
            }
        } else {
            $pdo->prepare('UPDATE update_sources SET last_check_at=? WHERE id=1')->execute([time()]);
        }
        return $best;
    }

    public static function download(string $url, string $dest, string $token = ''): void
    {
        $allowed = [
            'github.com',
            'api.github.com',
            'objects.githubusercontent.com',
            'github-releases.githubusercontent.com',
            'release-assets.githubusercontent.com',
        ];
        $validateUrl = static function (string $candidate) use ($allowed): string {
            $parts = parse_url($candidate);
            if (!is_array($parts)
                || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
                || isset($parts['user']) || isset($parts['pass'])
                || (isset($parts['port']) && (int)$parts['port'] !== 443)
                || !in_array(strtolower((string)($parts['host'] ?? '')), $allowed, true)) {
                throw new RuntimeException('Untrusted update download URL');
            }
            return $candidate;
        };

        $fh = @fopen($dest, 'xb');
        if (!is_resource($fh)) throw new RuntimeException('Cannot create update download');
        @chmod($dest, 0600);
        $written = 0;
        $tooLarge = false;
        $writeFailed = false;
        $ok = false;
        $currentUrl = $validateUrl($url);

        try {
            // Redirects are followed manually so every hop is constrained to the
            // HTTPS update-host allowlist; libcurl's protocol filter alone does
            // not prevent an allowed host from redirecting to a private host.
            for ($redirects = 0; $redirects <= 5; $redirects++) {
                $status = 0;
                $location = '';
                $ch = curl_init($currentUrl);
                $dlHeaders = ['Accept: application/octet-stream'];
                if ($token !== '') $dlHeaders[] = 'Authorization: Bearer ' . $token;
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_TIMEOUT => 180,
                    CURLOPT_CONNECTTIMEOUT => 15,
                    CURLOPT_USERAGENT => 'RedFox-Updater/3.0',
                    CURLOPT_HTTPHEADER => $dlHeaders,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                ]);
                curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($curl, string $header) use (&$status, &$location): int {
                    if (preg_match('#^HTTP/\\S+\\s+(\\d{3})#i', trim($header), $match)) {
                        $status = (int)$match[1];
                        $location = '';
                    } elseif (stripos($header, 'Location:') === 0) {
                        $location = trim(substr($header, 9));
                    }
                    return strlen($header);
                });
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($curl, string $chunk) use ($fh, &$status, &$written, &$tooLarge, &$writeFailed): int {
                    $length = strlen($chunk);
                    if ($status >= 300 && $status < 400) return $length;
                    if ($written + $length > 134217728) { $tooLarge = true; return 0; }
                    $offset = 0;
                    while ($offset < $length) {
                        $count = fwrite($fh, substr($chunk, $offset));
                        if (!is_int($count) || $count <= 0) { $writeFailed = true; return 0; }
                        $offset += $count;
                        $written += $count;
                    }
                    return $length;
                });
                $policy = redfox_apply_curl_url_policy($ch, $currentUrl, false, false);
                if (empty($policy['ok'])) { curl_close($ch); break; }
                curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $errno = curl_errno($ch);
                curl_close($ch);
                if ($errno !== 0 || $tooLarge || $writeFailed) break;
                if ($code >= 200 && $code < 300) { $ok = true; break; }
                if ($code < 300 || $code >= 400 || $location === '' || $redirects === 5) break;
                // GitHub currently emits absolute redirect URLs. Relative or
                // scheme-relative targets fail closed rather than being guessed.
                $currentUrl = $validateUrl($location);
            }
        } finally {
            fflush($fh);
            fclose($fh);
        }

        if (!$ok || $written < 100) {
            @unlink($dest);
            throw new RuntimeException('Update download failed');
        }
    }
}
