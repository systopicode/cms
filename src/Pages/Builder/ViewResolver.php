<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Builder;

use Systopic\Cms\Pages\Route\RouteNode;
use Systopic\Cms\Pages\PageRootFolder;

/**
 * Finds the template file for (page, view).
 *
 * Replaces route::hasTemplate(), which hard-coded a two-step lookup:
 * the page's own template folder first, the site root second. That lookup
 * is what made four separate legacy features work, and all four survive
 * here unchanged:
 *
 *  1. View override with root fallback — openPage(22, $lang, 'footernavi')
 *     renders node 22 with the site-wide footernavi.tpl.php no matter which
 *     template the DB assigns to node 22.
 *  2. Sibling views inside a template folder — 'ausstellungen' rendering its
 *     grandchildren with tile.tpl.php instead of main.tpl.php.
 *  3. template->hasBody — a template folder shipping its own body.tpl.php
 *     wins over the site-wide one, because step 1 runs first.
 *  4. Inline mode ($viewName === false) — handled by the navigator, not here:
 *     there is no file to resolve, the caller renders the child itself.
 *
 * The only addition is multi-root: several VIEWS roots may be registered
 * (system defaults + project overrides) and are consulted last-wins, exactly
 * like Panels\Builder\FolderResolver does for panel folders. A VIEWS root may
 * be bound to a site (PageRootFolder::$site); it then counts only for nodes
 * of that site — <project>/pages/<siteName>/ over <project>/pages/.
 */
class ViewResolver
{
    /** @var PageRootFolder[] */
    private array $roots = [];

    public function addRoot(PageRootFolder $root): void
    {
        $this->roots[] = $root;
    }

    /** @return PageRootFolder[] */
    public function getRoots(): array
    {
        return $this->roots;
    }

    /**
     * Resolves <viewName>.tpl.php for $node, or null when no root ships it.
     * A null result makes the navigator emit a placeholder layer — the same
     * outcome the legacy `if (route::openView(...))` guard produced.
     */
    public function resolve(RouteNode $node, string $viewName): ?ViewHit
    {
        if ($viewName === '') {
            return null;
        }

        // 1. the page's own template folder (DB-assigned), e.g. templates/700_galerie/
        $templateFolder = $this->templateFolder($node);
        if ($templateFolder !== null) {
            $hit = $this->probe($templateFolder, $viewName, 'template');
            if ($hit !== null) {
                return $hit;
            }
        }

        // 2. views roots, last registered wins (project overrides system); a
        //    root bound to a site only for nodes of that site
        return $this->probeViewsRoots($viewName, $node->site()?->name);
    }

    /**
     * Only what belongs to a page ITSELF: its template folder. This is what
     * the render-host walk asks on its way up (PageNavigator::openRenderHost()):
     * "do you ship your own body?" — a page template with body.tpl.php
     * replaces the body for its pages (full-screen views). Everything else
     * opens on the site root and resolves through resolve(): the site's
     * sites/<domain>/templates/document/ before templates/document/.
     */
    public function resolveOwn(RouteNode $node, string $viewName): ?ViewHit
    {
        if ($viewName === '') {
            return null;
        }
        $templateFolder = $this->templateFolder($node);
        return $templateFolder === null ? null : $this->probe($templateFolder, $viewName, 'template');
    }

    /** The VIEWS roots, last wins, skipping those bound to another site. */
    private function probeViewsRoots(string $viewName, ?string $site): ?ViewHit
    {
        foreach (array_reverse($this->roots) as $root) {
            if ($root->kind !== PageRootFolder::VIEWS) {
                continue;
            }
            if ($root->site !== null && $root->site !== $site) {
                continue;
            }
            $hit = $this->probe((string) $root->dir, $viewName, 'views');
            if ($hit !== null) {
                return $hit;
            }
        }
        return null;
    }

    /**
     * Public accessor for the DB-assigned template folder of a node.
     * site.php uses it to include a template's config.php / actions.php
     * before rendering starts.
     */
    public function templateFolderOf(?RouteNode $node): ?string
    {
        return $node !== null ? $this->templateFolder($node) : null;
    }

    /**
     * Finds a bare file name in the VIEWS roots (last wins).
     *
     * For files that are included directly rather than opened as a layer —
     * head.loader.tpl.php, which carries the static <head> (favicons, JS/CSS
     * loader) and must stay outside the diffed head layer.
     */
    public function resolveFile(string $fileName): ?string
    {
        // the site being served — its pages/<site>/ root wins over pages/
        $site = \Systopic\System\Config\Config::current()?->site()?->name;
        foreach (array_reverse($this->roots) as $root) {
            if ($root->kind !== PageRootFolder::VIEWS) {
                continue;
            }
            if ($root->site !== null && $root->site !== $site) {
                continue;
            }
            $candidate = rtrim(\fs::toInternal((string) $root->dir), '/') . '/' . $fileName;
            if (\fs::isFile($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Absolute folder of the page's DB-assigned template, or null when the
     * page has no template (the tree root has no record at all) or the
     * folder does not exist on disk.
     *
     * Looked up by template NAME in the TEMPLATES roots, last-wins, skipping
     * roots bound to another site: sites/<domain>/templates/pages/ before
     * templates/pages/. By name, not by folder, because the numeric prefix is
     * only the sort order — a site may override '200_landing_page' with
     * '250_landing_page'. Resolved against absolute roots, not against the
     * CWD, which is what made the legacy lookup depend on fs::openRootDir().
     */
    private function templateFolder(RouteNode $node): ?string
    {
        if ($node->page === null) {
            return null; // the tree root has no record and no template
        }
        $name = $node->page->templateName;
        if ($name === '') {
            return null;
        }
        $site = $node->site()?->name;

        foreach (array_reverse($this->roots) as $root) {
            if ($root->kind !== PageRootFolder::TEMPLATES) {
                continue;
            }
            if ($root->site !== null && $root->site !== $site) {
                continue;
            }
            $base = rtrim(\fs::toInternal((string) $root->dir), '/');
            if (\fs::isDir("$base/$name")) {
                return "$base/$name";
            }
            foreach (glob(\fs::toNative($base) . DIRECTORY_SEPARATOR . '*_' . $name, GLOB_ONLYDIR) ?: [] as $candidate) {
                if (preg_match('~^\d+_' . preg_quote($name, '~') . '$~', basename($candidate))) {
                    return \fs::toInternal($candidate);
                }
            }
        }

        return null;
    }
    private function probe(string $folder, string $viewName, string $source): ?ViewHit
    {
        $folder = rtrim(\fs::toInternal($folder), '/');
        $file   = "{$folder}/{$viewName}.tpl.php";
        return \fs::isFile($file) ? new ViewHit($folder, $file, $source) : null;
    }
}
