<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\PageTree;

use Stringable;
use Systopic\Cms\Pages\PageAccess;
use Systopic\System\Auth\Session;
use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Acls\Operator as AclOperator;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\Db\Scope;
use Systopic\Cms\Queries\Params;
use Systopic\Cms\Tables\Pages\Page;

/**
 * The page tree in the pylon of `cms/pages/` — successor of
 * `lib/class/tree/`.
 *
 * It draws the same markup as `tree` and `treeNode`, character for character.
 * What changes is **how it gets its rows**.
 *
 * `treeNode::appendChildren()` asks per open folder — four open folders, four
 * statements, regardless of whether they sit above or next to each other. Here
 * it fetches per **level**: every open folder of a level in one statement. On
 * a deep, narrow tree that is the same; on a wide one it is the difference
 * between four statements and one.
 *
 * Three further differences, all small and all deliberate:
 *
 * **1. `fold()` checks whether the node exists.** `tree::fold()` calls
 * `$this->removeChildNodes($this->nodesById[$id])` without a guard. For a node
 * that is not in the drawn tree at that moment, that is three warnings — and
 * an AJAX response that is no longer JSON.
 *
 * **2. The mode is called a mode.** `actions.php` calls `fold($id, $this)` and
 * thereby passes the panel as the mode. Harmless, because every value other
 * than `'open'` acts like `'toggle'`, but wrong — here the parameter is a
 * `FoldMode` and a panel does not fit into it.
 *
 * **3. The aliases come as rows.** `tree.sql` packs them into a
 * `GROUP_CONCAT(node_id)`; that has no table in `getColumnMeta()` and depends
 * on `group_concat_max_len`. Here it is a statement of its own for the whole
 * tree with real nodes — route D from section 6 of the design.
 *
 * What the tree no longer does: replace the lean record with the full one when
 * `page::$pages` happens to be filled. That line in `treeNode::__toString()`
 * made the output depend on what other parts of the request had loaded.
 */
final class PageTree implements Stringable
{
    /** @var list<int> */
    private array $openFolds;

    /** @var array<int, PageTreeNode> node id => entry, everything drawn */
    private array $rendered = [];

    /** @var list<PageTreeLevel> the levels, top to bottom */
    private array $levels = [];

    /** @var array<int, list<Node>> node id => its alias nodes */
    private array $aliases = [];

    private bool $built = false;

    public function __construct(
        private readonly Scope $scope,
        private readonly Params $params,
        /** @param list<int> $openFolds nodes whose folders are unfolded */
        array $openFolds = [],
        /** The node the panel is currently showing. */
        public readonly ?int $activeId = null,
        /** @param list<int> $rootIds where the tree starts; 0 is the topmost level */
        private readonly array $rootIds = [0],
    ) {
        $this->openFolds = array_values(array_unique(array_map('intval', $openFolds)));
    }

    public string $lang {
        get => $this->params->lang;
    }

    /** The request this tree reads in. */
    public function scope(): Scope
    {
        return $this->scope;
    }

    /** The parameters this tree is read under. */
    public function params(): Params
    {
        return $this->params;
    }

    // --------------------------------------------------------------- folding

    /** @return list<int> */
    public function openFolds(): array
    {
        return $this->openFolds;
    }

    public function isOpen(int $id): bool
    {
        return in_array($id, $this->openFolds, true);
    }

    /**
     * Unfold or fold a folder.
     *
     * The return value says whether anything changed — `tree::fold()` always
     * returns `false`, which nobody evaluates and nobody explains.
     */
    public function fold(int $id, FoldMode $mode = FoldMode::Toggle): bool
    {
        $open = $this->isOpen($id);

        if ($open && $mode !== FoldMode::Open) {
            $this->openFolds = array_values(array_diff($this->openFolds, [$id]));
            $this->built = false;
            return true;
        }
        if (!$open && $mode !== FoldMode::Close) {
            $this->openFolds[] = $id;
            $this->built = false;
            return true;
        }
        return false;
    }

    /** Fold every folder. */
    public function closeAll(): void
    {
        $this->openFolds = [];
        $this->built = false;
    }

    /**
     * Unfold the way to a node — without the node itself.
     *
     * Successor of `tree::autoFoldActiveNode()`, which taps the front-end
     * route (`PageTree::getIds()`) for it. Here it is a question the tree asks
     * itself, and it costs one statement.
     */
    public function openPathTo(int $nodeId): void
    {
        [$ancestors, ] = PageTreeLevel::chainTo($this->scope, $this->params, $nodeId);
        foreach ($ancestors as $ancestor) {
            $id = $ancestor->node->id;
            if ($id !== null && !$this->isOpen($id)) {
                $this->openFolds[] = $id;
                $this->built = false;
            }
        }
    }

    /**
     * Unfold the branch the active node sits on — but only if it is not
     * visible anyway.
     *
     * `tree::autoFoldActiveNode()` unfolds unconditionally; it costs nothing
     * there, because the route is in memory regardless. Here it costs one
     * statement, and for a page on the topmost level — the common case — it
     * would be wasted.
     */
    public function revealActive(): void
    {
        if ($this->activeId === null || $this->has($this->activeId)) {
            return;
        }
        $this->openPathTo($this->activeId);
    }

    // ---------------------------------------------------------------- build

    /**
     * Level by level, one statement per level.
     *
     * Only the children of nodes that are **open** get loaded. The group
     * policy of `PageTreeLevel` would take the whole level along — right for a
     * render loop that touches everything anyway, wrong for a tree that knows
     * exactly what it unfolds.
     */
    private function build(): void
    {
        if ($this->built) {
            return;
        }
        $this->rendered = [];
        $this->levels = [];

        $level = PageTreeLevel::under($this->scope, $this->params, $this->rootIds);
        $depth = 0;
        /** @var array<int, PageTreeNode> $above node id => entry of the level above */
        $above = [];

        while (true) {
            $this->levels[$depth] = $level;

            $current = [];
            foreach ($level->nodes() as $node) {
                if ($node->id === null) {
                    continue;
                }
                $entry = new PageTreeNode(
                    $this,
                    Page::of($this->scope, $this->params, $node),
                    $above[$level->parentIdOf($node)] ?? null,
                    $depth,
                );
                $this->rendered[$node->id] = $entry;
                $current[$node->id] = $entry;
            }

            $open = [];
            foreach ($level->nodes() as $node) {
                if ($node->id !== null
                    && $this->isOpen($node->id)
                    && $level->hasChildren($node) === true) {
                    $open[] = $node->id;
                }
            }
            if ($open === []) {
                break;
            }
            $level->preload($open);
            $below = $level->children();
            if ($below->nodes() === []) {
                break;
            }
            $above = $current;
            $level = $below;
            $depth++;
        }

        // The aliases for the **whole** tree, not per level: which nodes get
        // drawn is settled now. Only those that have any according to
        // `alias_of` are asked for — if none has, the statement falls away.
        $withAliases = [];
        foreach ($this->rendered as $id => $entry) {
            $entryLevel = $this->levels[$entry->depth] ?? null;
            if ($entryLevel?->hasAliases($entry->node) !== false) {
                $withAliases[] = $id;
            }
        }
        $this->aliases = PageTreeLevel::aliasesFor($this->scope, $this->params, $withAliases);

        $this->built = true;
    }

    /** @return array<int, PageTreeNode> everything that gets drawn */
    public function nodes(): array
    {
        $this->build();
        return $this->rendered;
    }

    /** The entry for a node id, or NULL when it is not in the tree. */
    public function getNode(?int $id): ?PageTreeNode
    {
        if ($id === null) {
            return null;
        }
        return $this->nodes()[$id] ?? null;
    }

    public function has(?int $id): bool
    {
        return $this->getNode($id) !== null;
    }

    /** The entry the panel is showing — NULL when it is not in the tree. */
    public ?PageTreeNode $active {
        get => $this->getNode($this->activeId);
    }

    /** @internal for PageTreeNode */
    public function hasChildren(PageTreeNode $entry): bool
    {
        $this->build();
        return ($this->levels[$entry->depth] ?? null)?->hasChildren($entry->node) === true;
    }

    /** @internal for PageTreeNode @return list<Node> */
    public function aliasesOf(int $id): array
    {
        $this->build();
        return $this->aliases[$id] ?? [];
    }

    // --------------------------------------------------------------- markup

    public function __toString(): string
    {
        $this->build();
        $html = '';
        foreach (($this->levels[0] ?? null)?->nodes() ?? [] as $node) {
            $entry = $this->rendered[$node->id] ?? null;
            if ($entry !== null) {
                $html .= $this->renderNode($entry, $this->rootIds[0] ?? 0);
            }
        }
        return "<ul class='tree' data-page_language_iso='{$this->params->lang}'>" . $html . '</ul>';
    }

    private function renderNode(PageTreeNode $entry, int $parentId): string
    {
        $id = $entry->id;
        $hasChildren = $entry->hasChildren;
        $open = $hasChildren && $this->isOpen($id);

        $below = '';
        if ($open) {
            $level = $this->levels[$entry->depth] ?? null;
            foreach ($level?->childrenOf($entry->node) ?? [] as $child) {
                $childEntry = $this->rendered[$child->id] ?? null;
                if ($childEntry !== null) {
                    $below .= $this->renderNode($childEntry, $id);
                }
            }
            if ($below !== '') {
                $below = '<ul>' . $below . '</ul>';
            }
        }

        // what the user may change (PageAccess): a page they may not is not
        // dragged, and nothing is dropped where it would land under it
        $may = PageAccess::mayWrite($entry->node);
        $mayBeside = PageAccess::mayWrite($entry->node->parentNode);

        // sites move like pages; their paths follow (NodeSite::rebuildBranch)
        $dataAttrs = $entry->edit || !$may ? '' : " data-drag_type='page'";

        $grip = $entry->edit || !$may ? '' : "<i class='treeGrip fa-regular fa-grip-dots-vertical' title='Hold and drag to move'></i>";
        $menu = ($entry->edit || $entry->isSite) ? ''
            : "<button type='button' class='treeMenu' data-on_click='getTreeContextmenu' onmousedown='event.stopPropagation()' title='Menu'><i class='fa-regular fa-ellipsis'></i></button>";
        $html = "<li data-node_id='$id' data-node_parent_node_id='$parentId'"
              . " class='{$this->classNames($entry, $may)}'>";
        $html .= "<label$dataAttrs>";
        $html .= self::sortHelpers($mayBeside, $may);
        $html .= $grip;
        $html .= $this->foldIcon($id, $hasChildren, $open);
        $html .= $this->aliasIcon($entry);
        $html .= $this->label($entry);
        $html .= $this->aclIcons($entry, $may);
        $html .= $this->referenceIcons($entry, $may);
        $html .= $menu;
        $html .= '</label>';
        $html .= "$below</li>";
        return $html;
    }

    private function classNames(PageTreeNode $entry, bool $may): string
    {
        $names = $may ? [] : ['readonly'];
        if ($entry->isActive) {
            $names[] = 'active';
        }
        if ($entry->redirectId > 0) {
            $names[] = 'redirected';
        }
        if ($entry->isSite) {
            $names[] = 'site';
        }
        return implode(' ', $names);
    }

    /**
     * The drop zones for sorting — the same for every entry.
     *
     * Five invisible strips over each entry: before, after, two for media and
     * one that sorts in as the first child (with `Alt` as an alias). They lie
     * on top of each other and are hard to read in the markup.
     *
     * **To make them visible: hold `Shift` + `Ctrl` while dragging.**
     * `event.drag.js` then sets `body.debug` and freezes drag mode — the
     * strips get colour and stay put as long as the keys are held.
     *
     * Before and after land under the entry's parent, into under the entry:
     * each only where the user may change that one.
     */
    private static function sortHelpers(bool $beside = true, bool $into = true): string
    {
        return ($beside
                ? "<div class='sort v insertBefore' data-drop_accept='page' data-on_drop='insertBefore'></div>"
                . "<div class='sort v insertAfter' data-drop_accept='page' data-on_drop='insertAfter'></div>"
                : '')
            . ($into
                ? "<div class='sort v insertInto nosort' data-drop_accept='media' data-on_drop='mml/moveMediaToPage'></div>"
                . "<div class='sort v insertInto nosort' data-drop_accept='mediaAlias' data-on_drop='mml/moveMediaAliasToPage'></div>"
                . "<div class='sort v insertInto' data-drop_accept='page' data-on_drop='insertAsFirstChild' data-on_drop_alt='insertAliasAsFirstChild'></div>"
                : '');
    }

    /** @var array<int, string>|null group id => name, for the titles of the ACL marks */
    private ?array $groupNames = null;

    /**
     * Where the content groups change, for a superuser of a project that
     * judges pages by them (`PAGE_ACL`): a page below the topmost level with
     * groups of its own, a page with none (owner and superusers only). For everybody: a lock on a
     * page they may read but not change.
     */
    private function aclIcons(PageTreeNode $entry, bool $may): string
    {
        $html = $may ? '' : "<i class='acl readonly fa-regular fa-lock' title='Read only: you may not change this page'></i>";
        if ($entry->isSite || !defined('PAGE_ACL') || !\PAGE_ACL || !Session::isSuperuser()) {
            return $html;
        }
        $node = $entry->node;
        if ($node->relationId('acl') === null) {
            return $html . "<i class='acl none fa-regular fa-user-lock' title='No content group: only the owner and superusers'></i>";
        }
        if ($node->aclInherit || $entry->parent === null) {
            return $html;   // a topmost page has its own anyway: no news
        }
        if ($this->groupNames === null) {
            $this->groupNames = [];
            foreach (UserAdmin::of($this->scope)->groups as $group) {
                $this->groupNames[(int) $group->id] = (string) $group->name;
            }
        }
        $names = array_map(fn(int $id): string => $this->groupNames[$id] ?? "#$id", AclOperator::groupIds($node->acl));
        $title = htmlspecialchars('Own content groups: ' . implode(', ', $names), ENT_QUOTES);
        return $html . "<i class='acl own fa-regular fa-users' title='$title'></i>";
    }

    private function foldIcon(int $id, bool $hasChildren, bool $open): string
    {
        if (!$hasChildren) {
            return "<a class='fold leaf'></a>";
        }
        $icon = $open ? 'fa-chevron-down' : 'fa-chevron-right';
        $openClass = $open ? ' open' : '';
        return "<a data-on_click=fold data-id=$id class='fold$openClass'><i class='fa-regular $icon'></i></a>";
    }

    private const ALIAS_ICON = '<i class="fal fa-file-import"></i>';

    private function aliasIcon(PageTreeNode $entry): string
    {
        if (!$entry->isAlias) {
            return '';
        }
        return "<a title='show Original' class='ajax alias' href='./?id={$entry->originalId}'>"
            . self::ALIAS_ICON . '</a>';
    }

    private function label(PageTreeNode $entry): string
    {
        $text = html_entity_decode((string) $entry->label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($entry->isSite) {
            // a site opens its editor in main (site.tpl.php); no context menu
            return "<a class='label site ajax' href='./?id={$entry->id}' title='Site " . htmlentities($text) . "'>"
                . "<i class='fa-regular fa-globe'></i> " . htmlentities($text) . '</a>';
        }
        $typeIcon = $entry->hasChildren ? 'fa-folder' : 'fa-file';
        $icon = "<i class='type fa-regular $typeIcon'></i>";
        if ($entry->edit) {
            return $icon
                . "<span class='label' data-on_blur='updateLabel' data-on_keypress='checkKey' contenteditable='plaintext-only'>"
                . htmlentities($text) . '</span>';
        }
        $title = ' title="' . $entry->ordering . '"';
        return "<a class='label ajax' href='./?id={$entry->id}' data-on_contextmenu='getTreeContextmenu'$title>"
            . $icon
            . ($text === '' ? '- - - -' : htmlentities($text))
            . '</a>';
    }

    private function referenceIcons(PageTreeNode $entry, bool $may = true): string
    {
        $html = '';
        if ($entry->isSite) {
            return $html; // no row: nothing published, nothing aliased
        }
        if ($entry->redirectId > 0 && !$entry->isTrashed) {
            $html .= "<a title='show Alias' class='ajax alias' href='./?id={$entry->redirectId}'>"
                . "<i class='fa-regular fa-arrow-turn-down-right'></i></a>";
        }
        if (!$entry->isTrashed) {
            foreach ($entry->aliases as $alias) {
                $html .= "<a title='show Alias' class='ajax' href='./?id={$alias->id}'>" . self::ALIAS_ICON . '</a>';
            }
        }
        if (!$entry->isPublished) {
            $html .= $may
                ? "<a class='unpublished' title='Hidden - click to publish' data-on_click='publish?id={$entry->id}'>"
                    . "<i class='fa-regular fa-eye-slash'></i></a>"
                : "<i class='fa-regular fa-eye-slash' title='Hidden'></i>";
        }
        if ($entry->isTrashed && $may) {
            $html .= "<a  data-action='recoverPage' data-on_click='userConfirm'"
                . " data-confirm='All langauges, children and versions will be recoverd!'"
                . " title='Recover Page' class='ajax'  data-node_id='{$entry->id}'>"
                . "<i class='fa-regular fa-arrow-rotate-left'></i></a>";
        }
        return $html;
    }
}
