<?php

declare(strict_types=1);
namespace Systopic\Cms\Queries\MediaUsage;

use Systopic\Db\Scope;

/** Reverse references for the media sidebar. No writes and no resolved public URLs. */
final class MediaUsage
{
    public static function of(Scope $scope, int $node, int $mediaId): array
    {
        return self::collect(
            $scope->rows(dirname(__DIR__) . '/UnusedMedia/UnusedMedia.nodes.sql', [])->looseValues(),
            $scope->rows(__DIR__ . '/MediaUsage.sql', [])->looseValues(), $node, $mediaId,
        );
    }

    public static function collect(iterable $nodes, iterable $pages, int $target, int $mediaId): array
    {
        $byId = [];
        foreach ($nodes as $node) $byId[(int) $node['id']] = $node;
        $original = static function (int $id) use ($byId): int {
            $seen = [];
            while (isset($byId[$id]) && !isset($seen[$id]) && (int) $byId[$id]['original']) {
                $seen[$id] = true;
                $id = (int) $byId[$id]['original'];
            }
            return $id;
        };
        $target = $original($target);
        $library = [];
        foreach ($byId as $id => $node) {
            if ($node['type'] !== 'media' || (int) $node['trashed'] || $original($id) !== $target) continue;
            $seen = [];
            $parent = (int) $node['parent'];
            while (isset($byId[$parent]) && !isset($seen[$parent])) {
                $seen[$parent] = true;
                if ((int) $byId[$parent]['trashed']) break;
                if ($byId[$parent]['type'] === 'page') { $library[$parent] = true; break; }
                $parent = (int) $byId[$parent]['parent'];
            }
        }
        $result = [];
        foreach ($pages as $page) {
            $reasons = [];
            if (isset($library[(int) $page['node']])) $reasons[] = 'Media library';
            if ((int) $page['share'] > 0 && $original((int) $page['share']) === $target) $reasons[] = 'Share image';
            $content = (string) $page['content'];
            preg_match_all('~(?:media::?|data-node_id\s*=\s*["\']?)(\d+)~i', $content, $refs);
            foreach ($refs[1] as $ref) if ($original((int) $ref) === $target) { $reasons[] = 'Content'; break; }
            $folder = sprintf('%05d', $mediaId);
            if ($mediaId > 0 && preg_match('~var/(?:formats/[^/"\'\s]+|original)/' . substr($folder, 0, 3) . '/' . substr($folder, 3) . '/~', $content)) $reasons[] = 'Content';
            if ($reasons) $result[] = ['node' => (int) $page['node'], 'lang' => (string) $page['lang'], 'label' => (string) $page['label'], 'reasons' => array_values(array_unique($reasons))];
        }
        return $result;
    }
}
