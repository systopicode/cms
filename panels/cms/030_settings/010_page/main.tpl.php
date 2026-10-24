<?php

/**
 * One level of page settings. The path sits in the controls header, the same
 * way a page title does. This list is one card.
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
	'select' => 'Each row is one choice. The pen changes it. Drag the handle to change the order.',
	'group' => 'Each row is one field in this block. The pen changes it. Drag the handle to change the order.',
	'checkbox' => 'This is the value the switch stores. The pen changes it.',
	default => 'Open a row to see what belongs to it. The pen changes the row. Drag the handle to change the order.',
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
<h1><?= $e($heading) ?></h1>
<p class="tagsHint"><?= $e($note) ?></p>
<section class="widget pageSettings">
	<header>
		<h2><?= $e($card) ?></h2>
		<a class="button submit" data-on_click="add" data-id="<?= $addId ?>">
			<i class="fa-regular fa-plus"></i><span><?= $e($addLabel) ?></span>
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
								<i class="fa-regular fa-grip-vertical" title="Drag to reorder"
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
										<li><a class="ajax button icononly" title="Open" href="?id=<?= $id ?>"><i class="fa-regular fa-folder-open"></i></a></li>
									<?php } ?>
									<li><a class="ajax button icononly" title="Edit" href="edit/?id=<?= $id ?>"><i class="fa-regular fa-pen"></i></a></li>
									<li><a class="ajax button icononly" title="Delete" href="delete/?id=<?= $id ?>"><i class="fa-regular fa-trash"></i></a></li>
								</ul>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
</section>
