<?php
/** @var \Systopic\Cms\Panels\Root\Admin\Settings\Page\Panel $this */
use Systopic\Cms\Queries\SettingTree\SettingTree;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$base = $this->href;
$open = array_map('intval', (array) ($this->state->openFolds ?? []));
$render = function(array $rows, int $depth = 0) use (&$render, $e, $base, $open): void {
 foreach ($rows as $row) {
  $id = $row->nodeId; $group = $row->type === 'group'; $expanded = in_array($id,$open,true);
  $icon = $group ? 'folder' : 'sliders';
 ?>
 <tr data-setting-row="<?= $id ?>" data-setting-parent="<?= $row->parent?->nodeId ?? 0 ?>" data-setting-kind="<?= $group ? 'group' : 'field' ?>" class="<?= $depth ? 'settingChild' : '' ?> <?= $group ? ('settingGroup' . ($expanded ? ' is-expanded' : '')) : '' ?>" style="--setting-depth:<?= $depth ?>">
  <td class="pageSettingDrag"><i class="cmsIcon cmsIcon-grip-dots-vertical" aria-label="Drag to reorder" data-drag_type="setting" data-id="<?= $id ?>"></i><div class="sort v insertBefore" data-drop_accept="setting" data-on_drop="placeSetting" data-position="before" data-id="<?= $id ?>"></div><div class="sort v insertAfter" data-drop_accept="setting" data-on_drop="placeSetting" data-position="after" data-id="<?= $id ?>"></div><?php if ($group) { ?><div class="sort v insertInto" data-drop_accept="setting" data-on_drop="placeSetting" data-position="inside" data-id="<?= $id ?>"></div><?php } ?></td>
  <td class="settingType" data-table-inline><i class="cmsIcon cmsIcon-<?= $icon ?>" role="img" aria-label="<?= $group ? 'Group' : 'Setting' ?>"></i></td>
  <td class="settingName" data-table-subject><span class="settingRowLabel"><?= $e($row->label) ?></span></td>
  <td><?= $e($row->setting->description ?? '') ?></td>
  <td class="icons"><ul><li><a class="ajax button icononly" aria-label="Edit <?= $e($row->label) ?>" data-focus-id="setting-edit-<?= $id ?>" href="<?= $e($base) ?>edit/?id=<?= $id ?><?= $row->parent ? '&amp;from=list' : '' ?>"><i class="cmsIcon cmsIcon-pen"></i></a></li><?php if ($group) { ?><li><button type="button" class="button icononly" data-focus-id="setting-fold-<?= $id ?>" data-on_click="toggleGroup" data-id="<?= $id ?>" aria-expanded="<?= $expanded ? 'true' : 'false' ?>" aria-label="<?= $expanded ? 'Collapse' : 'Expand' ?> <?= $e($row->label) ?>"><i class="cmsIcon cmsIcon-chevron-<?= $expanded ? 'up' : 'down' ?>"></i></button></li><?php } else { ?><li class="settingFoldSpace" aria-hidden="true"></li><?php } ?></ul></td>
 </tr>
 <?php if ($group && $expanded) { ?>
 <tr class="settingChildrenRow"><td colspan="5"><div class="tableScroll settingChildrenTable"><table><thead><tr><th></th><th aria-label="Type"></th><th>Label</th><th>Description</th><th></th></tr></thead><tbody><?php $render($row->options,0); ?></tbody></table><?php if (!$row->options) { ?><p class="tagsHint">This group is empty. Drop a setting here or <a class="ajax" href="<?= $e($base) ?>edit/?parent_id=<?= $id ?>">add a setting</a>.</p><?php } ?></div></td></tr>
 <?php } }

};
?>
<h1><?= $e($this->label) ?></h1>
<p class="tagsHint">Create fields for the Settings area in Pages. Groups keep related fields together. Your templates decide how these values affect the website.</p>
<section class="widget pageSettings">
 <header class="settingCreateActions"><a class="ajax button" href="<?= $e($base) ?>edit/?kind=group" data-focus-id="template-group-add"><i class="cmsIcon cmsIcon-folder"></i><span>Add group</span></a><a class="ajax button submit" href="<?= $e($base) ?>edit/?parent_id=0" data-focus-id="template-setting-add"><i class="cmsIcon cmsIcon-plus"></i><span>Add setting</span></a></header>
 <div class="tableScroll"><table><thead><tr><th></th><th aria-label="Type"></th><th>Label</th><th>Description</th><th></th></tr></thead><tbody><?php $render(SettingTree::load($this->scope)); ?></tbody></table></div>

</section>
