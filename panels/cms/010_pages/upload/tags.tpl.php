<?php
/**
 * Tags of the medium being replaced. Same panel as the page sidebar.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Upload\Panel $this
 */

use Systopic\System\Queries\TagGroups\TagGroups;
use Systopic\System\Queries\TagGroups\TagGroupsPanel;
use Systopic\System\Queries\TagLinks\TagLinks;

$pages = $this->parent;
$nodeId = $this->nodeId;
if ($nodeId < 2 || $pages->page === null) {
	return;
}
echo TagGroupsPanel::forPage(
	TagGroups::load($pages->scope),
	$nodeId,
	TagLinks::tagIdsOf($pages->scope, $nodeId),
	TagGroups::relatedNodes($pages->scope, (string) $pages->page->language),
	'',
	'../?id=',
	'?node_id=' . $nodeId,
);
