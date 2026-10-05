<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Pages;

use RuntimeException;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\Cms\Tables\Pages\Model as PageRow;
use Systopic\Cms\Queries\PageDetail\PageDetail;
use Systopic\Cms\Queries\PageTree\PageTreeLevel;
use Systopic\Db\Scope;
use Systopic\Cms\Queries\Params;
use Systopic\System\Tables\Nodes\Operator as NodeOperator;
use Systopic\Cms\Tables\Pages\Operator as PageOperator;
use Systopic\Cms\Queries\Translations\Translations;

/**
 * A place in the tree — phase 5.2 of orm-umsetzung.md.
 *
 * What is spread over seven places in `page.php` today (`getChildren()`,
 * `getParent()`, `getSiblings()` and the three branches of `__get()` calling
 * them) sits together here. `Page` stays a row; **loading is a verb, and verbs
 * do not belong on shared nouns.**
 *
 * A family hangs off the **node**, not off the page, and `->page` is its
 * content. The reason is in `PageTreeLevel`: an alias and its original share one
 * row in `cms_pages`, but they sit at two places in the tree. Only the node
 * can answer "where is this?", only the page can answer "what is there?". The
 * legacy code mixes both into one `page` object and creates the same row
 * several times over to make it work.
 *
 * Three things differ from the legacy code, all three measured:
 *
 * **1. One statement per level, not per node.** The artist collection
 * (page 19) cost 15 statements for three levels, now 3. `PageTreeLevel` does
 * that; `Page` is the view onto it.
 *
 * **2. The way up is one statement.** `getParent()` walks it step by step and
 * ends up loading the row with id 1 twice, which does not exist.
 * `PageTree.ancestors.sql` fetches the whole chain with a recursive CTE — and the
 * root row not at all.
 *
 * **3. `siblings` returns something.** In the legacy code `$page->siblings` is
 * not retrievable: `getSiblings()` sets `nextSibling`/`prevSibling` to NULL
 * and then calls `$this->parent->getChildren()` — `$this->siblings` is never
 * assigned anywhere, and `$this->parent` only exists when somebody read
 * `->parent` beforehand.
 *
 * Names here say what they are. The translation to the old surface
 * (`nextSibling`, `prevSibling`, `firstChild`, `lastChild`) comes in 5.3,
 * where it belongs — on `Tables\Pages\Model`, one line each.
 */
final class Page
{
    private ?PageTreeLevel $generation = null;

    /** @var list<Page>|null ancestors, topmost first, without this place */
    private ?array $ancestorFamilies = null;

    /** @internal `of()` is the way here — one Page per node and parameters */
    public function __construct(
        private readonly Scope $scope,
        private readonly Params $params,
        public readonly Node $node,
    ) {}

    /**
     * The `Page` of a node — one per node and parameter set, for the request.
     *
     * Keyed by the **node**, not the page row: an alias and its original share
     * one row in `cms_pages` but sit at two places in the tree. Keyed by row
     * both would get the same object, and one of the two places disappears —
     * in the first attempt that was exactly one artist out of fifty.
     */
    public static function of(Scope $scope, Params $params, Node $node): self
    {
        return $scope->objectFor(
            self::class,
            $params->key() . '#' . spl_object_id($node),
            static fn(): self => new self($scope, $params, $node),
        );
    }

    /**
     * Pages by node id — `page::get()` in bulk.
     *
     * What is loaded under these parameters costs nothing; the rest arrives in
     * **one** statement. The placeholders in texts (`?id=42`) go through here.
     *
     * @param list<int> $nodeIds
     * @return array<int, Page> id => page, only the ones found
     */
    public static function byIds(Scope $scope, Params $params, array $nodeIds): array
    {
        $table = \Systopic\Db\Schema\Schema::tableNameOf(Node::class);
        $found = [];
        $missing = [];
        foreach (array_unique(array_map('intval', $nodeIds)) as $id) {
            $node = $scope->identity->get($table, (string) $id);
            if ($node instanceof Node
                && $scope->hasObject(self::class, $params->key() . '#' . spl_object_id($node))) {
                $found[$id] = self::of($scope, $params, $node);
            } else {
                $missing[] = $id;
            }
        }
        if ($missing !== []) {
            foreach (PageTreeLevel::ofNodes($scope, $params, $missing)->nodes() as $node) {
                if ($node->id !== null) {
                    $found[$node->id] = self::of($scope, $params, $node);
                }
            }
        }
        return $found;
    }

    public static function byId(Scope $scope, Params $params, int $nodeId): ?self
    {
        return self::byIds($scope, $params, [$nodeId])[$nodeId] ?? null;
    }

    // ------------------------------------------------------------- writing
    //
    // What brings a page **row** into being — step C of the handover. Each
    // method builds its rows, has `Nodes\Operator` hang the node under its
    // parent, and commits through the scope. The commit throws away every
    // result that read a written table, so whoever draws the tree next
    // draws a fresh one.
    //
    // Moving and aliasing are not here: they change nothing but `cms_nodes`,
    // and they are the same for a media or a settings node —
    // `Nodes\Operator::placed()` and `::aliased()`.

    /**
     * A new page under a parent, at a position among its siblings.
     *
     * The node, its row in this language, the renumbered siblings — one
     * commit. NULL as parent is the topmost level; `PHP_INT_MAX` puts it
     * last. Returns the page as loaded, so the caller holds the objects the
     * tree will draw.
     */
    public static function create(
        Scope $scope,
        Params $params,
        ?Node $parent,
        int $index = PHP_INT_MAX,
        string $label = '',
        string $template = '',
        ?int $userId = null,
    ): self {
        $node = new Node();
        $node->type = 'page';
        $row = PageOperator::create($node, $params->lang, $userId, $label);
        $row->templateName = $template;

        $uow = $scope->uow();
        foreach (NodeOperator::placed($scope, $node, $parent, $index) as $changed) {
            $uow->persist($changed);
        }
        $uow->persist($row)->commit();
        return self::loaded($scope, $params, $node);
    }

    /** A copy of this page: a new node right after it, every language copied. */
    public function duplicate(?int $userId = null): self
    {
        $node = new Node();
        $node->type = 'page';

        $uow = $this->scope->uow();
        $after = NodeOperator::indexOf($this->scope, $this->node) + 1;
        foreach (NodeOperator::placed($this->scope, $node, $this->node->parentNode, $after) as $changed) {
            $uow->persist($changed);
        }
        foreach ($this->translations as $lang => $from) {
            $copy = PageOperator::create($node, $lang, $userId, (string) $from->label);
            PageOperator::copy($from, $copy, ['isPublished' => $from->isPublished]);
            $uow->persist($copy);
        }
        $uow->commit();
        return self::loaded($this->scope, $this->params, $node);
    }

    /** The page of a node that was just written. */
    private static function loaded(Scope $scope, Params $params, Node $node): self
    {
        return self::load($scope, $params, (int) $node->id)
            ?? throw new RuntimeException('page ' . $node->id . ' was written but cannot be loaded');
    }

    /**
     * One page, whole — every column of node and page, one statement.
     *
     * The entry point of the editor. What the tree of the same scope has
     * already loaded comes back as the same objects, completed by `PageDetail.sql`;
     * the page and the tree entry are then one thing, and a label written here
     * is the label the tree draws.
     *
     * NULL when the node does not exist. A node without a page in this
     * language comes back with `$row` NULL.
     */
    public static function load(Scope $scope, Params $params, int $nodeId): ?self
    {
        $level = PageDetail::level($scope, $params, $nodeId);
        foreach ($level->nodes() as $node) {
            if ($node->id === $nodeId) {
                return self::of($scope, $params, $node);
            }
        }
        return null;
    }

    /** The node id — the id the CMS addresses a page by. */
    public int $id {
        get => (int) $this->node->id;
    }

    /** The language this page was loaded in. */
    public string $language {
        get => $this->params->lang;
    }

    /**
     * The node whose content this is — the original for an alias.
     *
     * `cms_pages.page_node_id` names this one; an alias has no rows of its
     * own.
     */
    public int $contentNodeId {
        get => (int) ($this->node->relationId('originalNode') ?: $this->node->id);
    }

    /**
     * The same content in every language, language code => row.
     *
     * @var array<string, PageRow>
     */
    public array $translations {
        get => $this->translationRows ??= Translations::of($this->scope, $this->contentNodeId);
    }

    /** @var list<string> the languages this content exists in */
    public array $languages {
        get => array_keys($this->translations);
    }

    /** @var array<string, PageRow>|null */
    private ?array $translationRows = null;

    /** After a new language was written: the next read asks again. */
    public function forgetTranslations(): void
    {
        $this->translationRows = null;
    }

    /** The content of this place — NULL when it does not exist in this language. */
    public ?PageRow $row {
        get => $this->generation()->rowOf($this->node);
    }

    // ---------------------------------------------------------- collaborators

    /**
     * The addresses of this place — paths, `link`, `href`.
     *
     * On the place and not on the page: an alias carries the content of its
     * original but has an address of its own.
     */
    public Rewritepaths $rewritepaths {
        get => $this->rewritepathsObj ??= new Rewritepaths($this->scope, $this->params, $this);
    }

    /** The text fields — `label`, `text01`…, `html01`… */
    public Texts $texts {
        get => $this->textsObj ??= new Texts($this->scope, $this->params, $this);
    }

    /** Template, visibility, the details for search engines and sharing. */
    public Meta $meta {
        get => $this->metaObj ??= new Meta($this->scope, $this->params, $this);
    }

    private ?Rewritepaths $rewritepathsObj = null;
    private ?Texts $textsObj = null;
    private ?Meta $metaObj = null;

    /** @internal the collaborators need the level this place sits on */
    public function generationFor(): PageTreeLevel
    {
        return $this->generation();
    }

    /**
     * The place this node redirects to — `node_redirect_node_id`.
     *
     * `$page->redirect_id` in the legacy code, where `getLink()` follows it.
     * NULL means "no redirect".
     */
    public ?self $redirectsTo {
        get {
            // relationId: the redirect as it stands, also right after it was
            // changed in this request (refId only knows what a query delivered)
            $target = $this->node->relationId('redirectNode');
            return $target === null ? null : self::byId($this->scope, $this->params, $target);
        }
    }

    // ---------------------------------------------------------- the content
    //
    // What a template reads, typed. The text columns are fixed (cms_pages
    // text01–09, html01–09, label), so they are real properties: the IDE knows
    // every one, and nothing goes through __get. Each is a TextField — echo it
    // for the content (the editor in the CMS), ->plain for escaped text,
    // ->isVisible, ->value for the raw column. See TextField.

    public TextField $label { get => $this->texts->label; }
    public TextField $text01 { get => $this->texts->get('text01'); }
    public TextField $text02 { get => $this->texts->get('text02'); }
    public TextField $text03 { get => $this->texts->get('text03'); }
    public TextField $text04 { get => $this->texts->get('text04'); }
    public TextField $text05 { get => $this->texts->get('text05'); }
    public TextField $text06 { get => $this->texts->get('text06'); }
    public TextField $text07 { get => $this->texts->get('text07'); }
    public TextField $text08 { get => $this->texts->get('text08'); }
    public TextField $text09 { get => $this->texts->get('text09'); }
    public TextField $html01 { get => $this->texts->get('html01'); }
    public TextField $html02 { get => $this->texts->get('html02'); }
    public TextField $html03 { get => $this->texts->get('html03'); }
    public TextField $html04 { get => $this->texts->get('html04'); }
    public TextField $html05 { get => $this->texts->get('html05'); }
    public TextField $html06 { get => $this->texts->get('html06'); }
    public TextField $html07 { get => $this->texts->get('html07'); }
    public TextField $html08 { get => $this->texts->get('html08'); }
    public TextField $html09 { get => $this->texts->get('html09'); }

    /** The address inside the application — relative within the site, see Rewritepaths::$link. */
    public string $link { get => $this->rewritepaths->link; }

    /** The same address absolute. */
    public string $href { get => $this->rewritepaths->href; }

    /** The template of the page — '' when it has none (or no row in this language). */
    public string $templateName { get => (string) ($this->meta->templateName ?? ''); }

    /** The media, by group. */
    public Medias $medias {
        get => $this->mediasObj ??= new Medias($this);
    }

    /** The tags — data; the markup is a template's business. */
    public Tags $tags {
        get => $this->tagsObj ??= new Tags($this->scope, $this);
    }

    /** The page settings (cms_settings): ->isChecked('name'), ->value('name'), ->key('name'). */
    public \Systopic\Cms\Queries\SettingValues\SettingValues $settings {
        get => \Systopic\Cms\Queries\SettingValues\SettingValues::of($this->scope, $this->id);
    }

    private ?Medias $mediasObj = null;
    private ?Tags $tagsObj = null;

    // ------------------------------------------------------------- downwards

    /** @var list<Page> children, in `node_ordering` */
    public array $children {
        get {
            $level = $this->generation();
            return array_map($this->sibling(...), $level->childrenOf($this->node));
        }
    }

    /** @var list<Page> the same as pages — the surface 5.3 needs */
    public array $childPages {
        get {
            $pages = [];
            foreach ($this->children as $child) {
                $page = $child->row;
                if ($page !== null) {
                    $pages[] = $page;
                }
            }
            return $pages;
        }
    }

    public ?Page $firstChild {
        get => $this->children[0] ?? null;
    }

    public ?Page $lastChild {
        get {
            $children = $this->children;
            return $children === [] ? null : $children[count($children) - 1];
        }
    }

    /**
     * Does this node have children?
     *
     * The level query brings the answer along as a loose value
     * (`child_node_parent_node_id`), so the tree can draw its fold icon
     * without loading the children. Only when the node comes from a source
     * that does not say so does it look.
     */
    public bool $hasChildren {
        get => $this->generation()->hasChildren($this->node) ?? ($this->children !== []);
    }

    /**
     * The places where the same content hangs again.
     *
     * @var list<Node>
     */
    public array $aliases {
        get => $this->generation()->aliasesOf($this->node);
    }

    // --------------------------------------------------------------- sideways

    /** @var list<Page> siblings **including** this place, as in the legacy code */
    public array $siblings {
        get => array_map($this->sibling(...), $this->generation()->siblingsOf($this->node));
    }

    public ?Page $previous {
        get {
            $before = null;
            foreach ($this->siblings as $sibling) {
                if ($sibling === $this) {
                    return $before;
                }
                $before = $sibling;
            }
            return null;
        }
    }

    public ?Page $next {
        get {
            $seen = false;
            foreach ($this->siblings as $sibling) {
                if ($seen) {
                    return $sibling;
                }
                $seen = $sibling === $this;
            }
            return null;
        }
    }

    // --------------------------------------------------------------- upwards

    public ?Page $parent {
        get {
            $fromLevels = $this->parentFromGenerations();
            if ($fromLevels !== null) {
                return $fromLevels;
            }
            $chain = $this->ancestors;
            return $chain === [] ? null : $chain[count($chain) - 1];
        }
    }

    /** @var list<Page> ancestors, topmost first, without this place */
    public array $ancestors {
        get => $this->ancestorFamilies ??= $this->buildAncestors();
    }

    /** 1 for the topmost level — the same count as `page::getParent()`. */
    public int $level {
        get => count($this->ancestors) + 1;
    }

    /** The topmost place of this branch; level 1 is its own root. */
    public Page $root {
        get => $this->ancestors[0] ?? $this;
    }

    public bool $isRoot {
        get => $this->ancestors === [];
    }

    /** An alias sits at a second place in the tree and shows foreign content. */
    public bool $isAlias {
        get => ($this->node->relationId('originalNode') ?? 0) > 0;   // as it stands, see redirectsTo
    }

    /** Does this place lie on the way to that one? */
    public function isAncestorOf(Page $other): bool
    {
        foreach ($other->ancestors as $ancestor) {
            if ($ancestor === $this) {
                return true;
            }
        }
        return false;
    }

    // ---------------------------------------------------------------- inside

    /**
     * The level this node sits on.
     *
     * If it did not come from a loaded level — straight out of `pages.sql`,
     * say — it forms a level of its own. Its children then cost one statement,
     * and the next level is shared again.
     */
    private function generation(): PageTreeLevel
    {
        if ($this->generation === null) {
            PageTreeLevel::ofSingle($this->scope, $this->params, $this->node);
        }
        return $this->generation
            ?? throw new RuntimeException('Page without a generation — PageTreeLevel::fill() has not run');
    }

    /** Another page under the same parameters — a sibling, a child, a parent. */
    private function sibling(Node $node): self
    {
        return self::of($this->scope, $this->params, $node);
    }

    /** @internal a generation registers itself once it has loaded the node */
    public function placeIn(PageTreeLevel $generation): void
    {
        // The first level wins: it already holds loaded children, and swapping
        // would throw them away. A level that knows the node *with* its page
        // does beat one that only has it as a placeholder.
        if ($this->generation === null
            || (!$this->generation->knows($this->node) && $generation->knows($this->node))) {
            $this->generation = $generation;
        }
    }

    /** Without a statement: does the level know its parent level? */
    private function parentFromGenerations(): ?self
    {
        $level = $this->generation;
        $above = $level?->parentGeneration;
        if ($level === null || $above === null) {
            return null;
        }
        $parentId = $level->parentIdOf($this->node);
        foreach ($above->nodes() as $candidate) {
            if ($candidate->id === $parentId) {
                return $this->sibling($candidate);
            }
        }
        return null;
    }

    /** @return list<Page> */
    private function buildAncestors(): array
    {
        return $this->ancestorsFromGenerations() ?? $this->loadAncestors();
    }

    /**
     * The way up for as long as the levels know it.
     *
     * NULL means "does not reach the top" — then `loadAncestors()` fetches the
     * whole chain in one statement instead of piecing it half together.
     *
     * @return list<Page>|null
     */
    private function ancestorsFromGenerations(): ?array
    {
        $chain = [];
        $place = $this;
        $guard = 64;

        while ($guard-- > 0) {
            $level = $place->generation;
            if ($level === null) {
                return null;
            }
            if ($level->parentIdOf($place->node) === 0) {
                return array_reverse($chain);   // topmost level reached
            }
            $parent = $place->parentFromGenerations();
            if ($parent === null) {
                return null;
            }
            $chain[] = $parent;
            $place = $parent;
        }
        return null;
    }

    /**
     * The whole chain in one statement — and the levels out of it.
     *
     * @return list<Page>
     */
    private function loadAncestors(): array
    {
        $nodeId = $this->node->id;
        if ($nodeId === null) {
            return [];
        }
        [$ancestors, ] = PageTreeLevel::chainTo($this->scope, $this->params, $nodeId);
        return $ancestors;
    }
}
