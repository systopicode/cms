<?php
/**
 * The sidebar next to the preview: one column per active item.
 *
 * Every item template reads the page from `$this->parent->page` — the
 * page panel's page, the same object the tree draws from.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */
if (empty($this->state->activeSidebarTemplates)) {
	return;
}
$items = count($this->state->activeSidebarTemplates);
$cols = min($items, floor($this->state->sidebarWidth / 250));
?>
<aside class=cms style='flex: 0 0 <?= $this->state->sidebarWidth ?>px' data-cols='<?= $cols ?>' data-items='<?= $items ?>'>
	<div class='flexContainer'>
		<div class="scrollContainer">
			<div class='scrollContainerContent' data-Xon_click='sidebar/update' data-node_id="<?= $this->parent->page?->id ?? '' ?>">
				<?php
				if ($this->parent->page?->row !== null) {
					foreach ($this->state->activeSidebarTemplates as $templateName) {
						$sideBarItem = $this->templates[$templateName] ?? null;
						if ($sideBarItem === null) {
							continue;
						}
						echo '<div class="sidebarItem">';
						$this->withView($sideBarItem->view, fn ($p) => $p->render($sideBarItem->filename));
						echo "</div>";
					}
				}
				?>
			</div>
		</div>
	</div>
	<a class='clearSidebar icon' data-on_click='sidebar/clearSidebar' title='Clear Sidebar'><i class="fa-light fa-xmark-large"></i></a>
	<div data-drag_type='none' data-on_draginit='draginitSidebarwidth'></div>
</aside>
