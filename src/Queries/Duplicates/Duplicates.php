<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\Duplicates;

use Systopic\Db\Scope;
use Systopic\System\Tables\MediaCrops\Model as Crop;
use Systopic\Cms\Tables\Pages\Model as PageRow;

/**
 * Pages and crops that exist more than once — `Duplicates.sql`.
 */
final class Duplicates
{
    /** @return list<PageRow> every copy, grouped by node and language */
    public static function pages(Scope $scope): array
    {
        return $scope->rows(__DIR__ . '/Duplicates.sql', [])->set(0)?->all(PageRow::class) ?? [];
    }

    /** @return list<Crop> every copy, grouped by media and format; `->media` is loaded */
    public static function crops(Scope $scope): array
    {
        return $scope->rows(__DIR__ . '/Duplicates.sql', [])->set(1)?->all(Crop::class) ?? [];
    }

    /** All local original media types; URLs and generated formats are excluded. */
    public static function files(Scope $scope): array
    {
        $rows = $scope->rows(__DIR__ . '/Files.sql', [])->all(\Systopic\System\Tables\Medias\Model::class);
        $files = [];
        foreach ($rows as $row) {
            if (in_array($row->type, ['youtube', 'vimeo'], true)) continue;
            $media = new \Systopic\System\Tables\Medias\Media($row);
            $files[] = ['id' => (int) $row->id, 'label' => trim((string) $row->label) ?: (string) $row->filename, 'path' => $media->path];
        }
        return IdenticalFiles::scan($files);
    }

    public static function page(Scope $scope, int $pageId): ?PageRow
    {
        foreach (self::pages($scope) as $page) {
            if ((int) $page->id === $pageId) {
                return $page;
            }
        }
        return null;
    }

    public static function crop(Scope $scope, int $cropId): ?Crop
    {
        foreach (self::crops($scope) as $crop) {
            if ((int) $crop->id === $cropId) {
                return $crop;
            }
        }
        return null;
    }
}
