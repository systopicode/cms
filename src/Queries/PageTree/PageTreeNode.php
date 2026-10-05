<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\PageTree;

use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\Cms\Tables\Pages\Model as PageRow;
use Systopic\Cms\Tables\Pages\Page;

/**
 * An entry in the drawn tree — successor of `treeNode`.
 *
 * The difference to the legacy code is mostly one of **names**. `treeNode`
 * holds a `$record` and asks it using database column names:
 * `$this->record->node_is_trashed`, `$this->record->page_is_published`,
 * `$this->record->node_original_node_id`. The context menu therefore reaches
 * through a row onto columns fifteen times over, and every rename in the
 * database goes through the markup.
 *
 * Here it reads `isTrashed`, `isPublished`, `originalId` — and behind it sits
 * `Page`, the node with its place in the tree and its page.
 *
 * Two quirks of the legacy code survive because the markup needs them:
 *
 * `contentId` is `treeNode::$node_original_node_id` in the legacy code and
 * means **not** the column of the same name but "the id of the content": the
 * original's for an alias, its own otherwise. `setProperties()` computes that,
 * the name hides it. Here it says what it is.
 *
 * `edit` is a switch an action sets: `editLabel` turns the label into an input
 * field, `updateLabel` takes it back. It belongs on the entry and not on the
 * row — a page is not "being edited", a tree entry is.
 */
final class PageTreeNode
{
    /** In rename mode the label becomes an input field. */
    public bool $edit = false;

    public function __construct(
        public readonly PageTree $tree,
        public readonly Page $page,
        public readonly ?PageTreeNode $parent,
        /** The level this entry sits on — 0 is the topmost. */
        public readonly int $depth,
    ) {}

    public Node $node {
        get => $this->page->node;
    }

    public ?PageRow $row {
        get => $this->page->row;
    }

    public int $id {
        get => (int) $this->page->node->id;
    }

    public string $label {
        get => $this->isSite
            ? (string) (\Systopic\Cms\Pages\Sites\SiteRegistry::nameOfNode($this->tree->scope(), $this->id) ?? 'site ' . $this->id)
            : (string) ($this->page->row?->label ?? '');
    }

    /**
     * A node of type 'site' — the top of one site's pages. It has no page row:
     * it moves like a page, has no context menu and opens the site editor.
     */
    public bool $isSite {
        get => $this->page->node->type === \Systopic\Cms\Pages\Sites\SiteRegistry::NODE_TYPE;
    }

    public string $language {
        get => $this->tree->lang;
    }

    public int $ordering {
        get => (int) ($this->page->node->ordering ?? 0);
    }

    public bool $isTrashed {
        get => $this->page->node->isTrashed;
    }

    public bool $isPublished {
        get => (bool) ($this->page->row?->isPublished ?? false);
    }

    /**
     * 0 means "not an alias".
     *
     * relationId, not refId: the value as it stands, also for a node created
     * or changed earlier in this request (refId only knows query results).
     */
    public int $originalId {
        get => $this->page->node->relationId('originalNode') ?? 0;
    }

    public bool $isAlias {
        get => $this->originalId > 0;
    }

    /**
     * The id of the content: the original's for an alias, its own otherwise.
     *
     * The context menu builds `createAlias` out of it — an alias of an alias
     * points at the same original, not at the alias.
     */
    public int $contentId {
        get => $this->originalId ?: $this->id;
    }

    /** 0 means "no redirect". */
    public int $redirectId {
        get => $this->page->node->relationId('redirectNode') ?? 0;   // see originalId
    }

    public bool $hasChildren {
        get => $this->tree->hasChildren($this);
    }

    public bool $isOpen {
        get => $this->tree->isOpen($this->id);
    }

    public bool $isActive {
        get => $this->tree->activeId === $this->id;
    }

    /** @var list<Node> the places where the same content still hangs */
    public array $aliases {
        get => $this->tree->aliasesOf($this->id);
    }

    /**
     * Does this entry lie on the way to that one?
     *
     * `treeNode::isAncestorOf()` walks the `parent` chain upwards and compares
     * ids. The same here — only over the entries of the drawn tree, which both
     * of them sit in.
     */
    public function isAncestorOf(?PageTreeNode $other): bool
    {
        while ($other !== null) {
            if ($other->id === $this->id) {
                return true;
            }
            $other = $other->parent;
        }
        return false;
    }
}
