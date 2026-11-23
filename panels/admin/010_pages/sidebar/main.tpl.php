<?php
/**
 * Sidebar: a row of tabs (the groups), below it the folds of the open one
 * (its sections). Both are panels below this one (panel.php), opened here
 * by name: the tab chosen is sidebar state, a fold's open/closed its own.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Sidebar\Panel $this
 */
if (!$this->parent->inPath()) return;
$page = $this->parent->page ?? null;
$groups = $this->groups();
if ($page === null || $page->row === null || $groups === []) {
	return;
}
$activeGroup = $this->activeGroup();
$collapsed = !empty($this->state->sidebarCollapsed);
$width = $collapsed ? 0 : ((int) $this->state->sidebarWidth ?: 280);
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$manage = [
	'tags' => ['admin/settings/tags/', 'Manage tags'],
	'settings' => ['admin/settings/page/', 'Manage template settings'],
	'permissions' => ['admin/access/content/', 'Manage content groups'],
];
$toggleHref = $this->href . 'toggleSection';
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
				<?php foreach ($groups as $groupName => $group) { ?>
					<li><a role="tab" aria-selected="<?= $groupName === $activeGroup ? 'true' : 'false' ?>" class="put ajax<?= $groupName === $activeGroup ? ' selected' : '' ?>" href="sidebar/showGroup" data-focus-id="pages-properties-tab-<?= $e($groupName) ?>" data-group="<?= $e($groupName) ?>"><?= $e($group->label) ?></a></li>
				<?php } ?>
			</ul>
		</nav>
		<div class="flexContainer">
			<div class="scrollContainer">
				<div class="scrollContainerContent" data-node_id="<?= (int) $page->id ?>">
					<?php
					$this->withChild($activeGroup, 'sections', function ($group) use ($e, $manage, $toggleHref): void {
						foreach ($group->sections() as $section) {
							$name = $section->name;
							?>
							<details class="sidebarSection" data-section="<?= $e($name) ?>" data-toggle-href="<?= $e($toggleHref) ?>" data-group="<?= $e($group->name) ?>"<?= empty($section->state->open) ? '' : ' open' ?>>
								<summary>
									<span class="sidebarLabel"><?= $e($section->label) ?></span>
									<span class="sidebarTools">
										<?php if ($name === 'meta') { ?>
											<a class="ajax button transparent icononly small sidebarInfo" data-history="false" href="<?= $e($this->parent->href . 'sidebar/defaultMeta/') ?>" title="Default values" aria-label="Default values"><i class="cmsIcon cmsIcon-circle-info"></i></a>
										<?php } elseif ($name === 'relations') { ?>
	                                        <a class="ajax button transparent icononly small sidebarInfo" data-history="false" href="<?= $e($this->parent->href . 'sidebar/relationsHelp/') ?>" title="About Aliases &amp; Redirects" aria-label="About Aliases &amp; Redirects"><i class="cmsIcon cmsIcon-circle-info"></i></a>
	                                    <?php } elseif (isset($manage[$name])) { ?>
											<a class="ajax button transparent icononly small sidebarInfo" data-cms-open-sheet href="<?= http::$root . $manage[$name][0] ?>" title="<?= $manage[$name][1] ?>" aria-label="<?= $manage[$name][1] ?>"><i class="cmsIcon cmsIcon-pen"></i></a>
										<?php } ?>
										<i class="cmsIcon cmsIcon-chevron-down sidebarFold" aria-hidden="true"></i>
									</span>
								</summary>
								<div class="sidebarItem">
									<?php $group->withChild($name, 'main'); ?>
								</div>
							</details>
							<?php
						}
					});
					?>
				</div>
			</div>
		</div>
		<div data-drag_type="none" data-on_draginit="draginitSidebarwidth"></div>
	<?php } ?>
</aside>
