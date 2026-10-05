<?php

declare(strict_types=1);

namespace Systopic\Cms;

use Systopic\Cms\Pages\Renderer\PagesRenderer;
use Systopic\Cms\Pages\Route\PageAccess;
use Systopic\Cms\Pages\Router\UrlResolver;

/**
 * The website as a frontend of the system: sitemap.xml, the page route, and
 * site.php for every URL that resolves to a page the visitor may see.
 *
 * A panel selected by the URL path wins before this is asked (loader.php):
 * a page id can also arrive as ?id=, the CMS's own page selector, and
 * /cms/pages/?id=20 must open the editor, not page 20.
 */
final class Frontend implements \Systopic\System\Sys\Frontend
{
    public function name(): string
    {
        return 'site';
    }

    public function early(): ?string
    {
        // rawpath keeps its leading slash ('/sitemap'), and the rewrite hands
        // sitemap.xml over with its extension - accept both spellings.
        return in_array(trim(\app::request()->rawpath, '/'), ['sitemap', 'sitemap.xml'], true)
            ? dirname(__DIR__) . '/sitemap.php'
            : null;
    }

    public function boot(): ?string
    {
        return dirname(__DIR__) . '/boot.php';
    }

    public function claims(): bool
    {
        \logroute('PageAccess::databaseReady()', PageAccess::databaseReady());
        if (!PageAccess::databaseReady()) {
            return false;
        }
        // URL → page id/lang: "is there a page for this URL?" decides.
        UrlResolver::resolve();
        $id = \app::request()->id;
        $hasAccess = ($id !== null && $id !== '') ? PageAccess::check() : false;
        \logroute('page resolution', ['id' => $id, 'checkAccess' => $hasAccess]);
        if ($id !== null && $id !== '' && $hasAccess) {
            \logroute('dispatch: site.php (page found)');
            return true;
        }
        return false;
    }

    public function dispatch(): string
    {
        // The page layer payload — pagesRenderer.js reads it as
        // sys.singletons.pages.renderer.__data and patches head + body from it.
        // Only now: its export fires sys.siteRenderer.updateDocument, which a
        // panel request does not have.
        \clientInterfaces::$clientDataClasses[] = PagesRenderer::class;
        return dirname(__DIR__) . '/site.php';
    }
}
