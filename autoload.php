<?php

/**
 * systopic/cms — the website: pages, paths, sites, page settings, the page
 * panels in /cms/, site.php and sitemap.xml. Not needed by projects without
 * pages (pwk, spaceticker).
 *
 * Composer `autoload.files`: tells the system what the package brings. The
 * system itself never names this package.
 */

use Systopic\Db\Schema\Registry;
use Systopic\System\Sys\Hooks\Hooks;
use Systopic\System\Sys\Packages;

// lib/class/… (appSite behind app::site()), and the root its panels' JS/CSS load from
Packages::addLib('systopic/cms', __DIR__);

// panels/cms/… — merged into the system's /cms/ (pages, page settings, path maintenance)
Packages::addPanels('systopic/cms', 'Systopic\Cms\Panels');

// public/css/ — page-only additions to the system GUI (page header, preview), in every panel head
Packages::addPanelAssets(__DIR__ . '/public/');

// the sites from cms_sites, right after the database connection (loader.php)
Hooks::on('afterConnect', static fn() => \Systopic\Cms\Pages\Sites\SiteRegistry::boot());

// sitemap.xml, llms.txt, the page route, site.php
Packages::addFrontend(new \Systopic\Cms\Frontend());

// the tables of the CMS — scanned by the ORM on the first Scope
Registry::addTables(__DIR__ . '/src/Tables', 'Systopic\Cms\Tables');

// cms/search/ (Cmd+K): pages and media
// A removed media node may be a page's share image (Meta::$shareImageId).
// Runs inside the removal's transaction.
Hooks::on('mediaRemoved', static function (\Systopic\Db\Scope $scope, array $nodeIds): void {
	$pages = \Systopic\Db\Schema\Schema::tableNameOf(\Systopic\Cms\Tables\Pages\Model::class);
	$marks = implode(',', array_fill(0, count($nodeIds), '?'));
	$scope->connection->run("UPDATE $pages SET page_meta_share_image = NULL WHERE page_meta_share_image IN ($marks)", $nodeIds);
	$scope->results->invalidate([$pages]);
});
