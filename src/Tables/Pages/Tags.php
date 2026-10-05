<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Pages;

use Systopic\Db\Scope;
use Systopic\System\Queries\TagGroups\TagGroups;
use Systopic\System\Queries\TagLinks\TagLinks;
use Systopic\System\Tables\Tags\Model as Tag;

/**
 * The tags of a page — data only.
 *
 *   $page->tags->ids        list<int>, the legacy keys()
 *   $page->tags->all        list<Tag> with name and group
 *   $page->tags->isEmpty
 *
 * The markup is not here. The legacy `tags::toSearchHtml()` / `toHtml()` built
 * it in the model — with the group colour as inline style on text and
 * background alike, which made the labels unreadable. A project renders its
 * tags in a template of its own (systopic.net: `components/tagFilter.tpl.php`).
 *
 * Tags hang on the node, not on the content: an alias carries its own.
 */
final class Tags
{
    public function __construct(
        private readonly Scope $scope,
        private readonly Page $page,
    ) {}

    /** @var list<int> */
    public array $ids {
        get => array_values(array_map('intval', TagLinks::tagIdsOf($this->scope, $this->page->id)));
    }

    /** @var list<Tag> in id order, as the legacy collection listed them */
    public array $all {
        get {
            $byId = [];
            foreach (TagGroups::load($this->scope) as $group) {
                foreach ($group->tags as $tag) {
                    $byId[(int) $tag->id] = $tag;
                }
            }
            $tags = [];
            foreach ($this->ids as $id) {
                if (isset($byId[$id])) {
                    $tags[] = $byId[$id];
                }
            }
            return $tags;
        }
    }

    public bool $isEmpty {
        get => $this->ids === [];
    }

    public function has(int $tagId): bool
    {
        return in_array($tagId, $this->ids, true);
    }
}
