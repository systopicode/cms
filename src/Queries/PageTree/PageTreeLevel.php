<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\PageTree;

use RuntimeException;
use Systopic\Db\Result\Completion;
use Systopic\Db\Rows;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\Cms\Tables\Pages\Model as PageRow;
use Systopic\Cms\Tables\Rewritepaths\Model as Rewritepath;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\Cms\Queries\Params;
use Systopic\Cms\Tables\Pages\Page;

/**
 * One level of the tree — and the place where a level comes into being in
 * **one** statement.
 *
 * Same idea as `LoadGroup`, one storey up. There it reads "not one query per
 * object, but one per missing column, for all objects"; here "not one query
 * per node, but one per missing level, for all nodes of that level".
 *
 * Measured on the artist collection (page 19, 9 letter groups, 50 artists,
 * 19 exhibitions):
 *
 *     legacy       15 statements   (one per node)
 *     PageTreeLevel    3 statements   (one per level)
 *
 * The price is stated honestly next to it: with `Completion::Group` the third
 * level fetches the children of **every** node on it, even though the template
 * renders five. That is what the policy is for — `Single` asks per node, as
 * before, and `Never` throws so a render loop can be checked.
 *
 * **The tree is made of nodes, not of pages.** That is not polish here, it is
 * the difference between right and wrong: this project has 180 alias nodes,
 * and an alias shares its page row with its original
 * (`page_node_id = IF(node_original_node_id=0, node_id, node_original_node_id)`).
 * Six nodes point at the page of node 41 alone. The identity map hands out the
 * **same** `Page` object for all seven — correctly, since it is one row — and
 * a level keyed by page loses nodes over it. The first attempt did exactly
 * that and counted 49 artists instead of 50.
 *
 * The legacy code gets around it by having `page::getChildren()` build a
 * separate `page` object per node carrying the original's record — the same
 * row several times in memory, with several sets of changes. That is what the
 * identity map forbids, and rightly so.
 */
final class PageTreeLevel
{
    /** @var list<Node> */
    private array $nodes = [];

    /** @var array<int, Page|null> spl_object_id(Node) => page of the same row */
    private array $pages = [];

    /**
     * @var array<int, array<string, mixed>> spl_object_id(Node) => loose values
     *
     * What belongs to no table: `child_node_parent_node_id`, the answer to
     * "does this node have children?" that the tree needs before it loads
     * them, and `alias_of`.
     */
    private array $loose = [];

    /** @var array<int, list<Node>> parent node id => child nodes */
    private array $childrenByParent = [];

    /** @var array<int, true> parent node ids whose children have been fetched */
    private array $loadedParents = [];

    private bool $levelLoaded = false;

    private ?PageTreeLevel $childGeneration = null;

    public function __construct(
        public readonly Scope $scope,
        public readonly Params $params,
        public readonly ?PageTreeLevel $parentGeneration = null,
    ) {}

    /**
     * The topmost level: everything hanging directly below the root.
     *
     * **A root carries `NULL`, not 0.** That used to be the other way round,
     * and the change is not cosmetic: 0 was a reference to a node that does
     * not exist, which is precisely what a foreign key refuses to accept. Once
     * `cms_nodes.node_parent_node_id` points at `cms_nodes.node_id` for real,
     * "no parent" can only be spelled NULL.
     *
     * Which is why this is not `under($scope, [0])` any more. `IN (…)` never
     * matches NULL — not even `IN (NULL)` — so asking for the top level with a
     * magic parent id returns nothing at all, silently. The query gets a flag
     * instead.
     */
    public static function top(Scope $scope, Params $params): self
    {
        return self::under($scope, $params, [], roots: true);
    }

    /**
     * The children of given nodes as a first level — the entry point when the
     * tree does not start at the root.
     *
     * @param list<int> $parentIds
     */
    public static function under(Scope $scope, Params $params, array $parentIds, bool $roots = false): self
    {
        // `0` is the old spelling for "the topmost level", and callers still
        // use it — `PageTree` has `$rootIds = [0]` as its default. Translating it
        // here rather than at each call site is what makes the whole thing
        // work under both conventions: `IN (0)` matches while the column still
        // holds 0, and the roots branch matches once it holds NULL. Neither
        // caller has to know which of the two the database is currently on.
        $roots = $roots || in_array(0, array_map('intval', $parentIds), true);

        $level = new self($scope, $params);
        [$nodes, $pages, $loose] = self::execute($scope, $params, '', [
            'parentIds' => $parentIds,
            'roots'     => $roots ? 1 : 0,
        ]);
        $level->fill($nodes, $pages, $loose);
        return $level;
    }

    /** A single node as a level of its own — the entry point from outside. */
    public static function ofSingle(Scope $scope, Params $params, Node $node, ?PageRow $page = null): self
    {
        $level = new self($scope, $params);
        $level->fill([$node], [spl_object_id($node) => $page]);
        return $level;
    }

    /**
     * Nodes by id — one statement for any number of them.
     *
     * @param list<int> $nodeIds
     */
    public static function ofNodes(Scope $scope, Params $params, array $nodeIds): self
    {
        $level = new self($scope, $params);
        if ($nodeIds === []) {
            return $level;
        }
        [$nodes, $pages, $loose] = self::execute($scope, $params, 'nodes', ['nodeIds' => $nodeIds]);
        $level->fill($nodes, $pages, $loose);
        return $level;
    }

    // ------------------------------------------------------------- contents

    /** @return list<Node> */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /** The page of a node — NULL when it does not exist in this language. */
    public function rowOf(Node $node): ?PageRow
    {
        return $this->pages[spl_object_id($node)] ?? null;
    }

    public function knows(Node $node): bool
    {
        return array_key_exists(spl_object_id($node), $this->pages);
    }

    /** The id of the parent node; 0 for the topmost level. */
    public function parentIdOf(Node $node): int
    {
        // The row reader only remembers a foreign key id when it is > 0, so
        // `node_parent_node_id = 0` means "topmost level" here, not "unknown".
        // `relationId()` rather than `refId()`: after `$node->parentNode = …`
        // the node belongs under its new parent, not the one it was loaded with.
        return $node->relationId('parentNode') ?? 0;
    }

    // --------------------------------------------------------- loose values

    /**
     * Does this node have children — without loading them?
     *
     * NULL means "the query did not say". That is something else than "no",
     * and the tree must not blur the difference: on NULL it has to look, on
     * FALSE it draws an empty box.
     */
    public function hasChildren(Node $node): ?bool
    {
        return $this->flag($node, 'child_node_parent_node_id');
    }

    /**
     * Are there aliases of this node — without loading them?
     *
     * The tree asks before it sends the alias statement: on the topmost level
     * usually nobody has any, and then there is nothing to fetch.
     */
    public function hasAliases(Node $node): ?bool
    {
        return $this->flag($node, 'alias_of');
    }

    /** NULL means "the query did not say" — which is not "no". */
    private function flag(Node $node, string $column): ?bool
    {
        $values = $this->loose[spl_object_id($node)] ?? null;
        if ($values === null || !array_key_exists($column, $values)) {
            return null;
        }
        return $values[$column] !== null;
    }

    // ---------------------------------------------------------------- levels

    /**
     * The children of a node on this level.
     *
     * @return list<Node>
     */
    public function childrenOf(Node $node): array
    {
        $key = $node->id;
        if ($key === null) {
            return [];
        }
        if (!$this->levelLoaded && !isset($this->loadedParents[$key])) {
            $this->loadFor($key);
        }
        return $this->childrenByParent[$key] ?? [];
    }

    /**
     * The siblings of a node — every node on this level with the same parent.
     * The node itself is included, as in the legacy code.
     *
     * @return list<Node>
     */
    public function siblingsOf(Node $node): array
    {
        $parent = $this->parentIdOf($node);
        $siblings = [];
        foreach ($this->nodes as $candidate) {
            if ($this->parentIdOf($candidate) === $parent) {
                $siblings[] = $candidate;
            }
        }
        return $siblings;
    }

    /** The level below — it comes into being on the first fetch. */
    public function children(): self
    {
        return $this->childGeneration ??= new self($this->scope, $this->params, $this);
    }

    /**
     * Fetch the children of exactly these nodes — one statement, without
     * dragging the whole level along.
     *
     * The pylon tree needs that: of seven nodes on the topmost level maybe two
     * are unfolded, and nobody cares about the children of the other five.
     * `Completion::Group` would take them along — right for a render loop,
     * wrong for a tree that knows exactly what it unfolds.
     *
     * @param list<int> $nodeIds
     */
    public function preload(array $nodeIds): void
    {
        $open = [];
        foreach ($nodeIds as $id) {
            if (!$this->levelLoaded && !isset($this->loadedParents[$id])) {
                $open[] = $id;
            }
        }
        if ($open !== []) {
            $this->loadParents($open);
        }
    }

    // --------------------------------------------------------------- loading

    private function loadFor(int $key): void
    {
        switch ($this->params->completion) {
            case Completion::Never:
                throw new RuntimeException(
                    'The children of node ' . $key . ' were not in the query — '
                    . 'Completion::Never forbids fetching them. Either load the level '
                    . 'up front or change the policy.'
                );

            case Completion::Single:
                $this->loadParents([$key]);
                return;

            case Completion::Group:
                $all = [$key => true];
                foreach ($this->nodes as $node) {
                    if ($node->id !== null) {
                        $all[$node->id] = true;
                    }
                }
                $this->loadParents(array_keys($all));
                $this->levelLoaded = true;
                return;
        }
    }

    /**
     * One statement for all named parents; the children land on the level
     * below.
     *
     * @param list<int> $parentIds
     */
    private function loadParents(array $parentIds): void
    {
        if ($parentIds === []) {
            return;
        }
        foreach ($parentIds as $id) {
            $this->loadedParents[$id] = true;
            $this->childrenByParent[$id] ??= [];
        }

        [$nodes, $pages, $loose] = self::execute($this->scope, $this->params, '', [
            'parentIds' => $parentIds,
            'roots'     => 0,
        ]);

        $below = $this->children();
        $below->fill($nodes, $pages, $loose);

        foreach ($nodes as $child) {
            $this->childrenByParent[$this->parentIdOf($child)][] = $child;
        }
    }

    /**
     * The one statement — and the place where the group a row came from is
     * remembered. `Scope::need()` later uses it to fetch a column the lean
     * query did not deliver.
     *
     * @return array{0: list<Node>, 1: array<int, Page|null>, 2: array<int, array<string, mixed>>}
     */
    private static function execute(Scope $scope, Params $params, string $file, array $placeholders): array
    {
        return self::run($scope, $params, self::queryFile($file), $placeholders);
    }

    /**
     * A level out of somebody else's query — `Page.sql`, for instance.
     *
     * The query has to deliver `cms_nodes` and may deliver `cms_pages`; it
     * runs through the scope's identity map, so a node the tree already holds
     * comes back as the same object, with whatever columns this query adds
     * filled into it (`RowReader::fillGaps()`).
     */
    public static function ofFile(Scope $scope, Params $params, string $path, array $placeholders): self
    {
        $level = new self($scope, $params);
        [$nodes, $pages, $loose] = self::run($scope, $params, $path, $placeholders);
        $level->fill($nodes, $pages, $loose);
        return $level;
    }

    /**
     * One statement through the scope — or the result it produced earlier
     * for the same file and placeholders.
     *
     * @return array{0: list<Node>, 1: array<int, PageRow|null>, 2: array<int, array<string, mixed>>}
     */
    private static function run(Scope $scope, Params $params, string $path, array $placeholders): array
    {
        self::register();
        $rows = $scope->rows($path, $params->params($placeholders), $params->completion);

        [$nodes, $pages] = self::split($rows->records());
        return [$nodes, $pages, self::looseByNode($rows)];
    }

    /**
     * The loose values sit parallel to the records; here they get the node as
     * their key so they can travel with it.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function looseByNode(Rows $rows): array
    {
        $loose = [];
        $values = $rows->looseValues();
        foreach ($rows->records() as $i => $record) {
            $node = $record[Node::class] ?? null;
            if ($node !== null) {
                $loose[spl_object_id($node)] = $values[$i] ?? [];
            }
        }
        return $loose;
    }

    /**
     * Pull nodes and their pages out of the records of one execution.
     *
     * Keyed by the **node**: two nodes may share a page (alias and original),
     * two pages never share a node.
     *
     * @return array{0: list<Node>, 1: array<int, Page|null>}
     */
    public static function split(array $records): array
    {
        $nodes = [];
        $pages = [];
        foreach ($records as $record) {
            $node = $record[Node::class] ?? null;
            if ($node === null) {
                continue;   // a LEFT JOIN without a hit is not a row
            }
            $nodes[] = $node;
            $pages[spl_object_id($node)] = $record[PageRow::class] ?? null;
        }
        return [$nodes, $pages];
    }

    /**
     * @param list<Node>                        $nodes
     * @param array<int, Page|null>             $pages
     * @param array<int, array<string, mixed>>  $loose
     * @internal a family assembles the chain of ancestors with this
     */
    public function fill(array $nodes, array $pages, array $loose = []): void
    {
        foreach ($nodes as $node) {
            if (!in_array($node, $this->nodes, true)) {
                $this->nodes[] = $node;
            }
        }
        $this->pages += $pages;
        $this->loose += $loose;
        foreach ($nodes as $node) {
            Page::of($this->scope, $this->params, $node)->placeIn($this);
        }
    }

    /**
     * Without these three the result map is blind: `getColumnMeta()` says
     * `cms_nodes`, and only the registry knows the way back to a class.
     */
    public static function register(): void
    {
        Registry::register(PageRow::class);
        Registry::register(Node::class);
        Registry::register(Rewritepath::class);
    }

    // ------------------------------------------------------------- ancestors

    /**
     * The chain from a node up to the topmost level — **one** statement.
     *
     * The recursive CTE returns the node itself as well; the levels are built
     * from it right here, one per step and each linked to the one above.
     * Looking upwards afterwards costs nothing.
     *
     * Going through `Scope::node()` and then `Page::$ancestors` cost two
     * statements: one to get the node at all, one for the chain. The first is
     * redundant — the chain contains it.
     *
     * @return array{0: list<Page>, 1: Page|null} ancestors (topmost first)
     *         and the place of the node itself
     */
    public static function chainTo(Scope $scope, Params $params, int $nodeId): array
    {
        self::register();
        $rows = $scope->rows(self::queryFile('ancestors'), $params->params(['node_id' => $nodeId]), $params->completion);

        [$nodes, $pages] = self::split($rows->records());
        if ($nodes === []) {
            return [[], null];
        }
        // The loose values travel along — otherwise an ancestor would not know
        // whether it has children, and the tree would load them to find out.
        $loose = self::looseByNode($rows);

        $ancestors = array_slice($nodes, 0, -1);
        $self      = $nodes[count($nodes) - 1];

        $above = null;
        foreach ([...$ancestors, $self] as $node) {
            $level = new self($scope, $params, $above);
            $level->fill(
                [$node],
                [spl_object_id($node) => $pages[spl_object_id($node)] ?? null],
                [spl_object_id($node) => $loose[spl_object_id($node)] ?? []],
            );
            $above = $level;
        }

        return [
            array_map(static fn(Node $n): Page => Page::of($scope, $params, $n), $ancestors),
            Page::of($scope, $params, $self),
        ];
    }

    // --------------------------------------------------------------- aliases

    /** @var array<int, list<Node>> original id => alias nodes */
    private array $aliases = [];

    private bool $aliasesLoaded = false;

    /**
     * The aliases of a node — the places where the same content hangs again.
     *
     * @return list<Node>
     */
    public function aliasesOf(Node $node): array
    {
        $id = $node->id;
        if ($id === null) {
            return [];
        }
        if (!$this->aliasesLoaded && !array_key_exists($id, $this->aliases)) {
            $this->loadAliases($id);
        }
        return $this->aliases[$id] ?? [];
    }

    private function loadAliases(int $nodeId): void
    {
        $ids = [$nodeId => true];
        if ($this->params->completion === Completion::Group) {
            foreach ($this->nodes as $node) {
                if ($node->id !== null) {
                    $ids[$node->id] = true;
                }
            }
            $this->aliasesLoaded = true;
        }
        $this->aliases = self::aliasesFor($this->scope, $this->params, array_keys($ids)) + $this->aliases;
    }

    /**
     * The aliases of any number of nodes in **one** statement.
     *
     * Kept apart from the level because the tree needs them for all its levels
     * at once: at the end of the build it knows exactly which nodes it draws,
     * and one statement for the whole tree beats one per level.
     *
     * @param  list<int> $nodeIds
     * @return array<int, list<Node>> original id => alias nodes
     */
    public static function aliasesFor(Scope $scope, Params $params, array $nodeIds): array
    {
        $aliases = [];
        foreach ($nodeIds as $id) {
            $aliases[$id] = [];
        }
        if ($nodeIds === []) {
            return $aliases;   // nothing to fetch, no statement
        }

        self::register();
        $rows = $scope->rows(self::queryFile('aliases'), [
            'originalIds' => $nodeIds,
            'showtrashed' => $params->showTrashed,
        ], $params->completion);

        foreach ($rows->all(Node::class) as $alias) {
            /** @var Node $alias */
            $original = $alias->relationId('originalNode'); // see loadRewritepaths()
            if ($original !== null) {
                $aliases[$original][] = $alias;
            }
        }
        return $aliases;
    }

    // ---------------------------------------------------------------- media

    /** @var array<int, list<\Systopic\System\Tables\Medias\Model>> content node id => its media */
    private array $medias = [];

    private bool $mediasLoaded = false;

    /**
     * The media below a node — for every node of the level in one statement.
     * An alias shows the media of its original (its content node).
     *
     * @return list<\Systopic\System\Tables\Medias\Model>
     */
    public function mediasOf(Node $node): array
    {
        $content = self::contentNodeIdOf($node);
        if ($content === null) {
            return [];
        }
        if (!$this->mediasLoaded && !array_key_exists($content, $this->medias)) {
            $ids = [$content];
            if ($this->params->completion === Completion::Group) {
                foreach ($this->nodes as $other) {
                    $id = self::contentNodeIdOf($other);
                    if ($id !== null) {
                        $ids[] = $id;
                    }
                }
                $this->mediasLoaded = true;
            }
            $this->medias += \Systopic\System\Queries\MediaNodes\MediaNodes::underParents(
                $this->scope, array_values(array_unique($ids)), $this->params->showTrashed, $this->params->completion,
            );
        }
        return $this->medias[$content] ?? [];
    }

    private static function contentNodeIdOf(Node $node): ?int
    {
        $original = $node->relationId('originalNode');
        return $original !== null && $original > 0 ? $original : $node->id;
    }

    // --------------------------------------------------------- rewritepaths

    /** @var array<int, list<Rewritepath>> page id => all its paths */
    private array $rewritepaths = [];

    private bool $rewritepathsLoaded = false;

    /**
     * All speaking paths of a page — again one statement per level.
     *
     * @return list<Rewritepath>
     */
    public function rewritepathsOf(Node $node): array
    {
        $page = $this->rowOf($node);
        if ($page === null || $page->id === null) {
            return [];
        }
        if (!$this->rewritepathsLoaded && !array_key_exists($page->id, $this->rewritepaths)) {
            $this->loadRewritepaths($page->id);
        }
        return $this->rewritepaths[$page->id] ?? [];
    }

    private function loadRewritepaths(int $pageId): void
    {
        $ids = [$pageId => true];
        if ($this->params->completion === Completion::Group) {
            foreach ($this->nodes as $node) {
                $page = $this->rowOf($node);
                if ($page?->id !== null) {
                    $ids[$page->id] = true;
                }
            }
            $this->rewritepathsLoaded = true;
        }
        foreach (array_keys($ids) as $id) {
            $this->rewritepaths[$id] ??= [];
        }

        $rows = $this->scope->rows(self::queryFile('rewritepaths'), ['pageIds' => array_keys($ids)], $this->params->completion);

        foreach ($rows->all(Rewritepath::class) as $path) {
            /** @var Rewritepath $path */
            // relationId, not refId: a path created earlier in this request
            // comes back as the object the identity map already holds, and
            // refId() only knows ids a query delivered - it filed the new path
            // under no page, and the sidebar showed the list without it.
            $pageOfPath = $path->relationId('page');
            if ($pageOfPath !== null) {
                $this->rewritepaths[$pageOfPath][] = $path;
            }
        }
    }

    /**
     * A path that came into being in this request joins the loaded ones.
     *
     * Only when the paths of its page have been loaded — otherwise the next
     * load reads it along with the rest.
     */
    public function attachRewritepath(int $pageId, Rewritepath $path): void
    {
        if (array_key_exists($pageId, $this->rewritepaths) && !in_array($path, $this->rewritepaths[$pageId], true)) {
            $this->rewritepaths[$pageId][] = $path;
        }
    }

    /** A path that was deleted leaves the loaded ones. */
    public function detachRewritepath(int $pageId, Rewritepath $path): void
    {
        if (array_key_exists($pageId, $this->rewritepaths)) {
            $this->rewritepaths[$pageId] = array_values(array_filter(
                $this->rewritepaths[$pageId],
                static fn(Rewritepath $p): bool => $p !== $path,
            ));
        }
    }

    /**
     * A query of this class: `queryFile()` for the level itself,
     * `queryFile('ancestors')` for one of the others.
     *
     * The convention of `Queries/*\/`: a view's query sits beside it and
     * carries its name — `PageTree.sql` —, further queries of the same
     * class add what they load: `PageTree.ancestors.sql`. What was
     * `PageTree.sql` is the main one here.
     */
    public static function queryFile(string $name = ''): string
    {
        return __DIR__ . '/PageTree' . ($name === '' ? '' : '.' . $name) . '.sql';
    }
}
