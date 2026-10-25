<?php
/**
 * Sidebar: eine Tab-Reihe (Inhalt, SEO, Veröffentlichung), darunter Accordions.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */
$page = $this->parent->page ?? null;
if ($page === null || $page->row === null || $this->groups === []) {
	return;
}
$activeGroup = $this->activeGroup();
$collapsed = !empty($this->state->sidebarCollapsed);
$width = $collapsed ? 0 : ((int) $this->state->sidebarWidth ?: 280);
$manage = [
	'tags' => ['cms/settings/tags/', 'Manage tags'],
	'settings' => ['cms/settings/pagesettings/', 'Manage page settings'],
];
?>
<aside class="cms<?= $collapsed ? ' collapsed' : '' ?>" style="flex: 0 0 <?= $width ?>px" data-cols="1" data-items="1">
	<a class="sidebarFlag put ajax" href="sidebar/toggleSidebar" draggable="false" title="<?= $collapsed ? 'Show sidebar' : 'Hide sidebar' ?>">
		<i class="fa-regular fa-chevron-<?= $collapsed ? 'left' : 'right' ?>"></i>
	</a>
	<?php if (!$collapsed) { ?>
		<nav class="sidebarTabs">
			<button type="button" class="tabScroll prev" data-tab-scroll="-1" title="Back" hidden><i class="fa-regular fa-chevron-left"></i></button>
			<button type="button" class="tabScroll next" data-tab-scroll="1" title="Forward" hidden><i class="fa-regular fa-chevron-right"></i></button>
			<ul>
				<?php foreach ($this->groups as $groupName => $group) { ?>
					<li><a class="put ajax<?= $groupName === $activeGroup ? ' selected' : '' ?>" href="sidebar/showGroup" data-group="<?= htmlspecialchars((string) $groupName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $group->label, ENT_QUOTES, 'UTF-8') ?></a></li>
				<?php } ?>
			</ul>
		</nav>
		<div class="flexContainer">
			<div class="scrollContainer">
				<div class="scrollContainerContent" data-node_id="<?= (int) $page->id ?>">
					<?php
					$sectionIndex = 0;
					foreach ($this->groups[$activeGroup]->templates as $templateName) {
						$sideBarItem = $this->templates[$templateName] ?? null;
						if ($sideBarItem === null) {
							continue;
						}
						$sectionIndex++;
						?>
						<details class="sidebarSection" data-section="<?= htmlspecialchars((string) $templateName, ENT_QUOTES, 'UTF-8') ?>" open>
							<summary>
								<?= htmlspecialchars((string) $sideBarItem->label, ENT_QUOTES, 'UTF-8') ?>
								<?php if ($templateName === 'meta') { ?>
									<a class="ajax sidebarInfo" href="sidebar/defaultMeta/" title="Default values"><i class="fa-regular fa-circle-info"></i></a>
								<?php } elseif (isset($manage[$templateName])) { ?>
									<a class="ajax sidebarInfo" href="<?= http::$root . $manage[$templateName][0] ?>" title="<?= $manage[$templateName][1] ?>"><i class="fa-regular fa-pen"></i></a>
								<?php } ?>
							</summary>
							<div class="sidebarItem">
								<?php $this->withView($sideBarItem->view, fn ($panel) => $panel->render($sideBarItem->filename)); ?>
							</div>
						</details>
					<?php } ?>
				</div>
			</div>
		</div>
		<div data-drag_type="none" data-on_draginit="draginitSidebarwidth"></div>
	<?php } ?>
</aside>
