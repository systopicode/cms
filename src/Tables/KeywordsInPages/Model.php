<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\KeywordsInPages;

use Systopic\Db\TableRow;
use Systopic\Cms\Tables\Keywords\Model as Keyword;
use Systopic\Cms\Tables\Pages\Model as Page;

/**
 * A keyword on a page — `cms_keywords_in_pages`.
 *
 * Note which side it hangs on: `kip_page_id` points at `cms_pages`, not at
 * `cms_nodes`. A keyword therefore belongs to a page **in one language**,
 * which is right — the German and the English version of the same node do not
 * share their search terms.
 */
final class Model extends TableRow
{
    public const TABLE  = 'keywords_in_pages';
    public const PREFIX = 'kip';
    public const KEY    = ['keyword', 'page'];

    public ?Keyword $keyword {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Page $page {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
