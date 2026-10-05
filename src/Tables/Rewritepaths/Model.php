<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Rewritepaths;

use DateTimeImmutable;
use Systopic\Db\Schema\Col;
use Systopic\Db\Schema\Index;
use Systopic\Db\TableRow;
use Systopic\Cms\Tables\Pages\Model as Page;

/**
 * A speaking path — `cms_rewritepaths`.
 *
 * The class is named after the table, not after its context. In the legacy
 * code it is called `pageRewritepath` but maps the neutral table — and the
 * page-specific logic sits next to it in `pageRewritepathFuncs`. The split is
 * already right there, the naming is not.
 *
 * Why `PageRewritepath extends Rewritepath` would not work: the row reader
 * maps table -> class, one to one. `getColumnMeta()` says `cms_rewritepaths` —
 * which class should the registry return? And the identity map is defined over
 * (table, id); two classes for one row would be two objects with two sets of
 * changes overwriting each other on save. **Context is not a property of the
 * row.**
 *
 * What `page` knows beyond that moves to `ModelPageRewritepaths` in
 * phase 5.
 */
final class Model extends TableRow
{
    public const TABLE  = 'rewritepaths';
    public const PREFIX = 'rewritepath';

    public ?Page $page {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * The site this path belongs to — `rewritepath_site_node_id`: the node
     * of type 'site' the page hangs under, NULL for the default site.
     *
     * Paths are unique per site, so every site has its own '' (home) and its
     * own 'kontakt'. The value is never edited: `Pages\Sites\NodeSite` copies
     * it from the page's place in the tree whenever a path is written or a
     * branch moves, and the maintenance panel rewrites all of them at once.
     */
    public ?\Systopic\System\Tables\Nodes\Model $siteNode {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** The site's node id, NULL for the default site — without resolving the node. */
    public ?int $siteNodeId {
        get => $this->relationId('siteNode');
    }

    /**
     * The path itself — and the widest column in the installation that still
     * carries a **full** index: 1022 × 3 bytes (utf8mb3) = 3066, six bytes
     * under InnoDB's 3072-byte limit.
     *
     * That margin is worth knowing about. Convert this table to utf8mb4 — the
     * server default here — and the same 1022 characters become 4088 bytes,
     * past the limit, and the index can no longer be created. The conversion
     * fails with an error that does not obviously point at the charset.
     */
    #[Col(length: 1022, index: Index::Plain)]
    public ?string $string {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** Hit counters — they only ever go up. */
    #[Col(unsigned: true)]
    public int $livecount = 0 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(unsigned: true)]
    public int $testcount = 0 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $livecountDatetime {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?DateTimeImmutable $testcountDatetime {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?bool $isPrimary {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?int $autoIndex {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?int $barrierId {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
