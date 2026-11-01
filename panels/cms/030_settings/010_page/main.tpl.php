<?php

/**
 * One level of page settings: the path above the title, the list as one card.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Settings\Page\Panel $this
 */

use Systopic\Cms\Queries\SettingTree\Definition;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$selected = $this->selected;
$parentType = (string) ($selected->type ?? '');

$card = match ($parentType) {
	'select' => 'Choices',
	'group' => 'Fields',
	'checkbox' => 'Value',
	default => 'Shown in the sidebar',
};
$note = match ($parentType) {
	'select' => 'Choices available in this field, in their display order.',
	'group' => 'Fields available inside this block, in their display order.',
	'checkbox' => 'Defines the value controlled by this switch. Enabled and disabled states are stored as 1 and 0.',
	default => 'Defines the settings available in the page sidebar.',
};
$columns = match ($parentType) {
	'select' => ['label', 'value'],
	'group', 'checkbox' => ['label', 'name', 'type'],
	default => ['label', 'about'],
};
$heads = [
	'label' => 'Label',
	'value' => 'Value',
	'name' => 'Name',
	'type' => 'Type',
	'about' => 'In the sidebar',
];
$chain = [];
for ($walker = $selected; $walker instanceof Definition; $walker = $walker->parent) {
	array_unshift($chain, $walker);
}
$ancestors = array_slice($chain, 0, -1);
$heading = $selected !== null
	? ($selected->label !== '' ? $selected->label : 'Untitled')
	: 'Template Settings';
$addId = $selected ? (int) $selected->nodeId : 1;
$addLabel = match ($parentType) {
	'select' => 'Add option',
	'group' => 'Add field',
	'checkbox' => 'Add value',
	default => 'Add setting',
};
?>
<?php if ($selected !== null) { ?>
	<nav class="crumbs settingCrumbs">
		<a class="ajax" href="?">Template Settings</a>
		<i class="cmsIcon cmsIcon-chevron-right"></i>
		<?php foreach ($ancestors as $crumb) { ?>
			<a class="ajax" href="?id=<?= (int) $crumb->nodeId ?>"><?= $e($crumb->label !== '' ? $crumb->label : 'Untitled') ?></a>
			<i class="cmsIcon cmsIcon-chevron-right"></i>
		<?php } ?>
	</nav>
<?php } ?>
<h1><?= $e($heading) ?></h1>
<p class="tagsHint"><?= $e($note) ?></p>
<section class="widget pageSettings">
	<header>
		<h2><?= $e($card) ?></h2>
		<button type="button" data-cms-action class="button submit" data-on_click="add" data-id="<?= $addId ?>">
			<i class="cmsIcon cmsIcon-plus"></i><span><?= $e($addLabel) ?></span>
		</button>
	</header>
	<?php if ($this->rows === []) { ?>
		<main><p>Nothing here yet.</p></main>
	<?php } else { ?>
		<div class="tableScroll">
			<table>
				<thead>
					<tr>
						<th></th>
						<?php foreach ($columns as $column) { ?>
							<th><?= $e($heads[$column]) ?></th>
						<?php } ?>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($this->rows as $row) {
						/** @var Definition $row */
						$id = (int) $row->nodeId;
						$kind = (string) $row->type;
						if ($parentType === 'select') {
							$kind = 'option';
						} elseif ($parentType === 'group' || $parentType === 'checkbox') {
							$kind = 'input';
						}
						$opens = $parentType === '' && in_array($kind, ['group', 'select', 'checkbox'], true);
						$label = $row->label !== '' ? $row->label : 'Untitled';
						$name = (string) $row->name;
						$typeLabel = match ($row->datatype) {
							'int' => 'Number',
							'decimal' => 'Decimal',
							default => 'Text',
						};
						$about = match ($kind) {
							'group' => 'A block of fields',
							'select' => 'One choice',
							'checkbox' => 'On or off',
							default => match ($row->datatype) {
								'int' => 'A number',
								'decimal' => 'A decimal',
								default => 'A text',
							},
						};
						?>
						<tr>
							<td class="pageSettingDrag">
								<i class="cmsIcon cmsIcon-grip-dots-vertical" aria-label="Drag to reorder"
									data-drag_type="setting" data-id="<?= $id ?>"></i>
								<div class="sort v insertBefore" data-drop_accept="setting" data-on_drop="insertBefore" data-id="<?= $id ?>"></div>
								<div class="sort v insertAfter" data-drop_accept="setting" data-on_drop="insertAfter" data-id="<?= $id ?>"></div>
							</td>
							<?php foreach ($columns as $column) { ?>
								<td><?php
									if ($column === 'label') {
										echo '<span class="settingLabel">';
										if ($opens) {
											echo '<a class="ajax" href="?id=' . $id . '">' . $e($label) . '</a>';
										} else {
											echo $e($label);
										}
										if ($parentType === '' && $name !== '') {
											echo '<small>' . $e($name) . '</small>';
										}
										echo '</span>';
									} elseif ($column === 'value' || $column === 'name') {
										echo $e($name);
									} elseif ($column === 'type') {
										echo $e($typeLabel);
									} else {
										echo '<span class="settingAbout">' . $e($about) . '</span>';
									}
								?></td>
							<?php } ?>
							<td class="icons">
								<ul>
									<?php if ($opens) { ?>
										<li><a class="ajax button icononly" title="Open" href="?id=<?= $id ?>"><i class="cmsIcon cmsIcon-folder-open"></i></a></li>
									<?php } ?>
									<li><a class="ajax button icononly" title="Edit" href="edit/?id=<?= $id ?>"><i class="cmsIcon cmsIcon-pen"></i></a></li>
									<?php if (!is_array($row->options) || $row->options === []) { ?>
										<li><button type="button" class="button transparent icononly" title="Delete" data-on_click="userConfirm" data-action="delete" data-id="<?= $id ?>" data-confirm="Delete this setting?"><i class="cmsIcon cmsIcon-trash"></i></button></li>
									<?php } ?>
								</ul>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
</section>
