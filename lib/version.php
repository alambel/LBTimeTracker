<?php
const GITHUB_OWNER = 'alambel';
const GITHUB_REPO = 'LBTimeTracker';
const GITHUB_BRANCH = 'main';
const VERSION_CACHE_TTL = 300; // 5 min

/**
 * Lit les infos de déploiement (hash, date ISO, subject du dernier commit).
 *
 * Ordre de résolution :
 *   1. version.txt (3 lignes : hash, date ISO, subject) — généré au déploiement
 *   2. `git log -1` via exec() si autorisé et .git/ présent
 *   3. Lecture directe de .git/HEAD + ref file (hash + mtime, pas de subject)
 *   4. API publique GitHub (cache 5 min sur .version_cache.json)
 *
 * Pour générer version.txt côté Plesk (actions de déploiement supplémentaires) :
 *   git log -1 --format='%H%n%cI%n%s' > version.txt
 */
function get_deployment_info(): ?array {
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }

    // 1. version.txt (prioritaire : rapide, fiable, marche sans exec)
    $file = BASE_DIR . '/version.txt';
    if (is_readable($file)) {
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines) && count($lines) >= 3) {
            return $cache = [
                'hash' => (string)$lines[0],
                'date' => (string)$lines[1],
                'subject' => (string)$lines[2],
            ];
        }
    }

    // 2. git log via exec()
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (function_exists('exec') && !in_array('exec', $disabled, true) && is_dir(BASE_DIR . '/.git')) {
        $cmd = 'cd ' . escapeshellarg(BASE_DIR) . ' && git log -1 --format=%H%x1f%cI%x1f%s 2>/dev/null';
        $output = [];
        @exec($cmd, $output);
        if (!empty($output[0])) {
            $parts = explode("\x1f", $output[0], 3);
            if (count($parts) === 3) {
                return $cache = [
                    'hash' => $parts[0],
                    'date' => $parts[1],
                    'subject' => $parts[2],
                ];
            }
        }
    }

    // 3. Lecture directe de .git (hash + mtime uniquement)
    $head = BASE_DIR . '/.git/HEAD';
    if (is_readable($head)) {
        $content = trim((string)@file_get_contents($head));
        if (str_starts_with($content, 'ref: ')) {
            $refPath = BASE_DIR . '/.git/' . trim(substr($content, 5));
            if (is_readable($refPath)) {
                $mtime = (int)@filemtime($refPath);
                return $cache = [
                    'hash' => trim((string)@file_get_contents($refPath)),
                    'date' => $mtime > 0 ? date('c', $mtime) : '',
                    'subject' => '',
                ];
            }
        } elseif (preg_match('/^[a-f0-9]{40}$/', $content)) {
            $mtime = (int)@filemtime($head);
            return $cache = [
                'hash' => $content,
                'date' => $mtime > 0 ? date('c', $mtime) : '',
                'subject' => '',
            ];
        }
    }

    // 4. Fallback API GitHub publique (avec cache fichier 5 min)
    $fromGithub = fetch_github_commit();
    if ($fromGithub !== null) {
        return $cache = $fromGithub;
    }

    return $cache = null;
}

function fetch_github_commit(): ?array {
    $cacheFile = BASE_DIR . '/.version_cache.json';
    if (is_readable($cacheFile)) {
        $mtime = (int)@filemtime($cacheFile);
        if ($mtime > 0 && (time() - $mtime) < VERSION_CACHE_TTL) {
            $data = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($data) && !empty($data['hash'])) {
                return $data;
            }
        }
    }

    $url = 'https://api.github.com/repos/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/commits/' . GITHUB_BRANCH;
    $body = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'LBTimeTracker/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github.v3+json'],
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200) {
            $body = false;
        }
    } elseif (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: LBTimeTracker/1.0\r\nAccept: application/vnd.github.v3+json\r\n",
                'timeout' => 3,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
    }

    if ($body === false || $body === '') {
        return null;
    }

    $data = json_decode((string)$body, true);
    if (!is_array($data) || empty($data['sha'])) {
        return null;
    }

    $message = (string)($data['commit']['message'] ?? '');
    $subject = explode("\n", $message, 2)[0];

    $result = [
        'hash' => (string)$data['sha'],
        'date' => (string)($data['commit']['committer']['date'] ?? ''),
        'subject' => $subject,
    ];

    @file_put_contents($cacheFile, json_encode($result));
    return $result;
}

function asset_version(): string {
    $info = get_deployment_info();
    if ($info !== null && !empty($info['hash'])) {
        return substr($info['hash'], 0, 10);
    }
    // Fallback: filemtime of this file
    $f = BASE_DIR . '/assets/style.css';
    if (is_readable($f)) {
        return (string)@filemtime($f);
    }
    return (string)time();
}

function asset_url(string $path): string {
    return e($path) . '?v=' . e(asset_version());
}

function format_deployment_footer(): string {
    $info = get_deployment_info();
    if ($info === null || $info['hash'] === '') {
        return '';
    }
    $shortHash = substr($info['hash'], 0, 7);
    $dateStr = '';
    if ($info['date'] !== '') {
        try {
            $dt = new DateTimeImmutable($info['date']);
            $dt = $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $dateStr = $dt->format('d/m/Y H:i');
        } catch (Throwable $e) {
            $dateStr = $info['date'];
        }
    }

    $html = '<footer class="footer">';
    $html .= '<span class="footer-hash" title="' . e($info['hash']) . '">' . e($shortHash) . '</span>';
    if (!empty($info['subject'])) {
        $html .= '<span class="footer-subject" title="' . e($info['subject']) . '">' . e($info['subject']) . '</span>';
    }
    if ($dateStr !== '') {
        $html .= '<span class="footer-date">déployé le ' . e($dateStr) . '</span>';
    }
    $html .= '</footer>';
    return $html;
}
