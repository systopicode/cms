<?php
/**
 * Tags on this medium — the page sidebar's tags, aimed at the media node.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Editor\File\Panel $this
 */

use Systopic\System\Queries\TagGroups\TagGroups;
use Systopic\System\Queries\TagGroups\TagGroupsPanel;
use Systopic\System\Queries\TagLinks\TagLinks;

$pages = $this->pages();
$nodeId = $this->targetId;
if ($nodeId < 2 || $pages->page === null) {
	return;
}
echo TagGroupsPanel::forPage(
	TagGroups::load($pages->scope),
	$nodeId,
	TagLinks::tagIdsOf($pages->scope, $nodeId),
	TagGroups::relatedNodes($pages->scope, (string) $pages->page->language),
	'../../sidebar/',
	'../../?id=',
);
