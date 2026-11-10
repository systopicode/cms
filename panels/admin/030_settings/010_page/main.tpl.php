<?php

/**
 * One level of page settings: the path above the title, the list as one card.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Settings\Page\Panel $this
 */

use Systopic\Cms\Queries\SettingTree\Definition;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$selected = $this->selected;
$parentType = (string) ($selected->type ?? '');

$card = match ($parentType) {
	'select' => 'Choices',
	'group' => 'Fields',
	'checkbox' => 'Value',
	default => 'Page settings',
};
$note = match ($parentType) {
	'select' => 'Choices available in this field, in their display order.',
	'group' => 'Fields available inside this block, in their display order.',
	'checkbox' => 'Defines the value controlled by this switch. Enabled and disabled states are stored as 1 and 0.',
	default => 'Build the fields people can fill in under Settings in the page editor. Your templates decide how those values affect the website.',
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
	: 'Page Settings';
$addId = $selected ? (int) $selected->nodeId : 1;
$addLabel = match ($parentType) {
	'select' => 'Add option',
	'group' => 'Add field',
	'checkbox' => 'Add value',
	default => 'Add setting',
};
?>
<?php if ($selected !== null) { ?>
	<p class="settingBack"><a class="ajax button" href="?id=<?= $selected->parent ? (int) $selected->parent->nodeId : 1 ?>"><i class="cmsIcon cmsIcon-arrow-left"></i><span><?= $e($selected->parent?->label ?: 'Page Settings') ?></span></a></p>
<?php } ?>
<h1><?= $e($heading) ?></h1>
<p class="tagsHint"><?= $e($note) ?></p>
<section class="widget pageSettings">
	<header>
		<h2><?= $e($card) ?></h2>
		<a class="ajax button submit" data-focus-id="template-setting-add" href="<?= htmlspecialchars($this->href, ENT_QUOTES) ?>edit/?parent_id=<?= $selected ? (int) $selected->nodeId : 0 ?>">
			<i class="cmsIcon cmsIcon-plus"></i><span><?= $e($addLabel) ?></span>
		</a>
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
										echo '<a class="ajax" data-focus-id="setting-label-' . $id . '" href="' . $e($this->href) . 'edit/?id=' . $id . '">' . $e($label) . '</a>';
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

									<li><a class="ajax button icononly" data-focus-id="setting-edit-<?= $id ?>" title="Edit" href="<?= $e($this->href) ?>edit/?id=<?= $id ?>"><i class="cmsIcon cmsIcon-pen"></i></a></li>
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
