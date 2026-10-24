<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Settings\Page\Edit\Panel $this */

use Systopic\Cms\Queries\SettingTree\Definition;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$d = $this->definition;

$feld = static function (?Definition $d, string $name): string {
	if ($d === null) {
		return '';
	}
	return match ($name) {
		'name' => (string) $d->setting->name,
		'label' => (string) $d->setting->label,
		'description' => (string) ($d->setting->description ?? ''),
		'type' => (string) $d->setting->type,
		'datatype' => (string) $d->setting->datatype,
		default => '',
	};
};

$vorgabe = static function (?Definition $d, string $name): string {
	$j = $d?->defaultJunction;
	if ($j === null) {
		return '';
	}
	return match ($name) {
		'varchar' => (string) ($j->varchar ?? ''),
		'int' => $j->int === null ? '' : (string) $j->int,
		'decimal' => $j->decimal === null ? '' : (string) $j->decimal,
		default => '',
	};
};

$field = static function (string $action, string $name, string $label, string $value, string $hint = '', string $wide = '') use ($e): void {
	echo '<label class="field' . ($wide !== '' ? " $wide" : '') . '" data-on_input="' . $action . '">';
	echo '<span>' . $e($label) . '</span>';
	echo '<input name="' . $e($name) . '" value="' . $e($value) . '" autocomplete="off">';
	if ($hint !== '') {
		echo '<small>' . $e($hint) . '</small>';
	}
	echo '</label>';
};

$choice = static function (string $name, string $label, string $value, array $options, string $hint = '') use ($e): void {
	echo '<label class="field"><span>' . $e($label) . '</span>';
	echo '<select name="' . $e($name) . '" data-on_change="updateSetting">';
	if ($value !== '' && !array_key_exists($value, $options)) {
		$options = [$value => $value] + $options;
	}
	foreach ($options as $key => $text) {
		$selected = (string) $key === $value ? ' selected' : '';
		echo '<option value="' . $e($key) . '"' . $selected . '>' . $e($text) . '</option>';
	}
	echo '</select>';
	if ($hint !== '') {
		echo '<small>' . $e($hint) . '</small>';
	}
	echo '</label>';
};
?>
<div class="cmslightbox cms pageSettingDialog">
	<section data-doc="box">
		<header>
			<h1><?= $d ? $e($d->label !== '' ? $d->label : 'Untitled') : 'Setting' ?></h1>
			<?php
			$back = '../';
			if ($d?->parent) {
				$back = '../?id=' . (int) $d->parent->nodeId;
			}
			echo \menu::create('barmenu')->link($back)->icon('fa-regular fa-xmark-large');
			?>
		</header>
		<?php if ($d) { ?>
			<main>
				<p class="tagsHint"><?php
					echo match ((string) ($d->parent?->type ?? '')) {
						'select' => 'An option is one choice of its select. Name and label are what the sidebar shows in the list. The choice itself is which option a page points at.',
						'group' => 'A field inside a group stores its own value. Name is what a template reads. Label is what people see. Value type picks the default.',
						'checkbox' => 'A checkbox has one value. The sidebar stores whether the page has it switched on.',
						default => 'Name is what a template reads. Label is what people see in the sidebar. Kind is how it behaves. A group, select or checkbox holds what belongs to it one level down: close this dialog and open the row with the folder.',
					};
				?></p>
				<div class="fieldGrid">
					<?php
					$field('updateSetting', 'name', 'Name', $feld($d, 'name'), 'Template key');
					$field('updateSetting', 'label', 'Label', $feld($d, 'label'), 'Sidebar text');
					$field('updateSetting', 'description', 'Description', $feld($d, 'description'), '', 'is-wide');
					$parentKind = (string) ($d->parent?->type ?? '');
					$kind = $feld($d, 'type');
					$kinds = match ($parentKind) {
						'select' => ['option' => 'Option of a select'],
						'group', 'checkbox' => ['input' => 'Field'],
						default => [
							'input' => 'Field',
							'group' => 'Group of fields',
							'select' => 'Select',
							'checkbox' => 'Checkbox',
						],
					};
					if ($kind === '' && isset($kinds['input'])) {
						$kind = 'input';
					}
					if (count($kinds) === 1) {
						$only = array_key_first($kinds);
						echo '<label class="field"><span>Kind</span>';
						echo '<input value="' . $e($kinds[$only]) . '" readonly>';
						echo '<small>' . $e(match ($parentKind) {
							'select' => 'A select can only contain options.',
							'group' => 'A group can only contain fields.',
							'checkbox' => 'A checkbox can only contain its value.',
							default => '',
						}) . '</small></label>';
					} else {
						$choice('type', 'Kind', $kind, $kinds, 'Field stores a value. Group, select and checkbox hold children.');
					}
					$isField = $kind === '' || $kind === 'input' || $parentKind === 'group' || $parentKind === 'checkbox';
					$datatype = $feld($d, 'datatype') !== '' ? $feld($d, 'datatype') : 'varchar';
					if ($isField) {
						$choice('datatype', 'Value type', $datatype, [
							'varchar' => 'Text',
							'int' => 'Number',
							'decimal' => 'Decimal',
						], 'Which default the sidebar uses.');
						if ($datatype === 'int') {
							$field('updateDefault', 'int', 'Default', $vorgabe($d, 'int'), 'Used when a page has not set a number.');
						} elseif ($datatype === 'decimal') {
							$field('updateDefault', 'decimal', 'Default', $vorgabe($d, 'decimal'), 'Used when a page has not set a decimal.');
						} else {
							$field('updateDefault', 'varchar', 'Default', $vorgabe($d, 'varchar'), 'Used when a page has not set a text.');
						}
					}
					if ($d->parent === null) {
						$picked = array_values(array_filter(explode(';', (string) ($d->setting->showInTemplates ?? ''))));
						$templates = (array) \template::getAll();
						uasort($templates, static fn($a, $b) => strnatcmp((string) ($a->prefix ?? ''), (string) ($b->prefix ?? '')));
						echo '<div class="templatePick">';
						echo '<span>Templates</span>';
						if ($templates === []) {
							echo '<small>No page templates found.</small>';
						}
						foreach ($templates as $name => $template) {
							$checked = in_array((string) $name, $picked, true) ? ' checked' : '';
							$shown = trim(str_replace(['_', '-'], ' ', (string) ($template->name ?? $name)));
							$tick = 'show-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $name);
							echo '<div>';
							echo '<input class="custom" type="checkbox" id="' . $e($tick) . '" name="on" data-on_change="updateTemplates" data-template="' . $e($name) . '"' . $checked . '>';
							echo '<label for="' . $e($tick) . '"></label>';
							echo '<label for="' . $e($tick) . '"><span>' . $e($shown) . '</span></label>';
							echo '</div>';
						}
						echo '<small>No tick means this setting is on every template.</small>';
						echo '</div>';
					}
					?>
				</div>
			</main>
		<?php } ?>
	</section>
</div>
