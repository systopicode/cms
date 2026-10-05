<?php

/**
 * Public website — DB-driven pages.
 *
 * Panels live in app.php. Both render into the SAME DomLayer stack
 * ($layerTree / $domRenderer from src/bootstrap.php); only one of them runs
 * per request, so the client only ever sees one layer tree.
 *
 * Replaces the route/routeRenderer/renderPagesDiff.php stack. The diff is no
 * longer a replay loop over a recorded tree: PageNavigator::confirm() compares
 * each layer against the previous request's reference layer while the walk is
 * happening, exactly as PanelNavigator does.
 *
 * Branches, in order:
 *   PUT                  → commands only, no re-render
 *   OUTPUT_FORMAT=json   → CLI/JSON probe of the layer tree
 *   AJAX GET             → diff render + updateDocument
 *   else                 → full render: document skeleton + layers as JSON
 *
 * @var \Systopic\Cms\Pages\Route\Route              $route
 * @var \Systopic\Cms\Pages\Navigator\PageNavigator    $pageNav
 * @var \Systopic\Cms\Pages\Renderer\PagesRenderer     $pagesRenderer
 * @var \Systopic\System\Dom\Renderer\DomRenderer         $domRenderer
 * @var \Systopic\System\Dom\Tree\DomLayerTree            $layerTree
 */

http::sessionStartOnce();

/* * **************************** SET VARIABLES ************************** */

$ip       = $_SERVER['REMOTE_ADDR'] ?? null;
$onLoadJS = [];

$lang              = app::request()->lang;
$id                = app::request()->id;
$preview           = app::request()->preview;
$showtrashed       = app::request()->showtrashed;
$showeditableareas = app::request()->showeditableareas;

if (!\Systopic\Cms\Pages\Route\PageAccess::databaseReady()) {
	header('HTTP/1.0 404 Not Found');
	die('site.php says: page not found, no pages table in database. @cwd:' . fs::cwd());
}

\Systopic\Cms\Pages\Router\UrlResolver::resolve();
hooks::emit('afterGetPageId');
if (is_callable('onAfterGetPageId')) {
	// Legacy global hook. Prefer hooks::on('afterGetPageId', fn() => ...).
	// Note: do NOT announce this with d() — d() dumps and exits.
	logroute('site.php: legacy hook onAfterGetPageId() called');
	onAfterGetPageId();
}

// PageAccess::check() already ran in loader.php for the dispatch decision and
// left the built route on Route. Only build again if that did not happen
// (e.g. site.php reached as the no-module fallback).
if (!$route->isBuilt()) {
	\Systopic\Cms\Pages\Route\PageAccess::check();
}
$selectedPage = \Systopic\Cms\Pages\Route\PageAccess::$selectedPage;

if (!\Systopic\Cms\Pages\Route\PageAccess::$accessGranted) {
	// logroute, not d(): d() exits, which would swallow the redirect below.
	logroute('site.php: no access to page', [
		'queryResult'        => \Systopic\Cms\Pages\Route\PageAccess::$lastQueryResult,
		'id'                 => app::request()->id,
		'lang'               => app::request()->lang,
		'pageResolutionType' => app::request()->pageResolutionType,
		'rawpath'            => app::request()->rawpath,
	]);
	http::redirect(http::$root);
}

// One request language for the whole render: the one the route was built in.
// Templates read it as $this->lang. The global stays for legacy code
// (page::getLanguageSelected(), the shop) - it is no template variable.
$lang = $route->getLanguage();
if ($lang === '*' || $lang === null) {
	$lang = defined('DEFAULT_LANG') ? DEFAULT_LANG : 'de';
}
$GLOBALS['lang'] = $lang;

if (!http::ajax() && ($redirectTarget = $selectedPage->redirectsTo) !== null) {
	http::redirect($redirectTarget->link);
}


$settings['js']             = [];
$settings['js']['naviPath'] = $route->getIds();

/* * **************************** DEFINE BODY CLASSES ************************** */

$bodyClasses = $route->getBodyClasses();
if (\Systopic\System\Auth\Session::known()) {
	$bodyClasses[] = 'onPageEditing';
	if ($showeditableareas) {
		$bodyClasses[] = 'showeditableareas';
	}
}
hooks::emit('setBodyClasses');
if (is_callable('onSetBodyClasses')) {
	logroute('site.php: legacy hook onSetBodyClasses() called');
	onSetBodyClasses();
}
$settings['js']['bodyClasses'] = implode(' ', $bodyClasses);

/* * **************************** LOAD ACTIONS SWITCHES ************************** */


$renderStyle = 'public';
$action      = app::request()->action;

// Paths here are relative to the CURRENT directory, and at this point that is
// the system dir (index.php enters it via fs::openSysDir() before including
// loader.php) — not the project root. Everything the project owns therefore
// has to be addressed through fs::$projectRoot.
$projectLib = $pageNav->relativeTo((string) fs::$projectRoot . 'lib');
if (fs::openDir($projectLib)) {
	if (is_file('config.php')) {
		include 'config.php';
	}
	if (is_file('actions.php')) {
		include 'actions.php';
	}
	fs::closeDir(' include site lib config and actions.');
}


// Per-template config/actions, addressed through the same folder the view
// resolver uses, so a template can hook the request before anything renders.
$templateFolder = $route->getViewResolver()->templateFolderOf($route->getSelected());
if ($templateFolder !== null && fs::openDir($pageNav->relativeTo($templateFolder))) {
	if (is_file('config.php')) {
		include 'config.php';
	}
	if (is_file('actions.php')) {
		include 'actions.php';
	}
	fs::closeDir(' include template config and actions.');
}

/* * **************************** RENDER ************************** */


$domRenderer->beginRender();
$domRenderer->setBodyClasses($bodyClasses);
$pageNav->setActive($route->getRoot());

/**
 * One canonical opener for every branch: openRoot('document').
 * Different entry points would emit different layer counts and break the
 * client's index-based child walk — see panel-diff-rendering.md §6b.
 */
$renderDocument = static function () use ($pageNav): void {
	if ($pageNav->openRoot('document')) {
		$pageNav->getActive()->render('document.tpl.php');
		$pageNav->close();
	} else {
		d($pageNav->debugInfo());
	}
};

if (http::$method === 'PUT') {
	// Commands only — a PUT never re-renders the page.
	scripttime('render off -- begin flushresponse');
	header('Content-type: application/json');
	echo json_encode(client::flushResponse());
} elseif (defined('OUTPUT_FORMAT') && OUTPUT_FORMAT === 'json') {
	// CLI / JSON probe. With "ajax": true this is the diff, otherwise the
	// initial layer render that becomes the reference for it.
	$isDiff = http::ajax();
	$isDiff ? $pageNav->beginDiff() : $pageNav->beginLayers();
	$renderDocument();
	$isDiff ? $pageNav->endDiff() : $pageNav->endLayers();
	$domRenderer->storeReference();
	ob_clean();

	$jsonResponse = [
		'status'   => 'ok',
		'renderer' => $pagesRenderer->exportJson(),
		'client'   => clientInterfaces::clientData_export(),
		'commands' => client::$response,
	];
	if (DEBUGRENDER) {
		// box-tree data (Debug\BoxTree) - the same the debug prompts draw
		$jsonResponse['pageTree']  = $route->debug();
		$jsonResponse['domLayers'] = $pagesRenderer->debug();
	}
	if (class_exists(\systopic\debug\sysdebug::class, false)) {
		\systopic\debug\sysdebug::setAppResponse($jsonResponse);
	}
} elseif (http::ajax() && http::$method === 'GET') {
	scripttime('begin render diff');
	$pageNav->beginDiff();
	$renderDocument();
	$pageNav->endDiff();
	$domRenderer->storeReference();

	client::updateDocument();
	scripttime('begin flushresponse');
	header('Content-type: application/json');
	echo json_encode(client::flushResponse());
} else {
	scripttime('begin render layers');
	$pageNav->beginLayers();
	$renderDocument();
	$pageNav->endLayers();
	$domRenderer->storeReference();
	ob_clean();

	// The served document is a skeleton: the static head loader plus two
	// placeholders. head and body content travel as layer JSON and are built
	// by pagesRenderer.js, so the first paint runs through the same client
	// path as every later diff. (Server-rendered HTML for crawlers is a
	// separate, later step.)
	echo '<!DOCTYPE html>' . "\n";
	echo '<html lang="' . htmlspecialchars((string) $lang, ENT_QUOTES) . '">' . "\n";
	echo "<head>\n";
	$headLoader = $route->getViewResolver()->resolveFile('head.loader.tpl.php');
	if ($headLoader !== null) {
		fs::includeFile($headLoader);
	}
	echo '<template id="systemPagesHeadPlaceholder"></template>' . "\n";
	if (!defined('JSOFF')) {
		$clientData = json_encode(clientInterfaces::clientData_export());
		if (json_last_error() === JSON_ERROR_NONE) {
			echo '<script>sys.lib.updateClientData(' . $clientData . ');</script>' . "\n";
		} else {
			d('Error #' . json_last_error() . ' ' . json_last_error_msg() . ' while encoding JSON');
		}
	}
	echo "</head>\n";
	// A distinct id from the panel app's systemDomRendererPlaceholder on
	// purpose: loader.js hydrates that one from sys.singletons.dom.renderer,
	// which is empty on a pages request. The pages tree is mounted by
	// pagesRenderer.js, triggered by the onLibReady hook in
	// PagesRenderer::clientData_export().
	echo '<body class="' . htmlspecialchars(strtolower(implode(' ', $bodyClasses)), ENT_QUOTES) . '" id="top">';
	echo '<div id="systemPagesBodyPlaceholder"></div>';
	echo "</body>\n</html>";
}

if (DEBUGRENDER) {
	// data, drawn by the debug tool - see Debug\BoxTree
	p(...$route->debug())->target('panelTreeDebug')->type('boxTree');
	p(...$pagesRenderer->debug())->target('domLayersServer')->type('boxTree');
}
