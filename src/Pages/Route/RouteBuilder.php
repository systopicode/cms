<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Route;

use Systopic\Db\Scope;
use Systopic\Cms\Pages\Sites\SiteRegistry;
use Systopic\Cms\Queries\PageTree\PageTreeLevel;
use Systopic\Cms\Queries\Params;
use Systopic\Cms\Tables\Pages\Page;

/**
 * Builds the page route from the database — the pages counterpart of
 * PanelBuilder.
 *
 * Panels are built by walking folders down from a root. Pages are built the
 * other way round: the addressed page is loaded, and the chain is discovered by
 * walking node_parent_node_id UP, then reversing.
 *
 * Above the pages sits a node without content:
 *   - the tree root — no id, its children are the pages with parent NULL;
 *   - for a page of a site, the site node (type 'site') under the tree root,
 *     its children are the site's pages.
 *
 * Content comes from the ORM (`Tables\Pages\Page`), loaded under the route's
 * parameters: the request language, preview for the CMS, only page nodes.
 * An unpublished page is not loaded at all, which ends the walk there.
 *
 * Children are NOT built eagerly. A navigation template that iterates them
 * (eachChild) triggers Route::ensureChildrenBuilt(), which lands here in
 * buildChildren(). A leaf content template never pays for a children query.
 */
class RouteBuilder
{
    /**
     * Hard cap on route depth, inherited from route::build(). A cycle in
     * node_parent_node_id would otherwise loop forever.
     */
    private const MAX_DEPTH = 20;

    /** Class used for every node; a project subclasses RouteNode via setNodeClass(). */
    private string $nodeClass = RouteNode::class;

    /** @var array<string, string> Debug log of what the build did. */
    private array $buildLog = [];

    public function setNodeClass(string $class): void
    {
        if (!is_a($class, RouteNode::class, true)) {
            \d("RouteBuilder: '{$class}' is not a RouteNode — falling back to " . RouteNode::class);
            return;
        }
        $this->nodeClass = $class;
    }

    public function getNodeClass(): string
    {
        return $this->nodeClass;
    }

    /**
     * Builds the chain root → [site node] → … → selected.
     *
     * @return RouteNode[] Ordered root first, selected last. Only the root
     *                     when the page could not be loaded (missing,
     *                     unpublished, no content in this language).
     */
    public function buildRoute(Scope $scope, Params $params, int $id): array
    {
        $pages      = [];
        $currentId  = $id;
        $guard      = self::MAX_DEPTH;
        $siteNodeId = null;

        while ($currentId > 0 && $guard-- > 0) {
            $page = Page::byId($scope, $params, $currentId);
            if ($page === null || $page->row === null) {
                $this->buildLog[] = "page $currentId/{$params->lang} not loaded";
                break;
            }
            $pages[] = $page;

            // The parent node is not in the page query (a site node has no
            // page row, so it would not even be found there): the relation
            // fetches it once, the identity map keeps it for the next step.
            $parentNode = $page->node->parentNode;
            if ($parentNode === null) {
                break;   // the topmost level
            }
            if ($parentNode->type === SiteRegistry::NODE_TYPE) {
                $siteNodeId = (int) $parentNode->id;   // the top of this site's pages
                break;
            }
            $currentId = (int) $parentNode->id;
        }
        if ($guard <= 0) {
            \d('RouteBuilder: max route depth (' . self::MAX_DEPTH . ') exceeded — check node_parent_node_id for a cycle');
        }

        $pages = array_reverse($pages);
        $class = $this->nodeClass;

        /** @var RouteNode $root */
        $root   = new $class(null, null);
        $nodes  = [$root];
        $parent = $root;
        if ($siteNodeId !== null) {
            $siteNode = new $class(null, $root, $siteNodeId);
            $root->childSelected = $siteNode;
            $nodes[] = $siteNode;
            $parent  = $siteNode;
        }
        foreach ($pages as $page) {
            /** @var RouteNode $node */
            $node = new $class($page, $parent);
            $parent->childSelected = $node;
            $nodes[] = $node;
            $parent  = $node;
        }

        $this->buildLog[] = 'route: ' . implode(' / ', array_map(
            static fn(RouteNode $n) => $n->getLayerIdentity(),
            $nodes,
        ));

        return $nodes;
    }

    /**
     * Builds $parent's children and links them as siblings — pages only, with
     * content in the route's language.
     *
     * The child of a node that is already on the route must reuse the node
     * object that is IN the route — otherwise a navigation template iterating
     * the children would compare a fresh object against the selected one and
     * never mark it as active. route::getChildren() solved this the same way.
     */
    public function buildChildren(RouteNode $parent, Scope $scope, Params $params): void
    {
        if ($parent->page === null) {
            // the tree root: the topmost pages; a site node: the pages below it
            $level = PageTreeLevel::under($scope, $params, [$parent->siteNodeId ?? 0]);
            $childPages = array_map(static fn($node) => Page::of($scope, $params, $node), $level->nodes());
        } else {
            $childPages = $parent->page->children;
        }

        $class    = $this->nodeClass;
        $children = [];
        $prev     = null;

        foreach ($childPages as $childPage) {
            // nested site nodes and pages without content here are no children of the route
            if ($childPage->node->type !== 'page' || $childPage->row === null) {
                continue;
            }
            $selected = $parent->childSelected;
            if ($selected !== null && $selected->page?->id === $childPage->id) {
                $node = $selected;   // identity-preserving: the route node IS this child
            } else {
                /** @var RouteNode $node */
                $node = new $class($childPage, $parent);
            }

            if ($prev !== null) {
                $prev->next = $node;
                $node->prev = $prev;
            }
            $children[] = $node;
            $prev       = $node;
        }

        $parent->children = $children;
    }

    /** @return string[] */
    public function getBuildLog(): array
    {
        return $this->buildLog;
    }
}
