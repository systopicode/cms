<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Route;

use Systopic\System\Debug\BoxTree;
use Systopic\System\Dom\Contracts\RenderNode;
use Systopic\System\Dom\Contracts\RenderTree;
use Systopic\Cms\Pages\Route\RouteBuilder;
use Systopic\Cms\Pages\Builder\ViewResolver;
use Systopic\Cms\Pages\Route\RouteNode;
use Systopic\Cms\Pages\PageRootFolder;
use Systopic\Db\Scope;
use Systopic\System\Auth\Session;
use Systopic\Cms\Queries\Params;
use Systopic\Cms\Tables\Pages\Page;

/**
 * Central per-request object for the pages renderer — counterpart of PanelTree.
 *
 * Holds the route (root→selected), the registered view roots and the view
 * resolver. Replaces all static state that lived on the `route` class.
 */
class Route implements RenderTree
{
    private RouteBuilder  $builder;
    private ViewResolver $views;

    /** @var RouteNode[] Route root→selected. */
    private array $nodes = [];

    private ?RouteNode $rootNode     = null;
    private ?RouteNode $selectedNode = null;

    /** Node ids root→selected — the legacy route::$ids. */
    private array $ids = [];

    private bool $built = false;

    /** The request language — every page of the route is read in it. */
    private string $lang = 'de';

    /** What the pages are read under: language, preview, links into the CMS or the site. */
    private ?Params $params = null;

    public function __construct()
    {
        $this->builder = new RouteBuilder();
        $this->views   = new ViewResolver();
    }

    // =========================================================================
    // Singleton access (legacy code reaches the tree without DI)
    // =========================================================================

    private static ?Route $instance = null;

    public static function setInstance(Route $tree): void
    {
        self::$instance = $tree;
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    // =========================================================================
    // Roots
    // =========================================================================

    public function addRoot(PageRootFolder $root): void
    {
        $this->views->addRoot($root);
    }

    /** @return PageRootFolder[] */
    public function getRoots(): array
    {
        return $this->views->getRoots();
    }

    public function getViewResolver(): ViewResolver
    {
        return $this->views;
    }

    public function getBuilder(): RouteBuilder
    {
        return $this->builder;
    }

    /**
     * Installs the project's RouteNode subclass (templates/route.php). Everything on
     * the route is then built from that class, so $alwaysUpdateViews and the
     * lifecycle hooks are project-controlled.
     */
    public function setNodeClass(string $class): void
    {
        $this->builder->setNodeClass($class);
    }

    // =========================================================================
    // Build
    // =========================================================================

    /**
     * Builds the route for the addressed page and runs the after-build hooks.
     *
     * $alwaysUpdateViews is applied here — before any layer is opened — because
     * DomLayer::checkUpdate() is consulted in the navigator's confirm(), which
     * runs at open time. Declaring the update inside the view template (as the
     * legacy route::scheduleUpdate() did) would take effect one request late.
     */
    public function build(int|string|null $id, ?string $lang): void
    {
        $this->lang   = $lang !== null && $lang !== '' && $lang !== '*'
            ? $lang
            : (\app::request()->lang ?: (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'de'));
        $this->params = self::paramsFor($this->lang);
        $this->nodes        = $this->builder->buildRoute(Scope::default(), $this->params, (int) $id);
        $this->built        = true;
        $this->rootNode     = $this->nodes[0] ?? null;
        $this->selectedNode = $this->nodes !== [] ? end($this->nodes) : null;

        // The root has no id; the ids are those of the pages on the route.
        $this->ids = [];
        foreach ($this->nodes as $node) {
            if ($node->page !== null) {
                $this->ids[] = $node->page->id;
            }
        }

        foreach ($this->nodes as $node) {
            $node->updateViews($node->alwaysUpdateViews);
            $node->onLoad();
        }
        $this->selectedNode?->onLoadSelected();
    }

    public function isBuilt(): bool
    {
        return $this->built;
    }

    public function ensureChildrenBuilt(RouteNode $parent): void
    {
        $this->builder->buildChildren($parent, Scope::default(), $this->getParams());
    }

    /**
     * The parameters pages of this request are read under.
     *
     * Preview in the CMS (and for an editor with ?preview): unpublished pages
     * too. In the CMS, links point into the page panel, as the legacy
     * page::getLink() did. Only page nodes — media and setting nodes hang in
     * the same tree.
     */
    public static function paramsFor(string $lang): Params
    {
        $inCms = \Systopic\System\Panels\Tree\PanelTree::getInstance()?->inCms() ?? false;
        $request = \app::request();
        return new Params(
            lang: $lang,
            preview: $inCms || $request->preview || (bool) $request->showeditableareas,
            showTrashed: $request->showtrashed,
            root: (string) \http::$root,
            siteUrl: (string) \http::$siteUrl,
            inCms: $inCms,
            nodeType: 'page',
            readAcls: \Systopic\Cms\Pages\PageAccess::readAcls(),
            owner: (int) Session::id(),
        );
    }

    public function getParams(): Params
    {
        return $this->params ??= self::paramsFor($this->lang);
    }

    public function getLanguage(): string
    {
        return $this->lang;
    }

    /**
     * Any page by node id, read like the route — for a template that needs
     * another page's data (a link, a label) without opening it.
     */
    public function page(int $id, ?string $lang = null): ?Page
    {
        $params = $this->getParams();
        if ($lang !== null && $lang !== '' && $lang !== $params->lang) {
            $params = $params->in($lang);
        }
        $page = Page::byId(Scope::default(), $params, $id);
        return $page?->row === null ? null : $page;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    public function getRoot(): ?RouteNode
    {
        return $this->rootNode;
    }

    public function getSelected(): ?RouteNode
    {
        return $this->selectedNode;
    }

    /** @return RouteNode[] */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * Node ids root→selected. Replaces route::$ids, which tree.php and the
     * client-side naviPath setting consume.
     *
     * @return int[]
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    public function isInPath(RouteNode $node): bool
    {
        if ($node->page === null) {
            return true; // the root is on every route
        }
        return in_array($node->page->id, $this->ids, true);
    }

    /**
     * CSS classes for <body>. The legacy site.php used the selected page's
     * template name plus editing flags; the flags stay in site.php, the
     * template name comes from here.
     *
     * @return string[]
     */
    public function getBodyClasses(): array
    {
        $selected = $this->selectedNode;
        if ($selected === null || $selected->page === null) {
            return [];
        }
        $name    = $selected->page->templateName;
        $classes = $name !== '' ? [$name] : [];
        // Multi-site: which site is being served, for css that differs per site.
        $site = \Systopic\System\Config\Config::current()?->site();
        if ($site !== null) {
            $classes[] = 'site-' . $site->slug();   // beautiful-technology.de → site-beautiful-technology-de
        }
        return $classes;
    }

    /**
     * The node the served site hangs off: the route node that is the site's
     * branch root (config/, `sites.*.root`), or the tree root for the site
     * that is the whole tree. Null before build().
     */
    public function getSiteRoot(): ?RouteNode
    {
        // the site node on the route — the route of a page lies in one site
        foreach ($this->nodes as $node) {
            if ($node->isSiteNode()) {
                return $node;
            }
        }
        return $this->rootNode;
    }

    // =========================================================================
    // Dom\Contracts\RenderTree
    // =========================================================================

    /**
     * Resolves the node a DomLayer belonged to from its node-id chain.
     *
     * Called on the diff path for layers restored from the session, whose
     * object references were dropped by DomLayer::__sleep(). The route chain
     * is checked first (that covers every chrome layer); anything below it is
     * resolved by walking children, building them on demand.
     *
     * @param array<int, string|int> $path
     */
    public function findByLayerPath(array $path): ?RenderNode
    {
        if ($path === [] || $this->rootNode === null) {
            return null;
        }
        $path = array_map('strval', $path);

        // Fast path: the layer belongs to a node that is on the route.
        foreach ($this->nodes as $node) {
            if ($node->getLayerPath() === $path) {
                return $node;
            }
        }

        // Otherwise walk down from the root, materialising children as needed.
        // The first segment is the root's, and the root has no node id; a
        // site node ('site<id>') is only known when it is on the route.
        $node = $this->rootNode;
        if ($path[0] !== RouteNode::ROOT_SEGMENT) {
            return null;
        }
        if (isset($path[1]) && str_starts_with($path[1], RouteNode::SITE_SEGMENT)) {
            $site = $this->nodes[1] ?? null;
            if ($site === null || ($site->getLayerPath()[1] ?? null) !== $path[1]) {
                return null;
            }
            $node = $site;
            $path = [$path[0], ...array_slice($path, 2)];
        }

        for ($i = 1; $i < count($path); $i++) {
            $node->ensureChildrenBuilt();
            $wanted = (int) $path[$i];
            $next   = null;
            foreach ($node->children as $child) {
                if ($child->page?->id === $wanted) {
                    $next = $child;
                    break;
                }
            }
            if ($next === null) {
                return null;
            }
            $node = $next;
        }

        return $node;
    }

    // =========================================================================
    // Client data export
    // =========================================================================

    /** Static entry point for the legacy clientInterfaces registry. */
    public static function clientData_export(): array
    {
        return self::$instance?->exportClientData() ?? [];
    }

    /** @return \Systopic\System\Client\ClientExport[] */
    public function exportClientData(): array
    {
        $exports = [];
        foreach ($this->nodes as $node) {
            $exports[] = $node->exportClientData();
        }
        return $exports;
    }

    // =========================================================================
    // Debug
    // =========================================================================

    /**
     * The route — root down to the selected page — as box-tree data
     * (Debug\BoxTree): one box per node, side by side.
     *
     * @return list<array<string, mixed>>
     */
    public function debug(): array
    {
        $roots = [];
        foreach ($this->nodes as $node) {
            $roots[] = BoxTree::node($node->getLayerIdentity(), $node->debugInfo(), [
                'page',
                $node === $this->selectedNode ? 'selected' : null,
                $node->childSelected !== null ? 'inPath' : null,
            ]);
        }
        return $roots;
    }
}
