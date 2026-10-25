<?php

/**
 * cms/pages/sidebar — the actions.
 *
 * The page is the page panel's (`$this->parent->page`), written through the
 * page panel's unit of work; the paths go through `Tables\Pages\Rewritepaths`,
 * which keeps its lists current so the sidebar redraws without loading again.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */

use Systopic\Cms\Tables\Pages\Operator as PageOperator;
use Systopic\Cms\Tables\Rewritepaths\Operator as PathOperator;
use Systopic\Cms\Tables\Rewritepaths\Removal;
use Systopic\Cms\Queries\RewritepathLookup\RewritepathLookup;

/** @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $pages */
$pages = $this->parent;
$page = $pages->page;
$row = $page?->row;

/** Save rows through the page panel's unit of work. */
$save = static function (array $rows) use ($pages): void {
	$uow = $pages->uow();
	foreach ($rows as $r) {
		$uow->persist($r);
	}
	$uow->commit();
};

switch ($this->action) {
	case 'showGroup':
		$this->state->sidebarGroup = http::dataset('group');
		$this->state->sidebarCollapsed = false;
		$this->updateView('main');
		break;

	case 'toggleSidebar':
		$this->state->sidebarCollapsed = empty($this->state->sidebarCollapsed);
		$this->updateView('main');
		break;

	case 'clearSidebar':
	case 'closeSidebar':
		$this->state->activeSidebarTemplates = [];
		$this->updateView('main');
		$this->parent->updateView('controls');
		break;

	case 'addToSidebar':
		$active = array_reverse((array) $this->state->activeSidebarTemplates);
		$active[] = http::dataset('template');
		$this->state->activeSidebarTemplates = array_reverse($active);
		$this->updateView('main');
		$this->parent->updateView('controls');
		break;

	case 'removeFromSidebar':
		$key = array_search(http::dataset('template'), $this->state->activeSidebarTemplates);
		unset($this->state->activeSidebarTemplates[$key]);
		$this->updateView('main');
		$this->parent->updateView('controls');
		break;

	case 'updateWidth':
		$width = (int) http::put('width');
		if ($width >= 200) {
			$this->state->sidebarWidth = min($width, 900);
		}
		break;

	// -------------------------------------------------------- page fields

	case 'updateParam':
		if ($row === null) {
			break;
		}
		try {
			foreach ((array) http::values() as $name => $value) {
				PageOperator::assign($row, (string) $name, $value);
			}
		} catch (InvalidArgumentException $e) {
			message::error($e->getMessage());
			break;
		}
		PageOperator::touch($row, $pages->userId());
		$save([$row]);
		// Label and published are drawn by the tree — from this very row.
		$this->parent->updateView('tree');
		$this->parent->updateView('main');
		$this->refreshSeoPreview();
		break;

	case 'updateTemplate':
		if ($row === null) {
			break;
		}
		$row->templateName = (string) http::dataset('template_name');
		PageOperator::touch($row, $pages->userId());
		$save([$row]);
		$this->updateView('main');
		$this->parent->updateView('main');
		break;

	case 'updateShareImage':
		$mediaNodeId = (int) http::dragdata('node_id', 0);
		if ($row !== null && $mediaNodeId > 0 && http::dragdata('type', false) === 'image') {
			$row->metaShareImage = $mediaNodeId;
			$save([$row]);
			client::replaceInner('aside div.mediaDrop.shareImage', $this->shareImage());
			$this->refreshSeoPreview();
		}
		break;

	case 'resetShareImage':
		if ($row !== null) {
			$row->metaShareImage = null;
			$save([$row]);
			client::replaceInner('aside div.mediaDrop.shareImage', $this->shareImage());
			$this->refreshSeoPreview();
		}
		break;

	// ------------------------------------------------------------- paths

	case 'rewritePath_updateAutoPath':
		if ($row !== null) {
			$save($page->rewritepaths->rebuildAuto());
			$this->updateView('010_urls');   // the url list is its own view, 'main' alone keeps it
			$this->refreshSeoPreview();
		}
		break;

	case 'rewritePath_new':
		if ($row !== null) {
			$save([$page->rewritepaths->newCustom()]);
			$this->updateView('010_urls');   // the url list is its own view, 'main' alone keeps it
			$this->refreshSeoPreview();
		}
		break;

	case 'rewritePath_delete':
		$path = $page?->rewritepaths->byId((int) http::dataset('rewritepath_id'));
		if ($path !== null) {
			$page->rewritepaths->detach($path);
			Removal::remove($pages->scope, $path); // detaches the request log first
			$this->updateView('010_urls');   // the url list is its own view, 'main' alone keeps it
			$this->refreshSeoPreview();
		}
		break;

	case 'rewritePath_update':
		$path = $page?->rewritepaths->byId((int) http::dataset('rewritepath_id'));
		$value = (string) http::values('rewritePath');
		if ($path === null || $path->autoIndex !== null) {
			break;   // automatic paths are rebuilt, not typed
		}
		if (PathOperator::safeStr($value) !== $value) {
			break;   // invalid characters — wait for the next keystroke
		}
		// unique within the page's site (multi-site) — another site may use the same path
		$blocker = RewritepathLookup::blocker($pages->scope, $value, $row->id, $page->rewritepaths->siteNodeId);
		if ($blocker === null) {
			$path->string = $value;
			$save([$path]);
			$this->refreshSeoPreview();
		} else {
			$owner = RewritepathLookup::pageOf($pages->scope, (int) $blocker->id);
			$label = htmlspecialchars((string) ($owner?->label ?? '?'));
			$href = './?id=' . (int) $owner?->relationId('node') . '&lang=' . ($owner?->languageIso ?? '');
			message::error("<a class='ajax' href='$href'>not unique: $label</a>");
		}
		break;

	case 'rewritePath_setPrimary':
		$path = $page?->rewritepaths->byId((int) http::dataset('rewritepath_id'));
		if ($path !== null) {
			$save($page->rewritepaths->makePrimary($path));
			$this->updateView('010_urls');   // the url list is its own view, 'main' alone keeps it
			$this->refreshSeoPreview();
		}
		break;
}

include 'actions.settings.php';
include 'actions.tags.php';
