<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\LinkCheck;

use Systopic\Db\Scope;

/**
 * The links and embeds in the html fields of the pages out of the trash.
 * Inside links are checked here against the tree, the paths and the files;
 * outside links only collected - asking every host takes the maintenance
 * panel's button (cms/tools/maintenance/links/).
 */
final class LinkCheck
{
    /** Outside addresses checked per request of the maintenance panel. */
    public const BATCH = 10;

    /** @var array{broken: list<array{node: int, lang: string, label: string, target: string, reason: string}>, external: array<string, list<int>>, labels: array<int, string>}|null */
    private static ?array $result = null;

    /** @return list<array{node: int, lang: string, label: string, target: string, reason: string}> */
    public static function broken(Scope $scope): array
    {
        return self::run($scope)['broken'];
    }

    /** @return array<string, list<int>> every outside address, with the pages that use it */
    public static function external(Scope $scope): array
    {
        return self::run($scope)['external'];
    }

    /** @return array<int, string> the title of every page with links, by node */
    public static function labels(Scope $scope): array
    {
        return self::run($scope)['labels'];
    }

    /** @return list<array{id: int, label: string, target: string}> one entry per page, the first broken target */
    public static function brokenPages(Scope $scope, int $limit): array
    {
        $out = [];
        foreach (self::broken($scope) as $hit) {
            $out[$hit['node']] ??= ['id' => $hit['node'], 'label' => $hit['label'], 'target' => $hit['target']];
        }
        return array_slice(array_values($out), 0, $limit);
    }

    /** @return array{broken: list<array{node: int, lang: string, label: string, target: string, reason: string}>, external: array<string, list<int>>, labels: array<int, string>} */
    private static function run(Scope $scope): array
    {
        if (self::$result !== null) {
            return self::$result;
        }
        $nodes = $paths = [];
        foreach ($scope->rows(__DIR__ . '/LinkCheck.targets.sql', [])->looseValues() as $row) {
            $nodes[(int) $row['lc_node_id']] = true;
            if ($row['lc_path'] !== null && !str_starts_with((string) $row['lc_path'], '#')) {
                $paths[trim((string) $row['lc_path'], '/')] = true;
            }
        }
        $root = (string) \http::$root;
        $broken = $external = $labels = [];
        foreach ($scope->rows(__DIR__ . '/LinkCheck.sql', [])->looseValues() as $row) {
            $html = str_replace(['###http::siteRoot###', '###http::varRoot###'], [$root, $root . 'var/'], (string) $row['lc_html']);
            $page = (int) $row['lc_node_id'];
            $found = [];
            preg_match_all('~\b(href|src)\s*=\s*(["\'])(.*?)\2~i', $html, $links, PREG_SET_ORDER);
            foreach ($links as [, , , $target]) {
                $target = html_entity_decode(trim($target), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (preg_match('~^https?://~i', $target)) {
                    $external[$target][$page] = $page;
                    continue;
                }
                $reason = self::inside($target, $root, $nodes, $paths);
                if ($reason !== null) {
                    $found[$target] = $reason;
                }
            }
            preg_match_all('~data-node_id\s*=\s*["\']?(\d+)~i', $html, $media);
            foreach ($media[1] as $id) {
                if (!isset($nodes[(int) $id])) {
                    $found['#' . $id] = 'The media is gone or in the trash.';
                }
            }
            $label = trim(html_entity_decode(strip_tags((string) $row['lc_label']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $labels[$page] ??= $label !== '' ? $label : 'Untitled';
            foreach ($found as $target => $reason) {
                $broken[] = ['node' => $page, 'lang' => (string) $row['lc_lang'], 'label' => $label, 'target' => (string) $target, 'reason' => $reason];
            }
        }
        return self::$result = [
            'broken' => $broken,
            'external' => array_map('array_values', $external),
            'labels' => $labels,
        ];
    }

    // ------------------------------------------------------ outside links

    /** @return array{checked: ?string, offset: int, done: bool, failed: array<string, string>} the last check, as far as it got */
    public static function lastCheck(): array
    {
        $data = json_decode((string) @file_get_contents(self::file()), true);
        return [
            'checked' => is_array($data) ? ($data['checked'] ?? null) : null,
            'offset' => is_array($data) ? (int) ($data['offset'] ?? 0) : 0,
            'done' => is_array($data) && !empty($data['done']),
            'failed' => is_array($data) && is_array($data['failed'] ?? null) ? $data['failed'] : [],
        ];
    }

    /**
     * Checks the next BATCH of outside addresses; a check that is done starts over.
     *
     * @return array{checked: ?string, offset: int, done: bool, failed: array<string, string>}
     */
    public static function checkNext(Scope $scope): array
    {
        $urls = array_keys(self::external($scope));
        $last = self::lastCheck();
        if ($last['done'] || $last['checked'] === null) {
            $last = ['checked' => gmdate('Y-m-d H:i:s'), 'offset' => 0, 'done' => false, 'failed' => []];
        }
        $batch = array_slice($urls, $last['offset'], self::BATCH);
        foreach (self::ask($batch) as $url => $problem) {
            $last['failed'][$url] = $problem;
        }
        $last['offset'] += count($batch);
        $last['done'] = $last['offset'] >= count($urls);
        $last['failed'] = array_intersect_key($last['failed'], array_flip($urls));
        @mkdir(dirname(self::file()), 0775, true);
        file_put_contents(self::file(), json_encode($last, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        return $last;
    }

    private static function file(): string
    {
        return \fs::toNative((string) \fs::$siteRoot . 'var/cache/linkcheck.json');
    }

    /**
     * The hosts asked all at once: HEAD first, GET where a host refuses HEAD.
     *
     * @param list<string> $urls
     * @return array<string, string> what went wrong, by address
     */
    private static function ask(array $urls, bool $get = false): array
    {
        if ($urls === []) {
            return [];
        }
        $multi = curl_multi_init();
        $handles = [];
        foreach ($urls as $url) {
            $handle = curl_init($url);
            curl_setopt_array($handle, ($get ? [CURLOPT_RANGE => '0-0'] : [CURLOPT_NOBODY => true]) + [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (fluxo cms link check)',
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[$url] = $handle;
        }
        $errors = [];
        do {
            $status = curl_multi_exec($multi, $running);
            $running && curl_multi_select($multi, 1.0);
            while ($info = curl_multi_info_read($multi)) {
                if ($info['result'] !== CURLE_OK) {
                    $errors[spl_object_id($info['handle'])] = curl_strerror($info['result']);
                }
            }
        } while ($running && $status === CURLM_OK);
        $failed = $retry = [];
        foreach ($handles as $url => $handle) {
            $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = $errors[spl_object_id($handle)] ?? '';
            curl_multi_remove_handle($multi, $handle);
            // 999 (LinkedIn) and 429 turn robots away; the page itself may well be there
            if (($code >= 200 && $code < 400) || $code === 999 || $code === 429) {
                continue;
            }
            if (!$get && in_array($code, [403, 405, 501], true)) {
                $retry[] = $url;
                continue;
            }
            $failed[$url] = $code > 0 ? 'HTTP ' . $code : ($error !== '' ? $error : 'No answer');
        }
        curl_multi_close($multi);
        return $failed + self::ask($retry, true);
    }

    /**
     * Why an inside link leads nowhere, NULL when it is fine or not ours
     * (mail, phone, anchors, other schemes).
     *
     * @param array<int, true> $nodes
     * @param array<string, true> $paths
     */
    private static function inside(string $target, string $root, array $nodes, array $paths): ?string
    {
        if ($target === '' || preg_match('~^(#|[a-z][\w+.-]*:|//)~i', $target)) {
            return null;
        }
        if (preg_match('~^\?id=(\d+)~', $target, $m)) {
            return isset($nodes[(int) $m[1]]) ? null : 'The page is gone or in the trash.';
        }
        if (!str_starts_with($target, $root)) {
            return null;
        }
        $path = trim((string) parse_url(substr($target, strlen($root)), PHP_URL_PATH), '/');
        if ($path === '' || str_starts_with($path, 'cms/')) {
            return null;
        }
        if (str_starts_with($path, 'var/')) {
            return is_file(\fs::toNative((string) \fs::$siteRoot . rawurldecode($path))) ? null : 'The file does not exist.';
        }
        if (isset($paths[$path])) {
            return null;
        }
        // an alias appends its node id to the path of its original
        if (preg_match('~^(.+)/(\d+)$~', $path, $m) && isset($paths[$m[1]]) && isset($nodes[(int) $m[2]])) {
            return null;
        }
        return 'No page has this address.';
    }
}
