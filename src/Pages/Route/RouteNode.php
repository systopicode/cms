<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Route;

use Systopic\System\Client\ClientExport;
use Systopic\System\Client\Contracts\ClientExportable;
use Systopic\System\Dom\Contracts\RenderNode;
use Systopic\Cms\Pages\Navigator\PageNavigator;
use Systopic\Cms\Pages\Route\Route;
use Systopic\Cms\Tables\Pages\Page;

/**
 * One node of the page route — the pages counterpart of PanelNode.
 *
 * Where a PanelNode wraps a folder, a RouteNode wraps a page from the DB
 * (`Tables\Pages\Page`: cms_nodes + cms_pages). The tree it lives in is the
 * parent/child chain from cms_nodes, not a directory tree; everything else —
 * the with* helpers, updateView(), the DomLayer bookkeeping — behaves exactly
 * like a panel, so both share the same Dom layer stack and the same client.
 *
 * In a template `$this` is the RouteNode, and it is the only variable there:
 *
 *   $this->page                  the content (Tables\Pages\Page), null on root and site nodes
 *   $this->lang                  the request language
 *   $this->withSelected('main')  … eachChild(), withView(), withPage(): open and render
 *   $this->pageById(7)           another page's data, without opening it
 *   $this->fetchView('x', [...]) a view as a string, without a layer (ajax partials)
 *
 * A template that reads the content a lot starts with `$page = $this->page;`.
 */
#[\AllowDynamicProperties]
class RouteNode implements ClientExportable, RenderNode
{
    // -------------------------------------------------------------------------
    // Identity
    // -------------------------------------------------------------------------

    /**
     * The `page` record this node renders — NULL on the tree root.
     *
     * The root of the route is a node without a record: it has no id, no
     * language and no template, and its children are the pages whose
     * `node_parent_node_id` is NULL. Row 1 of cms_nodes / cms_pages, which
     * used to play that part, is a database default record and nothing here
     * reads it any more. isTreeRoot() tells the two apart.
     */
    public ?Page $page;

    /** Node ids from the route root down to this node. Used as the layer path. */
    public array $idPath = [];

    /** Nesting depth; root = 0. */
    public int $level = 0;

    // -------------------------------------------------------------------------
    // Tree relations
    // -------------------------------------------------------------------------

    public ?RouteNode $parent        = null;
    public ?RouteNode $root          = null;
    public ?RouteNode $childSelected = null;
    public ?RouteNode $next          = null;
    public ?RouteNode $prev          = null;

    /** @var RouteNode[] Children in cms_nodes.node_ordering order. */
    public array $children = [];

    /** Iteration pointer used by PageNavigator::openNextChild(). */
    public ?RouteNode $activeChild = null;

    public bool $childrenBuilt = false;

    // -------------------------------------------------------------------------
    // View / render state
    // -------------------------------------------------------------------------

    /** View names to re-render on this request (replaces route::scheduleUpdate). */
    public array $viewsToBeUpdated = [];

    /**
     * Views that must re-render on EVERY request, declared by the site's page
     * class. These are the chrome views whose content depends on the *selected*
     * page rather than on their own — head (title, canonical, hreflang), navi
     * (selected marker), footer (contact link).
     *
     * The legacy equivalent was a route::scheduleUpdate() call at the bottom of
     * head.tpl.php / navi.tpl.php / footer.tpl.php. That worked because the old
     * replay loop read the flag off the *previous* request's layer. DomLayer
     * checks it in confirm(), i.e. BEFORE the view renders, so declaring it in
     * the template body would always be one request too late.
     *
     * @var string[]
     */
    public array $alwaysUpdateViews = [];

    /** Folder the currently open view was resolved from (debug / RenderNode). */
    public string $activeViewFolder = '';

    // =========================================================================
    // Construction
    // =========================================================================

    /**
     * The node id of a node of type 'site' — set for a site node, which, like
     * the tree root, has no page record. See Pages\Sites\SiteRegistry.
     */
    public ?int $siteNodeId = null;

    public function __construct(?Page $page, ?RouteNode $parent = null, ?int $siteNodeId = null)
    {
        $this->page       = $page;
        $this->parent     = $parent;
        $this->siteNodeId = $page === null ? $siteNodeId : null;
        $this->root       = $parent !== null ? ($parent->root ?? $parent) : $this;
        $this->level      = $parent !== null ? $parent->level + 1 : 0;
        // Neither the root nor a site node has a page: 'root' and 'site<id>'
        // are their segments of every layer path.
        $segment = match (true) {
            $page !== null              => (string) $page->id,
            $this->siteNodeId !== null  => self::SITE_SEGMENT . $this->siteNodeId,
            default                     => self::ROOT_SEGMENT,
        };
        $this->idPath = $parent !== null ? [...$parent->idPath, $segment] : [$segment];
    }

    /** The layer-path segment of the tree root, which has no node id. */
    public const ROOT_SEGMENT = 'root';

    /** Prefix of a site node's layer-path segment: 'site743'. */
    public const SITE_SEGMENT = 'site';

    /** True for the root of the route — the node without a record. */
    public function isTreeRoot(): bool
    {
        return $this->page === null && $this->siteNodeId === null;
    }

    /** True for a node of type 'site' — the root of one site's pages. */
    public function isSiteNode(): bool
    {
        return $this->siteNodeId !== null;
    }

    /**
     * The site this node belongs to: a site node is its site, the tree root
     * is the default site, a page the site above it. Null without config/.
     */
    public function site(): ?\Systopic\System\Config\Site
    {
        if ($this->siteKnown) {
            return $this->siteCache;
        }
        $this->siteKnown = true;
        $sites = \Systopic\System\Config\Config::current()?->sites;
        if ($sites === null) {
            return $this->siteCache = null;
        }
        if ($this->siteNodeId !== null) {
            return $this->siteCache = $sites->byRoot($this->siteNodeId) ?? $sites->default();
        }
        if ($this->page === null) {
            return $this->siteCache = $sites->default();
        }
        // The copy the page's primary path carries — the same source as its
        // address (Rewritepaths::$link), no tree lookup. The route parent is
        // no help: withPage() hangs a node under the ACTIVE node, wherever
        // the page sits in the tree.
        $primary = $this->page->rewritepaths->primary;
        $siteNodeId = $primary !== null
            ? $primary->siteNodeId
            : \Systopic\Cms\Pages\Sites\NodeSite::siteNodeIdOf($this->page->id);
        return $this->siteCache = \Systopic\Cms\Pages\Sites\NodeSite::site($siteNodeId);
    }

    private bool $siteKnown = false;
    private ?\Systopic\System\Config\Site $siteCache = null;

    /** True when this node is the root its site's document opens on — a site node, or the tree root for the default site. */
    public function isSiteRoot(): bool
    {
        return $this->page === null;
    }

    // =========================================================================
    // Dom\Contracts\RenderNode
    // =========================================================================

    /**
     * What the diff compares. This is the legacy renderId minus its view
     * segment — the view is already the reference-map key, so repeating it
     * here would only give every lookup its own bucket.
     *
     * Legacy: "id=20&lang=de&tpl=galerie&view=main"
     * Here:   "id=20&lang=de&tpl=galerie"  (+ viewName on the layer)
     */
    public function getLayerIdentity(): string
    {
        $page = $this->page;
        if ($page === null) {
            return $this->siteNodeId !== null ? 'site=' . $this->siteNodeId : self::ROOT_SEGMENT;
        }
        return 'id=' . $page->id
             . '&lang=' . $page->language
             . '&tpl=' . $page->templateName;
    }

    /**
     * The same page, named the way a person can match it against the address
     * bar: its primary rewritepath, then the template.
     *
     *   ':home'                  the home page - its rewritepath is '', and
     *                            the empty first segment is the point: that
     *                            is what home looks like
     *   'ted_atkinson:kuenstler' an ordinary page
     *   '[1:*]'                  no rewritepath at all (the route root, or a
     *                            page that never got one) - node id and
     *                            language in brackets instead
     *
     * Free of charge and free of side effects: pages.sql already left-joins
     * the primary rewritepath, so the string sits in the record. Read from
     * there on purpose - the $page->rewritepath accessor CREATES and saves a
     * rewritepath for a page that has none, and a debug label must never
     * write to the database.
     */
    public function getDebugIdentity(): string
    {
        $page = $this->page;
        if ($page === null) {
            return $this->siteNodeId !== null
                ? 'site:' . ($this->site()?->name ?? $this->siteNodeId)
                : self::ROOT_SEGMENT;
        }
        $rwid   = $page->rewritepaths->string;
        $parts  = [$rwid ?? '[' . $page->id . ':' . $page->language . ']'];
        if ($page->templateName !== '') {
            $parts[] = $page->templateName;
        }
        return implode(':', $parts);
    }

    /** @return array<int, string> Node ids root→this. */
    public function getLayerPath(): array
    {
        return $this->idPath;
    }

    /**
     * Reference-map key for layers opened BY this node. Must be stable across
     * requests for the same slot — the node-id chain is, whereas the legacy
     * positional items[index] matching was not.
     */
    public function getAddress(): string
    {
        return 'page:' . implode('/', $this->idPath) . '/';
    }

    /** @return string[] */
    public function getViewsToBeUpdated(): array
    {
        return $this->viewsToBeUpdated;
    }

    public function getViewBasePath(): string
    {
        return $this->activeViewFolder;
    }

    /**
     * @see RenderNode::getRenderSource() — the cms page preview puts these
     * layers inside the PANEL tree, and this is what keeps the two apart.
     */
    public function getRenderSource(): string
    {
        return \Systopic\System\Dom\Layer\DomLayer::SOURCE_PAGE;
    }

    // =========================================================================
    // View updates
    // =========================================================================

    public function updateView(string $viewName = 'main'): void
    {
        if (!in_array($viewName, $this->viewsToBeUpdated, true)) {
            $this->viewsToBeUpdated[] = $viewName;
        }
    }

    /** @param string[] $viewNames */
    public function updateViews(array $viewNames = ['main']): void
    {
        foreach ($viewNames as $viewName) {
            $this->updateView($viewName);
        }
    }

    /**
     * Pages have no hidden-view mechanism (panels drive that off session
     * state). Kept so the navigator can treat both node kinds the same.
     */
    public function viewVisible(string $viewName): bool
    {
        return true;
    }

    // =========================================================================
    // Template rendering
    // =========================================================================

    /**
     * Includes a template with `$this` as the only variable in scope.
     *
     * No `$page`, no `$lang`, no `$GLOBALS['page']` any more: the content is
     * `$this->page`, the request language `$this->lang`. A template that reads
     * the content a lot starts with `$page = $this->page;`. `$vars` become
     * variables of the template — for what a parent template hands down
     * (1100_team passes the counter of its members).
     *
     * @param array<string, mixed> $vars
     */
    public function render(string $file, array $vars = []): void
    {
        // No view folder means the open was inline - a page without a
        // template (PageNavigator::openNode) - so the working directory was
        // never moved to a template folder. The relative include below would
        // then resolve against the CALLER's folder and pull in ITS file of
        // that name: the cms page preview used to re-include its own
        // main.tpl.php that way and die on the $module it does not get.
        // An inline open is for callers that render the page data themselves.
        if ($this->activeViewFolder === '') {
            \logroute('RouteNode::render without an open view', [
                'file' => $file,
                'page' => $this->page?->id,
                'cwd'  => \fs::cwd(),
            ]);
            return;
        }
        (function () use ($file, $vars) {
            extract($vars, EXTR_SKIP);
            include $file;
        })->call($this);
    }

    /**
     * A view of this node as a string — resolved like any view (the page's
     * template folder, the site's, the shared ones), but without opening a
     * layer. For partials an ajax action replaces in place (client::replaceInner).
     * `$vars` become variables of the template, `$this` is this node.
     *
     * @param array<string, mixed> $vars
     */
    public function fetchView(string $viewName, array $vars = []): string
    {
        $hit = self::$tree?->getViewResolver()->resolve($this, $viewName);
        if ($hit === null) {
            \logroute('RouteNode::fetchView: no template', ['view' => $viewName, 'page' => $this->page?->id]);
            return '';
        }
        ob_start();
        try {
            (function () use ($hit, $vars) {
                extract($vars, EXTR_SKIP);
                include \fs::toNative($hit->file);
            })->call($this);
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    /** The request language — what every page of the route is read in. */
    public string $lang {
        get => self::$tree?->getLanguage() ?? (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'de');
    }

    /** Another page's data by node id — read like the route, not opened. */
    public function pageById(int $id, ?string $lang = null): ?Page
    {
        return self::$tree?->page($id, $lang);
    }

    /**
     * The activated languages of the installation, iso => definition
     * (`label`, `code`) — for hreflang links and language switches.
     *
     * @return array<string, array{label: string, code: string, activated: bool}>
     */
    public function languages(): array
    {
        return \Systopic\Cms\Tables\Pages\Languages::activated();
    }

    // =========================================================================
    // Closure-based navigation — open / run fn / auto-close
    // =========================================================================

    private static ?PageNavigator $nav  = null;
    private static ?Route      $tree = null;

    public static function setNavigator(PageNavigator $navigator): void
    {
        self::$nav = $navigator;
    }

    public static function getNavigator(): ?PageNavigator
    {
        return self::$nav;
    }

    public static function setTree(Route $tree): void
    {
        self::$tree = $tree;
    }

    public static function getTree(): ?Route
    {
        return self::$tree;
    }

    /**
     * Shared runner for all with* helpers.
     *
     * As in PanelNode, the closure runs in every mode including diff — an
     * 'alias' layer still has to be walked, because the actual change may sit
     * further down the tree. That is the legacy 'stay' semantics.
     */
    private function runWith(bool $opened, callable $fn): bool
    {
        if (!$opened) {
            return false;
        }
        try {
            $fn(self::$nav?->getActive());
        } finally {
            self::$nav?->close();
        }
        return true;
    }

    /**
     * Opens a view for THIS node's page and renders it.
     *   Legacy: if (route::openView('footer')) { include 'footer.tpl.php'; route::close(); }
     */
    public function withView(string $viewName, ?callable $fn = null): bool
    {
        $fn ??= fn($p) => $p->render("{$viewName}.tpl.php");
        return $this->runWith(self::$nav?->openView($viewName) ?? false, $fn);
    }

    /**
     * Opens the URL-selected page.
     *   Legacy: if (route::openSelected()) { include 'main.tpl.php'; route::close(); }
     */
    public function withSelected(string $viewName = 'main', ?callable $fn = null): bool
    {
        $fn ??= fn($p) => $p->render("{$viewName}.tpl.php");
        return $this->runWith(self::$nav?->openSelected($viewName) ?? false, $fn);
    }

    /**
     * Opens a view on whichever node owns it — the selected page's template,
     * the site's branch root, or the tree root (PageNavigator::openRenderHost).
     * document.tpl.php opens head and body this way.
     */
    public function withRenderHost(string $viewName, ?callable $fn = null): bool
    {
        $fn ??= fn($p) => $p->render("{$viewName}.tpl.php");
        return $this->runWith(self::$nav?->openRenderHost($viewName) ?? false, $fn);
    }

    /** Opens the route root — the node without a record; site.php opens the document view on it. */
    public function withRoot(string $viewName, ?callable $fn = null): bool
    {
        $fn ??= fn($p) => $p->render("{$viewName}.tpl.php");
        return $this->runWith(self::$nav?->openRoot($viewName) ?? false, $fn);
    }

    /** Opens the child of this node that lies on the selected route. */
    public function withChildSelected(string|false $viewName = 'main', ?callable $fn = null): bool
    {
        if ($fn === null && is_string($viewName)) {
            $view = $viewName;
            $fn   = fn($p) => $p->render("{$view}.tpl.php");
        }
        return $this->runWith(
            self::$nav?->openChildSelected($viewName) ?? false,
            $fn ?? fn($p) => null,
        );
    }

    /**
     * Opens an arbitrary page by id, optionally with a foreign view.
     *   Legacy: if (route::openPage(22, $lang, 'footernavi')) { … }
     *
     * The view is resolved through ViewResolver, so a site-wide
     * footernavi.tpl.php wins over whatever template the DB assigns to node 22.
     */
    public function withPage(
        int             $id,
        ?string         $lang = null,
        string|false    $viewName = 'main',
        ?callable       $fn = null,
    ): bool {
        if ($fn === null && is_string($viewName)) {
            $view = $viewName;
            $fn   = fn($p) => $p->render("{$view}.tpl.php");
        }
        return $this->runWith(
            self::$nav?->openPage($id, $lang, $viewName) ?? false,
            $fn ?? fn($p) => null,
        );
    }

    /**
     * Iterates the child pages.
     *
     * $viewName = false is the legacy inline mode (route::openNextChild(FALSE)):
     * no template, no directory change — the caller renders the child's data
     * with its own markup. That is how navi.tpl.php and footernavi.tpl.php work.
     */
    public function eachChild(callable $fn, string|false $viewName = false): void
    {
        if (self::$nav === null) {
            return;
        }
        $this->ensureChildrenBuilt();
        while (self::$nav->openNextChild($viewName)) {
            try {
                $fn(self::$nav->getActive());
            } finally {
                self::$nav->close();
            }
        }
    }

    // =========================================================================
    // Tree helpers
    // =========================================================================

    public function ensureChildrenBuilt(): void
    {
        if ($this->childrenBuilt) {
            return;
        }
        $this->childrenBuilt = true;
        self::$tree?->ensureChildrenBuilt($this);
    }

    /** True when this node lies on the selected route. */
    public function inPath(): bool
    {
        return self::$tree?->isInPath($this) ?? false;
    }

    public function isSelected(): bool
    {
        return self::$tree !== null && $this === self::$tree->getSelected();
    }

    // =========================================================================
    // Lifecycle hooks — override in a project page class
    // =========================================================================

    /** Called on every node of the route after the chain is built. */
    public function onLoad() {}

    /** Called only on the deepest (URL-matched) node. */
    public function onLoadSelected() {}

    // =========================================================================
    // Client data export
    // =========================================================================

    public function exportClientData(): ClientExport
    {
        return new ClientExport(
            route: 'pages.' . implode('.', $this->idPath),
            data: (object) [
                'id'       => $this->page?->id,
                'lang'     => $this->page?->language,
                'template' => $this->page?->templateName ?? '',
                'label'    => (string) ($this->page?->label?->value ?? ''),
                'level'    => $this->level,
                'inPath'   => $this->inPath(),
                'selected' => $this->isSelected(),
            ],
        );
    }

    // =========================================================================
    // Debug
    // =========================================================================

    public function debugInfo(): array
    {
        return [
            'identity'      => $this->getLayerIdentity(),
            'address'       => $this->getAddress(),
            'label'         => (string) ($this->page?->label?->value ?? '[]'),
            'level'         => $this->level,
            'template'      => ($this->page?->templateName ?? '') ?: '[]',
            'viewFolder'    => $this->activeViewFolder ?: '[]',
            'children'      => count($this->children),
            'childSelected' => $this->childSelected?->page?->id ?? '[]',
            'viewsToUpdate' => implode(', ', $this->viewsToBeUpdated) ?: '[]',
        ];
    }
}
