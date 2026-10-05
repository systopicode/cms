<?php
/**
 * The tags of the page — every group, a click toggles.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */

use Systopic\System\Queries\TagGroups\TagGroups;
use Systopic\System\Queries\TagGroups\TagGroupsPanel;
use Systopic\System\Queries\TagLinks\TagLinks;

$page = $this->parent->page;
$scope = $this->parent->scope;
?>
<h3>Tags</h3>
<?php
echo TagGroupsPanel::forPage(
	TagGroups::load($scope),
	$page->id,
	TagLinks::tagIdsOf($scope, $page->id),
	TagGroups::relatedNodes($scope, $page->language),
);
