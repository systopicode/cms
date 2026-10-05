<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\Translations;

use Systopic\Db\Rows;
use Systopic\Cms\Tables\Pages\Model as PageRow;
use Systopic\Db\Scope;
use Systopic\Cms\Queries\PageTree\PageTreeLevel;

/**
 * Every language of one content node — `Translations.sql`.
 *
 * `page::$available_languages` in the legacy code. The editor asks it for two
 * things: which languages a missing page can be created from, and which rows
 * a duplicate has to copy.
 *
 * Whole rows, through the scope's identity map: the row of the scope's own
 * language is the same object the page view holds.
 */
final class Translations
{
    /** @return array<string, PageRow> language code => row, in code order */
    public static function of(Scope $scope, int $contentNodeId): array
    {
        PageTreeLevel::register();
        $rows = $scope->rows(__DIR__ . '/Translations.sql', ['nodeId' => $contentNodeId]);

        $byLanguage = [];
        foreach ($rows->all(PageRow::class) as $row) {
            $byLanguage[(string) $row->languageIso] = $row;
        }
        return $byLanguage;
    }
}
