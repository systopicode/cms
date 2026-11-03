<?php
/**
 * Tags of the file being uploaded or replaced. Same chips as the page sidebar.
 * A new file has no node yet: the chosen ids wait on the panel and are linked
 * once the medium exists.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Upload\Panel $this
 */

use Systopic\System\Queries\TagGroups\TagGroups;
use Systopic\System\Queries\TagGroups\TagGroupsPanel;
use Systopic\System\Queries\TagLinks\TagLinks;

$pages = $this->parent;
if ($pages->page === null) {
	return;
}
$nodeId = $this->nodeId;
$pending = array_map('intval', (array) ($this->state->pendingTags ?? []));
$active = $nodeId >= 2
	? TagLinks::tagIdsOf($pages->scope, $nodeId)
	: $pending;
echo TagGroupsPanel::forPage(
	TagGroups::load($pages->scope),
	$nodeId >= 2 ? $nodeId : 0,
	$active,
	TagGroups::relatedNodes($pages->scope, (string) $pages->page->language),
	'',
	'../?id=',
	'?node_id=' . ($nodeId >= 2 ? $nodeId : 0),
);
