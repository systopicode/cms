<?php
/**
 * The media library of cms/pages — order, groups, moving, deleting.
 *
 * Dispatch only: the medium comes from the page panel (`mediaOfNode()`), the
 * rows from `Tables\Medias\Operator`, deleting from `Tables\Medias\Removal`.
 *
 * Dropped with the ORM round: `moveMediaAliasToPage` (it returned before its
 * own code — the drop target stays and does nothing, as before) and
 * `loadAllMMLAttatchments` (empty).
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Mml\Panel|\Systopic\System\Panels\PanelNode $this
 */

use Systopic\System\Tables\Medias\Operator as MediaOperator;
use Systopic\System\Tables\Medias\Removal as MediaRemoval;

/** @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $pages */
$pages = $this->parent;

/** Save what an operator returned, stamp the medium, redraw library and preview. */
$save = static function (array $rows, ?\Systopic\System\Tables\Medias\Model $media) use ($pages): void {
	if ($media !== NULL) {
		MediaOperator::touch($media, $pages->userId());
		$rows[] = $media;
	}
	$uow = $pages->uow();
	foreach ($rows as $row) {
		$uow->persist($row);
	}
	$uow->commit();
	$pages->updateView('mml');
	$pages->updateView('main');
};

switch ($this->action) {
	case 'updateMediaGroup':     // dropped on a group title: to the end of it
	case 'insertMediaBefore':    // dropped on the left edge of another medium
	case 'insertMediaAfter':     // … on its right edge
		$media = $pages->mediaOfNode((int) http::dragdata('node_id'));
		if ($media === NULL) {
			break;
		}
		$targetNode = (int) http::dataset('node_id');
		$target = $this->action === 'updateMediaGroup' ? NULL : $pages->mediaOfNode($targetNode);
		// parent and group of the drop: the target medium's, else the dataset's, else the page on screen
		$parentId = (int) ($target?->node?->relationId('parentNode') ?? http::dataset('node_parent_node_id') ?: ($pages->state->id ?? 0));
		$group = (string) ($target?->group ?? http::dataset('media_group') ?: 'main');
		if ($parentId <= 0) {
			break;
		}
		$rows = MediaOperator::placed(
			$pages->scope, $media, $parentId, $group,
			$this->action === 'insertMediaBefore' ? $targetNode : NULL,
			$this->action === 'insertMediaAfter' ? $targetNode : NULL,
		);
		$save($rows, $media);
		break;

	case 'moveMediaToPage':      // dropped on a page in the tree: to the end of its group
		$media = $pages->mediaOfNode((int) http::dragdata('node_id'));
		$pageId = (int) http::dataset('node_id');
		if ($media === NULL || $pageId <= 0) {
			break;
		}
		$save(MediaOperator::placed($pages->scope, $media, $pageId, (string) ($media->group ?: 'main')), $media);
		$pages->updateView('tree');
		break;

	case 'deleteMedia':
		$media = $pages->mediaOfNode((int) http::get('node_id'));
		if ($media !== NULL) {
			MediaRemoval::remove($pages->scope, $media);
		}
		client::callFunction('closeAllMenus');
		$pages->updateView('mml');
		$pages->updateView('main');
		break;

	case 'getMmlContextmenu':
		$nodeId = (int) http::dataset('node_id');
		$media = $pages->mediaOfNode($nodeId);
		if ($media !== NULL) {
			ob_start();
			include __DIR__ . '/contextmenu.tpl.php';
			client::contextmenu((string) ob_get_clean());
		}
		break;

	case 'loadMMLAttatchments':
		$pages->updateView('mml');
		$pages->updateView('main');
		client::callFunction('closeAllMenus');
		break;

	case 'updateFooterHeight':
		$pages->state->pylonFooterHeight = http::put()->height;
		break;
}
