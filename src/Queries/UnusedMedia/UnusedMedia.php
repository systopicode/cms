<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\UnusedMedia;

use Systopic\Db\Scope;

/**
 * Media no live page uses: not in a page's library, not in a page text,
 * not a share image.
 *
 * An alias is left out. It points at another medium's files, and deleting
 * it would take those files with it.
 */
final class UnusedMedia
{
    /** @return list<array{node: int, id: int, filename: string, type: string}> */
    public static function of(Scope $scope): array
    {
        $media = [];
        $byId = [];
        foreach ($scope->rows(__DIR__ . '/UnusedMedia.media.sql', [])->looseValues() as $row) {
            $node = (int) $row['node'];
            $media[$node] = [
                'node' => $node,
                'id' => (int) $row['id'],
                'parent' => (int) $row['parent'],
                'filename' => (string) $row['filename'],
                'type' => (string) $row['type'],
            ];
            $byId[(int) $row['id']] = $node;
        }
        if ($media === []) {
            return [];
        }

        $nodes = [];
        foreach ($scope->rows(__DIR__ . '/UnusedMedia.nodes.sql', [])->looseValues() as $row) {
            $nodes[(int) $row['id']] = $row;
        }
        $used = [];
        foreach ($nodes as $node => $row) {
            if ($row['type'] !== 'media') {
                continue;
            }
            $id = (int) $row['parent'];
            $guard = 0;
            $onPage = false;
            while (isset($nodes[$id]) && $guard++ < 40) {
                if ($nodes[$id]['type'] === 'page' && !(int) $nodes[$id]['trashed']) {
                    $onPage = true;
                    break;
                }
                $id = (int) $nodes[$id]['parent'];
            }
            if (!$onPage) {
                continue;
            }
            $used[$node] = true;
            // An alias on a page shows the original's files.
            $original = (int) $row['original'];
            if ($original !== 0) {
                $used[$original] = true;
            }
        }
        foreach ($scope->rows(__DIR__ . '/UnusedMedia.share.sql', [])->looseValues() as $row) {
            $used[(int) $row['node']] = true;
        }
        foreach ($scope->rows(__DIR__ . '/UnusedMedia.html.sql', [])->looseValues() as $row) {
            $html = (string) $row['html'];
            if (preg_match_all('~(?:media:|data-node_id\s*=\s*["\']?)(\d+)~i', $html, $ids)) {
                foreach ($ids[1] as $id) {
                    $used[(int) $id] = true;
                }
            }
            if (preg_match_all('~var/(?:formats/[^/"\'\s]+|original)/(\d{3})/(\d{2})/~', $html, $folders, PREG_SET_ORDER)) {
                foreach ($folders as $folder) {
                    $id = (int) ($folder[1] . $folder[2]);
                    if (isset($byId[$id])) {
                        $used[$byId[$id]] = true;
                    }
                }
            }
        }

        // References can name aliases outside a page's media library too.
        // Follow chains and stop at cycles, including references in HTML/share images.
        foreach (array_keys($used) as $node) {
            $seen = [];
            while (isset($nodes[$node]) && !isset($seen[$node])) {
                $seen[$node] = true;
                $original = (int) $nodes[$node]['original'];
                if ($original === 0) {
                    break;
                }
                $used[$original] = true;
                $node = $original;
            }
        }

        $unused = [];
        foreach ($media as $node => $row) {
            if (!isset($used[$node])) {
                unset($row['parent']);
                $unused[] = $row;
            }
        }
        usort($unused, static fn(array $a, array $b): int => strcasecmp($a['filename'], $b['filename']));
        return $unused;
    }
}
