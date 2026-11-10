<?php
/**
 * Sidebar: eine Tab-Reihe (Inhalt, SEO, Veröffentlichung), darunter Accordions.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Sidebar\Panel $this
 */
if (!$this->parent->inPath()) return;
$page = $this->parent->page ?? null;
if ($page === null || $page->row === null || $this->groups === []) {
	return;
}
$activeGroup = $this->activeGroup();
$collapsed = !empty($this->state->sidebarCollapsed);
$width = $collapsed ? 0 : ((int) $this->state->sidebarWidth ?: 280);
$manage = [
	'tags' => ['admin/settings/tags/', 'Manage tags'],
	'settings' => ['admin/settings/page/', 'Manage template settings'],
	'permissions' => ['admin/access/content/', 'Manage content groups'],
];
?>
<aside class="cms<?= $collapsed ? ' collapsed' : '' ?>" style="flex: 0 0 <?= $width ?>px" data-cols="1" data-items="1">
	<a class="sidebarFlag put ajax" href="sidebar/toggleSidebar" draggable="false" title="<?= $collapsed ? 'Show sidebar' : 'Hide sidebar' ?>">
		<i class="cmsIcon cmsIcon-chevron-<?= $collapsed ? 'left' : 'right' ?>"></i>
	</a>
	<?php if (!$collapsed) { ?>
		<nav class="sidebarTabs" role="tablist">
			<button type="button" class="tabScroll prev" data-tab-scroll="-1" title="Back" hidden><i class="cmsIcon cmsIcon-chevron-left"></i></button>
			<button type="button" class="tabScroll next" data-tab-scroll="1" title="Forward" hidden><i class="cmsIcon cmsIcon-chevron-right"></i></button>
			<ul>
				<?php foreach ($this->groups as $groupName => $group) { ?>
					<li><a role="tab" aria-selected="<?= $groupName === $activeGroup ? 'true' : 'false' ?>" class="put ajax<?= $groupName === $activeGroup ? ' selected' : '' ?>" href="sidebar/showGroup" data-focus-id="pages-properties-tab-<?= htmlspecialchars((string) $groupName, ENT_QUOTES, 'UTF-8') ?>" data-group="<?= htmlspecialchars((string) $groupName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $group->label, ENT_QUOTES, 'UTF-8') ?></a></li>
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
								<span class="sidebarLabel"><?= htmlspecialchars((string) $sideBarItem->label, ENT_QUOTES, 'UTF-8') ?></span>
								<span class="sidebarTools">
									<?php if ($templateName === 'meta') { ?>
										<a class="ajax button transparent icononly small sidebarInfo" data-history="false" href="<?= htmlspecialchars($this->parent->href . 'sidebar/defaultMeta/', ENT_QUOTES) ?>" title="Default values" aria-label="Default values"><i class="cmsIcon cmsIcon-circle-info"></i></a>
									<?php } elseif ($templateName === 'relations') { ?>
                                        <a class="ajax button transparent icononly small sidebarInfo" data-history="false" href="<?= htmlspecialchars($this->parent->href . 'sidebar/relationsHelp/', ENT_QUOTES) ?>" title="About Aliases &amp; Redirects" aria-label="About Aliases &amp; Redirects"><i class="cmsIcon cmsIcon-circle-info"></i></a>
                                    <?php } elseif (isset($manage[$templateName])) { ?>
										<a class="ajax button transparent icononly small sidebarInfo" data-cms-open-sheet href="<?= http::$root . $manage[$templateName][0] ?>" title="<?= $manage[$templateName][1] ?>" aria-label="<?= $manage[$templateName][1] ?>"><i class="cmsIcon cmsIcon-pen"></i></a>
									<?php } ?>
									<i class="cmsIcon cmsIcon-chevron-down sidebarFold" aria-hidden="true"></i>
								</span>
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
