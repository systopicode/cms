<?php

/**
 * systopic/cms — set up the page system after the panel tree (loader.php
 * includes this in the global scope through Frontend::boot()).
 *
 * Mirrors the panel block of loader.php. Pages and panels share $layerTree /
 * $domRenderer from the system's src/bootstrap.php: only one of the two
 * renders per request, so one layer stack is enough.
 */

// Two project layouts are understood.
//
// templates/ with subfolders (current):
//   <sys>/site/                          system default views
//   templates/document/                  document, head, head.loader, body — for every site
//   templates/components/                navi, header, gallery, contact form … — for every site
//   templates/pages/                     page templates, addressed via cms_pages.page_template_name
//   sites/<domain>/templates/{document,components,pages}/
//                                        the same, for one site only (config/ `sites`);
//                                        registered last, so they win for that site's nodes
//   templates/route.php                  the project's RouteNode subclass (<ns>\Pages\RouteNode)
//   (templates/layout/ — grid, rows, columns — is planned and not registered yet)
//
// Legacy (projects not moved yet): pages/ = views + page.php, pages/<site>/ =
// site views, templates/ = page templates.
//
// Roots resolve last-wins, as for panels.
$projectPagesNs = defined('PROJECT_NAMESPACE') ? PROJECT_NAMESPACE . '\Pages' : '';
$_root          = fs::$projectRoot;
$_templates     = $_root ? $_root->getFolder('templates') : null;
$_structured    = $_templates && $_templates->exists && $_templates->getFolder('pages')->exists;
$_config        = \Systopic\System\Config\Config::current();

$route = new \Systopic\Cms\Pages\Route\Route();

// Fallback views: the system's site/ (head.loader), this package's site/
// (document), then those of other packages (systopic/system-legacy: head,
// actions); a project's own views win (last-wins).
$_sysSiteViews = fs::$sysRoot ? fs::$sysRoot->getFolder('site') : null;
if ($_sysSiteViews && $_sysSiteViews->exists) {
	$route->addRoot(new \Systopic\Cms\Pages\PageRootFolder(
		$_sysSiteViews, \Systopic\Cms\Pages\PageRootFolder::VIEWS, 'Systopic\Cms\Pages'
	));
}
$route->addRoot(new \Systopic\Cms\Pages\PageRootFolder(
	\fsDir::get(fs::toInternal(__DIR__ . '/site')), \Systopic\Cms\Pages\PageRootFolder::VIEWS, 'Systopic\Cms\Pages'
));
foreach (\Systopic\System\Sys\Packages::siteViews() as $_sysSiteViews) {
	$route->addRoot(new \Systopic\Cms\Pages\PageRootFolder(
		\fsDir::get(fs::toInternal($_sysSiteViews['dir'])), \Systopic\Cms\Pages\PageRootFolder::VIEWS, $_sysSiteViews['namespace']
	));
}

// One set of folders — shared (site null) or one site's.
$_addRoots = static function (?\fsDir $dir, string $kind, ?string $site) use ($route, $projectPagesNs): void {
	if ($dir && $dir->exists) {
		$route->addRoot(new \Systopic\Cms\Pages\PageRootFolder($dir, $kind, $projectPagesNs, $site));
	}
};

if ($_structured) {
	$_addRoots($_templates->getFolder('document'), \Systopic\Cms\Pages\PageRootFolder::VIEWS, null);
	$_addRoots($_templates->getFolder('components'), \Systopic\Cms\Pages\PageRootFolder::VIEWS, null);
	$_addRoots($_templates->getFolder('pages'), \Systopic\Cms\Pages\PageRootFolder::TEMPLATES, null);
	$_sitesDir = $_root->getFolder('sites');
	if ($_config !== null && $_sitesDir->exists) {
		foreach ($_config->sites->all() as $_siteName => $_site) {
			$_siteTemplates = $_sitesDir->getFolder($_siteName)->getFolder('templates');
			if (!$_siteTemplates->exists) {
				continue;
			}
			$_addRoots($_siteTemplates->getFolder('document'), \Systopic\Cms\Pages\PageRootFolder::VIEWS, $_siteName);
			$_addRoots($_siteTemplates->getFolder('components'), \Systopic\Cms\Pages\PageRootFolder::VIEWS, $_siteName);
			$_addRoots($_siteTemplates->getFolder('pages'), \Systopic\Cms\Pages\PageRootFolder::TEMPLATES, $_siteName);
		}
	}
	// templates/route.php with <ns>\Pages\RouteNode; templates/page.php with <ns>\Pages\Page before the rename
	$pageClassFile = rtrim((string) $_templates, '/') . '/route.php';
	$pageClassName = 'RouteNode';
	if (!fs::isFile($pageClassFile)) {
		$pageClassFile = rtrim((string) $_templates, '/') . '/page.php';
		$pageClassName = 'Page';
	}
} else {
	$_pages = $_root ? $_root->getFolder('pages') : null;
	$_addRoots($_pages, \Systopic\Cms\Pages\PageRootFolder::VIEWS, null);
	if ($_config !== null && $_pages && $_pages->exists) {
		foreach ($_config->sites->all() as $_siteName => $_site) {
			$_addRoots($_pages->getFolder($_siteName), \Systopic\Cms\Pages\PageRootFolder::VIEWS, $_siteName);
		}
	}
	$_addRoots($_templates, \Systopic\Cms\Pages\PageRootFolder::TEMPLATES, null);
	$pageClassFile = $_pages ? rtrim((string) $_pages, '/') . '/page.php' : '';
	$pageClassName = 'Page';
}

// The project's RouteNode subclass — the pages equivalent of panels/panel.php.
// include'd rather than autoloaded: projects carry no composer autoload block.
if ($pageClassFile !== '' && fs::isFile($pageClassFile)) {
	include_once fs::toNative($pageClassFile);
	$pageClass = $projectPagesNs . '\\' . $pageClassName;
	if (class_exists($pageClass)) {
		$route->setNodeClass($pageClass);
	} else {
		logroute('pages: route class file present but class not found', $pageClass);
	}
}
unset($_root, $_templates, $_structured, $_config, $_sysSiteViews, $_addRoots, $_sitesDir, $_siteName, $_site, $_siteTemplates, $_pages);

$pageNav       = new \Systopic\Cms\Pages\Navigator\PageNavigator($route, $layerTree, $domRenderer);
$pagesRenderer = new \Systopic\Cms\Pages\Renderer\PagesRenderer($domRenderer, $layerTree);

\Systopic\Cms\Pages\Route\RouteNode::setNavigator($pageNav);
\Systopic\Cms\Pages\Route\RouteNode::setTree($route);
\Systopic\Cms\Pages\Route\Route::setInstance($route);
\Systopic\Cms\Pages\Renderer\PagesRenderer::setInstance($pagesRenderer);

clientInterfaces::$clientDataClasses[] = \Systopic\Cms\Pages\Route\Route::class;
// PagesRenderer is registered further down, and only when the request is
// actually dispatched to site.php: its export carries
// onLibReady: ['sys.siteRenderer.updateDocument'], and pagesRenderer.js is
// shipped by the pages head loader only. On a panel request the hook would
// fire against an undefined sys.siteRenderer.
