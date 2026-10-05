<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Keywords;

use Systopic\Db\TableRow;

/**
 * A keyword — `cms_keywords`.
 *
 * The assignment to pages is `cms_keywords_in_pages` — `KeywordInPage`. Note
 * which side it hangs on: `kip_page_id` points at `cms_pages`, so a keyword
 * belongs to a page **in one language**, not to the node.
 */
final class Model extends TableRow
{
    public const TABLE  = 'keywords';
    public const PREFIX = 'keyword';

    public ?string $string {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** ISO code of the language, '*' for language-neutral. */
    public ?string $languageIso {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
