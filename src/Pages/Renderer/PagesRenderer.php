<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Renderer;

use Systopic\System\Client\ClientExport;
use Systopic\System\Dom\Layer\DomLayer;
use Systopic\System\Dom\Renderer\DomRenderer;
use Systopic\System\Dom\Tree\DomLayerTree;

/**
 * Packages the page layer tree for the client. Replaces routeRenderer.
 *
 * The layer machinery itself is DomRenderer/DomLayerTree — shared with the
 * panels. What is specific to a public page is the SHAPE of the export: a
 * page render has to feed two different DOM roots, document.head and
 * document.body, whereas an app render only ever fills the body.
 *
 * The render therefore opens one `document` view whose template does nothing
 * but open `head` and `body`:
 *
 *   root (getLayers/getDiff)
 *     └── openRoot document          ← no markup of its own
 *           ├── openView head        → document.head
 *           └── openView body        → document.body
 *
 * exportDocument() picks those two subtrees back out. Everything below them
 * is a plain DomLayer tree and is diffed, exported and patched exactly like
 * a panel tree — the client reuses sys.core.dom.layer unchanged.
 */
class PagesRenderer
{
    public const VIEW_DOCUMENT = 'document';
    public const VIEW_HEAD     = 'head';
    public const VIEW_BODY     = 'body';

    public function __construct(
        private readonly DomRenderer  $domRenderer,
        private readonly DomLayerTree $layerTree,
    ) {}

    // =========================================================================
    // Singleton access (legacy code reaches the renderer without DI)
    // =========================================================================

    private static ?PagesRenderer $instance = null;

    public static function setInstance(PagesRenderer $renderer): void
    {
        self::$instance = $renderer;
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    // =========================================================================
    // Export
    // =========================================================================

    /**
     * The payload the client patches the document with.
     *
     * headLayer / bodyLayer are `false` when the render produced no such view,
     * which is what the client checks before touching that DOM root.
     */
    public function exportDocument(): object
    {
        $document = $this->documentLayer();

        $head = $document !== null ? $this->childByView($document, self::VIEW_HEAD) : null;
        $body = $document !== null ? $this->childByView($document, self::VIEW_BODY) : null;

        return (object) [
            'headLayer'   => $head !== null ? $head->export() : false,
            'bodyLayer'   => $body !== null ? $body->export() : false,
            'bodyClasses' => $this->domRenderer->getBodyClasses(),
        ];
    }

    /**
     * Static entry point for the legacy clientInterfaces registry, which
     * calls clientData_export() on the class name. Mirrors PanelTree.
     */
    public static function clientData_export(): ClientExport|array
    {
        $instance = self::$instance;
        if ($instance === null || !$instance->hasRenderedDocument()) {
            // Nothing was rendered by the pages stack, so there is nothing to
            // patch — and crucially no onLibReady hook, which would otherwise
            // call sys.siteRenderer.updateDocument() on a page that never
            // loaded pagesRenderer.js.
            return [];
        }
        return $instance->exportClientData();
    }

    /**
     * True when this request actually produced a page document — i.e. the
     * layer tree's root carries a `document` view with head/body below it.
     * False for a panel render, which uses the same DomLayerTree but a
     * completely different layer shape.
     */
    public function hasRenderedDocument(): bool
    {
        $document = $this->documentLayer();
        if ($document === null || $document->viewName !== self::VIEW_DOCUMENT) {
            return false;
        }
        return $this->childByView($document, self::VIEW_HEAD) !== null
            || $this->childByView($document, self::VIEW_BODY) !== null;
    }

    /**
     * Route: 'singletons.pages.renderer' — the JS side registers that route
     * with the shorthand `siteRenderer`, which is the name
     * server.command.js already dispatches to for non-app requests.
     *
     * onLibReady fires updateDocument() once the lib tree is built, so the
     * very first paint runs through the same client path as every later diff.
     */
    public function exportClientData(): ClientExport
    {
        return new ClientExport(
            route: 'singletons.pages.renderer',
            data: $this->exportDocument(),
            onLibReady: ['sys.siteRenderer.updateDocument'],
        );
    }

    /** Full layer tree — used by the CLI/JSON branch of site.php. */
    public function exportJson(): array
    {
        $root = $this->layerTree->root();

        return [
            'layers'      => $root !== null ? $root->export() : [],
            'document'    => $this->exportDocument(),
            'bodyClasses' => $this->domRenderer->getBodyClasses(),
            'mode'        => $this->domRenderer->mode(),
        ];
    }

    // =========================================================================
    // Layer lookup
    // =========================================================================

    /**
     * The `document` layer — the single child of the synthetic render root.
     *
     * Tolerates the root itself being the document layer, which is what a
     * render that skipped openRoot() would produce.
     */
    private function documentLayer(): ?DomLayer
    {
        $root = $this->layerTree->root();
        if ($root === null) {
            return null;
        }
        if ($root->viewName === self::VIEW_DOCUMENT) {
            return $root;
        }
        return $this->childByView($root, self::VIEW_DOCUMENT) ?? $this->firstChildLayer($root);
    }

    private function childByView(DomLayer $layer, string $viewName): ?DomLayer
    {
        foreach ($layer->items as $item) {
            if ($item->isLayer() && $item->viewName === $viewName) {
                /** @var DomLayer $item */
                return $item;
            }
        }
        return null;
    }

    private function firstChildLayer(DomLayer $layer): ?DomLayer
    {
        foreach ($layer->items as $item) {
            if ($item->isLayer()) {
                /** @var DomLayer $item */
                return $item;
            }
        }
        return null;
    }

    // =========================================================================
    // Debug
    // =========================================================================

    /**
     * Reference tree + current tree side by side, as route::debug() did —
     * as box-tree data, see DomRenderer::debug().
     *
     * @return list<array<string, mixed>>
     */
    public function debug(): array
    {
        return $this->domRenderer->debug();
    }
}
