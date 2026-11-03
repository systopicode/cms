<?php

/** @var \Systopic\Cms\Panels\Root\Admin\Settings\Page\Edit\Panel $this */

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
	echo '<input' . ($name === 'name' ? ' data-setting-key pattern="[A-Za-z_][A-Za-z0-9_]*" required title="Use letters, digits and underscores; start with a letter or underscore."' : '') . ' name="' . $e($name) . '" value="' . $e($value) . '" autocomplete="off">';
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
		<header class="tagEditTitle">
			<h1><?= $d ? $e($d->label !== '' ? $d->label : 'Untitled') : 'Setting' ?></h1>
			<?php if ($d) { ?>
                <button type="button" class="tagGroupRename" onclick="renameUserAdminTitle(this)" data-rename-field="label" data-rename-url="<?= $e($this->href . 'updateSetting?id=' . (int) $d->nodeId) ?>" title="Rename" aria-label="Rename"><i class="cmsIcon cmsIcon-pen"></i></button>
            <?php } ?>
			<?php
			$back = '../';
			if ($d?->parent) {
				$back = '../?id=' . (int) $d->parent->nodeId;
			}
			?>
            <ul class="barmenu">
                <?php if ($d && $d->options === []) { ?><li><button type="button" class="button icononly" data-on_click="userConfirm" data-action="removeSetting?id=<?= (int) $d->nodeId ?>" data-confirm="Delete this setting?" title="Delete" aria-label="Delete"><i class="cmsIcon cmsIcon-trash"></i></button></li><?php } ?>
                <li><a class="ajax" href="<?= $e($back) ?>" aria-label="Close"><i class="cmsIcon cmsIcon-xmark-large"></i></a></li>
            </ul>
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
					$field('updateSetting', 'name', 'Key', $feld($d, 'name'), 'Used by templates and stored settings. Renaming does not update references automatically.');
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
					$field('updateSetting', 'description', 'Description', $feld($d, 'description'), '', 'is-wide');
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
							echo '<label><input class="switch" type="checkbox" name="on" data-on_change="updateTemplates" data-template="' . $e($name) . '"' . $checked . '><div><div></div></div><span>' . $e($shown) . '</span></label>';
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
