<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Navigator;

use Systopic\System\Dom\Layer\DomLayer;
use Systopic\System\Dom\Renderer\DomRenderer;
use Systopic\System\Dom\Tree\DomLayerTree;
use Systopic\Cms\Pages\Builder\ViewHit;
use Systopic\Cms\Pages\Route\RouteNode;
use Systopic\Cms\Pages\Route\Route;

/**
 * Drives page rendering — the pages counterpart of PanelNavigator.
 *
 * Replaces the whole route::open* family together with the __callStatic
 * instrumentation seam that wrapped it. In the legacy design the renderer
 * hooked itself into every call via onBeforeCall/onAfterCall, and the diff
 * ran later as a separate replay loop over the recorded tree
 * (renderPagesDiff.php). Here the comparison happens inline in confirm(),
 * exactly as it does for panels — one walk, no replay.
 *
 * Every opener follows the same shape:
 *   1. resolve the target node and its view template
 *   2. on failure → cancel(): emit a placeholder layer, return false
 *   3. on success → create the layer, push the node, chdir, confirm()
 *
 * close() undoes the last open: closes the layer, pops the dir stack, pops
 * the node stack.
 *
 * There is no $GLOBALS['page'] any more: a template reads $this->page.
 * (Was: the navigator owned $GLOBALS['page']. Legacy templates and the page helper
 * classes (pageText, medias, app::site()) read that global, and route::setActive()
 * / route::close() maintained it the same way.
 */
class PageNavigator
{
    /** @var RouteNode[] Stack of nodes that have been opened. */
    private array $nodeStack = [];

    private ?RouteNode $activeNode = null;

    /** Last open* attempt — surfaced by debugInfo() when a render produced nothing. */
    private ?array $lastRequest = null;

    public function __construct(
        private readonly Route     $tree,
        private readonly DomLayerTree $layerTree,
        private readonly DomRenderer  $domRenderer,
    ) {}

    // =========================================================================
    // Active node
    // =========================================================================

    public function setActive(RouteNode $node): void
    {
        $this->activeNode = $node;
    }

    public function getActive(): ?RouteNode
    {
        return $this->activeNode;
    }

    // =========================================================================
    // Openers
    // =========================================================================

    /**
     * Opens the route root — the node without a record — with the given view.
     * site.php uses openRoot('document') as its single canonical entry point,
     * for the same reason app.php uses openRoot('body'): one opener means one
     * layer shape, so diff comparisons stay positionally aligned.
     */
    public function openRoot(string $viewName): bool
    {
        $root = $this->tree->getRoot();
        if ($root === null) {
            return $this->cancel('openRoot', [$viewName], 'no route root — Route::build() did not run');
        }
        return $this->openNode('openRoot', [$viewName], $root, $viewName);
    }

    /** Opens the URL-selected page. Legacy: route::openSelected(). */
    public function openSelected(string|false $viewName = 'main'): bool
    {
        $selected = $this->tree->getSelected();
        if ($selected === null) {
            return $this->cancel('openSelected', [$viewName], 'no selected page');
        }
        return $this->openNode('openSelected', [$viewName], $selected, $viewName, true);
    }

    /**
     * Opens a view on the node that OWNS it — the render host.
     *
     * Walks from the selected page up the route: the first node that has the
     * view as its own (ViewResolver::resolveOwn — its template folder, or the
     * site's pages/<site>/ when the node is the site's branch root) hosts it.
     * Nobody on the way up → the tree root, with the shared roots.
     *
     * The pages counterpart of PanelNavigator::findRenderRoot(). It restores
     * the legacy `template->hasBody` rule (a template shipping body.tpl.php
     * replaces the whole body — full-screen views) and gives a site its own
     * head and body. The layer is registered under the caller's address and
     * the view name, so the slot is stable no matter who hosts it; a change
     * of host between two requests is a changed layer identity, and the diff
     * re-renders the slot.
     */
    public function openRenderHost(string $viewName): bool
    {
        $root = $this->tree->getRoot();
        if ($root === null) {
            return $this->cancel('openRenderHost', [$viewName], 'no route root — Route::build() did not run');
        }
        // The document always opens on the site root — the site node, or the
        // tree root for the default site — unless a page on the way up ships
        // the view in its own template folder (full-screen views).
        $siteRoot = $this->tree->getSiteRoot() ?? $root;
        $resolver = $this->tree->getViewResolver();
        for ($node = $this->tree->getSelected(); $node !== null && $node->page !== null; $node = $node->parent) {
            if ($resolver->resolveOwn($node, $viewName) !== null) {
                return $this->openNode('openRenderHost', [$viewName], $node, $viewName);
            }
        }
        return $this->openNode('openRenderHost', [$viewName], $siteRoot, $viewName);
    }

    /**
     * Opens another view of the ACTIVE page — the node does not change.
     * Legacy: route::openView().
     *
     * Unlike the panel opener of the same name this does not require the file
     * to sit in the current directory: the view is resolved through the
     * registered roots, so body.tpl.php in pages/ can be opened while the CWD
     * is inside a template folder.
     */
    public function openView(string $viewName): bool
    {
        if ($this->activeNode === null) {
            return $this->cancel('openView', [$viewName], 'no active page');
        }

        return $this->openNode('openView', [$viewName], $this->activeNode, $viewName);
    }

    /**
     * Opens an arbitrary page by node id.
     * Legacy: route::openPage($id, $lang, $view) — e.g. the footer pulling in
     * node 22 with the site-wide footernavi view.
     */
    public function openPage(int $id, ?string $lang = null, string|false $viewName = 'main'): bool
    {
        // The root has no page and no language; then the request language applies.
        $page = $this->tree->page($id, $lang);
        if ($page === null) {
            return $this->cancel('openPage', [$viewName], "page {$id}/" . ($lang ?? $this->tree->getLanguage()) . ' not available');
        }

        $class = $this->tree->getBuilder()->getNodeClass();
        /** @var RouteNode $node */
        $node = new $class($page, $this->activeNode);

        return $this->openNode('openPage', [$viewName], $node, $viewName, true);
    }

    /** Opens the child of the active page that lies on the selected route. */
    public function openChildSelected(string|false $viewName = 'main'): bool
    {
        if ($this->activeNode === null) {
            return $this->cancel('openChildSelected', [$viewName], 'no active page');
        }
        $child = $this->activeNode->childSelected;
        if ($child === null) {
            return $this->cancel('openChildSelected', [$viewName], 'active page has no selected child');
        }
        return $this->openNode('openChildSelected', [$viewName], $child, $viewName, true);
    }

    /** Opens the first child page and starts the iteration. */
    public function openFirstChild(string|false $viewName = 'main'): bool
    {
        if ($this->activeNode === null) {
            return $this->cancel('openFirstChild', [$viewName], 'no active page');
        }
        $this->activeNode->ensureChildrenBuilt();
        if ($this->activeNode->children === []) {
            return $this->cancel('openFirstChild', [$viewName], 'active page has no children');
        }

        $first = reset($this->activeNode->children);
        $this->activeNode->activeChild = $first;

        return $this->openNode('openFirstChild', [$viewName], $first, $viewName, true);
    }

    /**
     * Advances the child iterator. Returns false at the end of the list, which
     * is what terminates the `while (openNextChild(...))` loops in navigation
     * templates.
     */
    public function openNextChild(string|false $viewName = 'main'): bool
    {
        if ($this->activeNode === null) {
            return $this->cancel('openNextChild', [$viewName], 'no active page');
        }
        $this->activeNode->ensureChildrenBuilt();

        $current = $this->activeNode->activeChild;
        if ($current === null) {
            return $this->openFirstChild($viewName);
        }
        if ($current->next === null) {
            $this->activeNode->activeChild = null;
            return $this->cancel('openNextChild', [$viewName], 'reached end of children');
        }

        $next = $current->next;
        $this->activeNode->activeChild = $next;

        return $this->openNode('openNextChild', [$viewName], $next, $viewName, true);
    }

    // =========================================================================
    // Shared open implementation
    // =========================================================================

    /**
     * The one place an open actually happens.
     *
     * Two things make an open "inline" — no template is resolved and the
     * working directory stays put, so the caller renders the opened page's
     * data with its own markup:
     *
     *  1. $viewName === false — the caller asked for it explicitly
     *     (navi.tpl.php, footernavi.tpl.php).
     *  2. $inlineWithoutTemplate and the page has no template assigned in the
     *     DB. This is route::setActive()'s `!$routeNode->page->template`
     *     branch and it is load-bearing, not a wart: container pages such as
     *     the year groups under "Ausstellungen" or the letter groups under
     *     "Künstler" carry no template, and the nested loops in
     *     100_ausstellungen / 200_kuenstler_collection rely on opening them
     *     successfully in order to reach their children. Without it those
     *     opens would cancel and the collections would render empty.
     *
     * Node-changing openers pass $inlineWithoutTemplate = true; the render
     * hosts (openRoot / openView) pass false, because a missing host template
     * really is a failure.
     *
     * The layer is created either way — the slot has to exist so the diff can
     * compare it — an inline open just carries an empty view name.
     */
    private function openNode(
        string       $method,
        array        $args,
        RouteNode     $node,
        string|false $viewName,
        bool         $inlineWithoutTemplate = false,
    ): bool {
        $hit = null;
        $rel = '';

        // Read the template into a local FIRST. `$page->template ?? null` would
        // be wrong: page::__get() is a magic accessor and the ?? operator
        // resolves through __isset(), which page/record do not implement for
        // virtual properties — so it yields null without ever calling __get(),
        // and every page would look template-less.
        // a page whose template folder does not exist counts as one without a template
        $hasTemplate = $this->tree->getViewResolver()->templateFolderOf($node) !== null;
        $inline      = $viewName === false || ($inlineWithoutTemplate && !$hasTemplate);

        if (!$inline) {
            $hit = $this->tree->getViewResolver()->resolve($node, $viewName);
            if ($hit === null) {
                return $this->cancel($method, $args, "no template for view '{$viewName}'", $node);
            }
            $rel = $this->relativePath($hit->folder);
        }

        $this->createLayer($method, $args);
        $this->nodeStack[] = $this->activeNode;
        $this->activeNode  = $node;
        $node->activeViewFolder = $hit?->folder ?? '';

        // openDir('') pushes an empty stack entry without touching the CWD —
        // the inline-mode equivalent of route::setActive()'s FALSE branch.
        \fs::openDir($rel);

        return $this->confirm($method, $args, $node, $hit);
    }

    /**
     * Public wrapper around relativePath(), for callers that need to
     * fs::openDir() a folder the resolver handed them (site.php does this for
     * a template's config.php / actions.php).
     */
    public function relativeTo(string $folder): string
    {
        return $this->relativePath($folder);
    }

    /**
     * Returns a CWD-relative path (always ending in '/') to $folder.
     *
     * Same approach as PanelNavigator::relativeOpenPath(): the path must be
     * relative because fs::closeDir() unwinds by segment count, and it is
     * computed by string prefix rather than realpath() so it stays correct
     * across the vendor/systopic/system junction on Windows.
     */
    private function relativePath(string $folder): string
    {
        $cwd    = rtrim(\fs::toInternal(\getcwd()), '/') . '/';
        $target = rtrim(\fs::toInternal($folder), '/') . '/';

        if ($target === $cwd) {
            return './';
        }
        if (str_starts_with($target, $cwd)) {
            return substr($target, strlen($cwd));
        }

        $cwdParts    = array_values(array_filter(explode('/', $cwd), 'strlen'));
        $targetParts = array_values(array_filter(explode('/', $target), 'strlen'));
        $i = 0;
        $n = min(count($cwdParts), count($targetParts));
        while ($i < $n && $cwdParts[$i] === $targetParts[$i]) {
            $i++;
        }
        $up  = count($cwdParts) - $i;
        $rel = str_repeat('../', $up) . implode('/', array_slice($targetParts, $i));
        return rtrim($rel, '/') . '/';
    }

    // =========================================================================
    // Close
    // =========================================================================

    public function close(): bool
    {

        if ($this->nodeStack === []) {
            $this->closeDomLayer();
            return false;
        }

        $this->closeDomLayer();
        \fs::closeDir();
        $this->activeNode = array_pop($this->nodeStack);

        return true;
    }

    public function closeAll(): void
    {
        while ($this->close()) {
            // until the stack is empty
        }
    }

    // =========================================================================
    // Render phase starters (delegated to DomRenderer)
    // =========================================================================

    public function beginLayers(): void
    {
        $this->domRenderer->beginLayers($this->resolveCallerNode());
    }

    public function endLayers(): void
    {
        $this->domRenderer->endLayers();
    }

    public function beginDiff(): void
    {
        $this->domRenderer->beginDiff($this->resolveCallerNode());
    }

    public function endDiff(): void
    {
        $this->domRenderer->endDiff();
    }

    public function beginDocument(): void
    {
        $this->domRenderer->beginDocument($this->resolveCallerNode());
    }

    public function endDocument(): void
    {
        $this->domRenderer->endDocument();
    }

    public function inDiffMode(): bool
    {
        return $this->domRenderer->checkMode(['diff']);
    }

    // =========================================================================
    // Internal — layer bookkeeping
    // =========================================================================

    /**
     * Creates the layer for an open attempt, before it is known whether the
     * open succeeds. Registering it under the CALLER's address + view name is
     * what lets the next request find it again; the diff comparison itself
     * waits for confirm(), where markOpened() has set the identity.
     */
    private function createLayer(string $method, array $args): void
    {
        if (!$this->domRenderer->checkMode(['document', 'layers', 'diff'])) {
            return;
        }
        if ($this->activeNode === null) {
            return;
        }

        $layer = $this->layerTree->create(
            $method,
            $args,
            $this->activeNode,
            $this->domRenderer->mode(),
        );

        $viewName = is_string($args[0] ?? null) ? $args[0] : '';
        if ($viewName !== '') {
            $this->layerTree->registerReference($this->activeNode, $viewName, $layer);
        }
    }

    /**
     * Successful open: mark the layer as opened, run the diff comparison, and
     * start a fresh slice.
     *
     * The reference lookup uses the CALLER node (top of the stack) — by the
     * time confirm() runs, activeNode is already the just-opened child, and
     * the previous request registered under the caller's address.
     */
    private function confirm(string $method, array $args, RouteNode $node, ?ViewHit $hit): bool
    {
        $this->recordRequest($method, $args, $node, $hit?->file, 'opened');

        if (!$this->domRenderer->checkMode(['document', 'layers', 'diff'])) {
            return true;
        }

        $active = $this->layerTree->active();
        if ($active !== null) {
            $active->markOpened($node);
            $active->setTemplateFile($hit?->file, $node);

            if ($this->domRenderer->checkMode(['diff'])) {
                $caller   = end($this->nodeStack) ?: $node;
                $refLayer = $this->getRefLayer($args, $caller);
                $this->domRenderer->addReference($refLayer, $active, $this->tree);
            }
        }
        $this->layerTree->addSlice();

        return true;
    }

    /**
     * Failed open: still emit a layer, so the slot keeps its position.
     *
     * This is the mechanism that replaces the legacy placeholder handling
     * ($layer->result === FALSE in renderPagesDiff.php). The client walks
     * child layers by index; a template whose open fails in one request and
     * succeeds in the next must occupy the same slot in both, or the index
     * walk desyncs. The placeholder carries result=false and an empty
     * identity, so the diff sees a plain mismatch and re-renders the slot.
     */
    private function cancel(string $method, array $args, ?string $reason = null, ?RouteNode $node = null): bool
    {
        $this->recordRequest($method, $args, $node, null, 'cancelled', $reason);

        if ($this->domRenderer->checkMode(['document', 'layers', 'diff'])
            && $this->activeNode !== null
        ) {
            $this->createLayer($method, $args);
            $active = $this->layerTree->active();

            if ($active !== null) {
                $active->reason = $reason;
                if ($this->domRenderer->checkMode(['diff'])) {
                    $refLayer = $this->getRefLayer($args, $this->activeNode);
                    $this->domRenderer->addReference($refLayer, $active, $this->tree);
                }
            }

            $this->layerTree->close();
        }

        return false;
    }

    private function closeDomLayer(): void
    {
        if ($this->domRenderer->checkMode(['document', 'layers', 'diff'])) {
            $this->layerTree->close();
        }
    }

    private function getRefLayer(array $args, ?RouteNode $caller): ?DomLayer
    {
        $caller ??= $this->activeNode;
        if ($caller === null) {
            return null;
        }
        $viewName = is_string($args[0] ?? null) ? $args[0] : '';
        return $this->layerTree->getReference($caller, $viewName);
    }

    private function resolveCallerNode(): RouteNode
    {
        $node = $this->activeNode ?? $this->tree->getRoot();
        if ($node === null) {
            throw new \RuntimeException(
                'PageNavigator: no active or root node — call Route::build() before starting a render phase.'
            );
        }
        return $node;
    }

    // =========================================================================
    // Debug
    // =========================================================================

    private function recordRequest(
        string    $method,
        array     $args,
        ?RouteNode $node,
        ?string   $file,
        string    $result,
        ?string   $reason = null,
    ): void {
        $this->lastRequest = [
            'method'     => $method,
            'args'       => $args,
            'cwd'        => \fs::cwd(),
            'page'       => $node?->getLayerIdentity(),
            'file'       => $file,
            'fileExists' => $file !== null ? \fs::isFile($file) : null,
            'result'     => $result,
            'reason'     => $reason,
            'activePage' => $this->activeNode?->getLayerIdentity(),
            'stackDepth' => count($this->nodeStack),
        ];
    }

    public function debugInfo(): array
    {
        return $this->lastRequest ?? ['method' => null, 'note' => 'no open* request issued yet'];
    }
}
