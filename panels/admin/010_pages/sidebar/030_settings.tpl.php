<?php

/** @var \Systopic\Cms\Panels\Root\Admin\Pages\Sidebar\Panel $this */

use Systopic\Cms\Queries\SettingTree\Definition;
use Systopic\Cms\Queries\SettingTree\SettingTree;
use Systopic\Cms\Queries\SettingValues\SettingValues;

$page = $this->parent->page;
$pageNodeId = (int) $page->id;
$pageTemplate = (string) $page->templateName;
$scope = $this->parent->scope;
$values = SettingValues::of($scope, $pageNodeId);
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$isVisible = static function (Definition $definition) use (&$isVisible, $pageTemplate): bool {
    if ($definition->name === null || !in_array($definition->type, ['select', 'multi', 'checkbox', 'input', 'group'], true)) {
        return false;
    }
    $templates = array_filter(array_map('trim', explode(';', (string) $definition->setting->showInTemplates)), 'strlen');
    if ($templates && !in_array($pageTemplate, $templates, true)) {
        return false;
    }
    return $definition->type !== 'group' || array_any($definition->options, $isVisible);
};

$renderSetting = function (Definition $definition) use (&$renderSetting, $isVisible, $values, $page, $pageNodeId, $escape): void {
    $label = $escape($definition->label);
    $name = $escape($definition->name);
    $fieldId = 'page-setting-' . $definition->nodeId;
    $firstOption = $definition->options[0] ?? $definition;
    $settingVersion = static fn(int $field, array $ids): string => \Systopic\Cms\Pages\SettingVersion::attributes($page, $field, $ids);
    $renderCaption = static function () use ($label, $fieldId, $definition): void {
        ?>
            <h4><?php if ($definition->type === 'input' || ($definition->type === 'select' && $definition->options)) { ?>
                <label for="<?= $fieldId ?>"><?= $label ?></label>
            <?php } else { echo $label; } ?></h4>
        <?php
    };

    $description = trim((string) $definition->setting->description);
    if ($definition->type !== 'group') {
        echo '<div class="templateSettingField">';
    }

	switch ($definition->type) {
		case 'select':
			$renderCaption();
            if (!$definition->options) {
                echo '<small class="templateSettingEmpty">No options configured.</small>';
                break;
            }
			echo "<select id='$fieldId' name='item_node_id' data-on_change='sidebar/updateSelect'"
				. ' ' . $settingVersion((int) $definition->nodeId, array_map(static fn($option)=>(int) $option->nodeId, $definition->options)) . " data-node_parent_node_id='{$definition->nodeId}'>";
			echo "<option value=''>No selection</option>";
			foreach ($definition->options as $option) {
				// Gewaehlt ist, worauf eine Junction-Zeile zeigt — der Wert
				// spielt keine Rolle, die Verknuepfung selbst ist die Aussage.
				$selected = $values->has($option->nodeId) ? ' selected' : '';
				echo "<option$selected value='{$option->nodeId}'>".$escape($option->label).'</option>';
			}
			echo "</select>";
			break;

		case 'multi':
			$renderCaption();
			break;

		case 'checkbox':
			// Eine Checkbox hat genau ein Kind, und der Haken steht in dessen
			// junction_int. `value` ist nicht der Zustand, sondern das, was
			// beim naechsten Klick geschickt wird — also der umgekehrte.
			$junction = $firstOption === null ? null : $values->junction($firstOption->nodeId);
			$int = $junction?->int;
			$value = $int ? 0 : 1;
			$checked = $int ? 'checked' : '';
			$itemNodeId = $firstOption?->nodeId ?? '';
			$name = $escape($values->key($definition->name) ?? '');
			echo "<div class='checkboxContainer'><label class='checkbox'>";
			echo "<span><b>{$label}</b></span>";
			echo "<input data-on_change='sidebar/updateValue' type='checkbox'"
				. ' ' . $settingVersion((int) $itemNodeId, [(int) $itemNodeId]) . " data-setting_node_id='$itemNodeId' $checked value='$value' name='$name'"
				. " class='switch tinyswitch'>";
			echo "<div><div></div></div></label></div>";
			break;

		case 'input':
			// Hier faellt die Vorgabe zu Recht ein: ein leeres Feld soll den
			// Wert zeigen, der ohne Zutun gilt.
			$itemNodeId = $firstOption?->nodeId ?? '';
			$renderCaption();
			echo "<input id='$fieldId' data-on_blur='sidebar/updateValue' value='{$escape($values->valueOf($definition))}'"
				. ' ' . $settingVersion((int) $itemNodeId, [(int) $itemNodeId]) . " type='text' name='{$name}' data-setting_node_id='$itemNodeId'  >";
			break;

        case 'group':
            ?>
            <details class="templateSettingSection" data-setting-section="<?= $definition->nodeId ?>">
                <summary data-focus-id="setting-section-<?= $definition->nodeId ?>"><h6 class="taggroup"><?= $label ?></h6><i class="cmsIcon cmsIcon-chevron-down" aria-hidden="true"></i></summary>
                <?php if ($description !== '') { ?>
                    <small class="templateSettingDescription"><?= $escape($description) ?></small>
                <?php } ?>
                <?php foreach (array_filter($definition->options, $isVisible) as $child) $renderSetting($child); ?>
            </details>
            <?php
            break;
    }
    if ($definition->type !== 'group') {
        if ($description !== '') {
            echo '<small class="templateSettingDescription">' . $escape($description) . '</small>';
        }
        echo '</div>';
    }
};

$definitions = array_filter(SettingTree::load($scope), $isVisible);
if (!$definitions) {
    echo '<small class="templateSettingEmpty">No settings for this template.</small>';
}
$groups = array_filter($definitions, static fn(Definition $definition): bool => $definition->type === 'group');
$fields = array_diff_key($definitions, $groups);
if ($fields && $groups) { ?>
    <details class="templateSettingSection" data-setting-section="general" open>
        <summary data-focus-id="setting-section-general"><h6 class="taggroup">General settings</h6><i class="cmsIcon cmsIcon-chevron-down" aria-hidden="true"></i></summary>
        <?php foreach ($fields as $definition) $renderSetting($definition); ?>
    </details>
<?php } else {
    foreach ($fields as $definition) $renderSetting($definition);
}
foreach ($groups as $definition) $renderSetting($definition);
