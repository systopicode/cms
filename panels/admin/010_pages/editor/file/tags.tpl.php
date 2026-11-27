<?php
/**
 * Tags on this medium — the page sidebar's tags, aimed at the media node.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Panel $this
 */

use Systopic\System\Queries\TagGroups\TagGroups;
use Systopic\System\Queries\TagGroups\TagGroupsPanel;
use Systopic\System\Queries\TagLinks\TagLinks;

$pages = $this->pages();
$nodeId = $this->targetId;
if ($nodeId < 2 || $pages->page === null) {
	return;
}
$groups = TagGroups::load($pages->scope);
if (!$groups) {
	echo '<p class="tagsHint">No tag groups configured.</p>';
	return;
}
echo TagGroupsPanel::forPage(
	$groups,
	$nodeId,
	TagLinks::tagIdsOf($pages->scope, $nodeId),
	TagGroups::relatedNodes($pages->scope, (string) $pages->page->language),
	'',
	$pages->href . '?id=',
	'?target_id=' . $nodeId,
);
