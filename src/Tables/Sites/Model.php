<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Sites;

use Systopic\Db\Schema\Col;
use Systopic\Db\Schema\Index;
use Systopic\Db\TableRow;
use Systopic\Db\Type\Text;
use Systopic\System\Tables\Nodes\Model as Node;

/**
 * A website served from the shared page tree — `cms_sites`.
 *
 * A site is a node of type 'site' on the topmost level of the tree; its pages
 * hang below it. The default site has no node (`site_node_id` NULL): its
 * pages are the topmost pages themselves.
 *
 * `name` is the domain with TLD ('beautiful-technology.de'). It is what the
 * rules of the instance files name (`"matches": [{ …, "site": "<name>" }]`)
 * and the name of the site's folder `sites/<name>/`.
 *
 * `meta` is JSON: title, appName, author, description, keywords, robots,
 * titleSeparator, shareImage — read by `appSite`.
 */
final class Model extends TableRow
{
    public const TABLE  = 'sites';
    public const PREFIX = 'site';

    /** The site's node in the tree; NULL for the default site. */
    public ?Node $node {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(length: 191, index: Index::Unique)]
    public string $name = '' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Text $meta {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * Further domains of the site — a JSON list (`["beautiful-technology.com"]`).
     *
     * They answer next to the name and redirect there with a 301
     * (`SiteRegistry::redirectAlias()`): one address per page. Hosts of a
     * stage (RC subdomains, DEV paths) do not belong here — they live in the
     * rules of the instance files (`"matches"`), this list travels with every copy of the
     * database. Written by `SiteRegistry::saveAliases()`.
     */
    public ?Text $aliases {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    // ---- virtual: computed, not a column -----------------------------------

    public bool $isDefault { get => $this->relationId('node') === null; }

    /** @return array<string, mixed> */
    public array $metaData {
        get {
            $decoded = json_decode((string) ($this->meta?->value ?? ''), true);
            return is_array($decoded) ? $decoded : [];
        }
    }

    /** @return list<string> the alias domains, lower case */
    public array $aliasList {
        get {
            $decoded = json_decode((string) ($this->aliases?->value ?? ''), true);
            return is_array($decoded) ? array_values(array_filter(array_map(
                static fn($d) => is_string($d) ? strtolower(trim($d)) : '',
                $decoded,
            ))) : [];
        }
    }
}
