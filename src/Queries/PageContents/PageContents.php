<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\PageContents;

use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\Cms\Tables\Pages\Model as PageRow;

/**
 * Every page row with its texts - every language, the trash too. For tools
 * that go through all content (tools/migration), not for a page view.
 */
final class PageContents
{
    /** @return list<PageRow> */
    public static function all(Scope $scope): array
    {
        Registry::register(PageRow::class);
        return $scope->rows(__DIR__ . '/PageContents.sql', [])->all(PageRow::class);
    }
}
