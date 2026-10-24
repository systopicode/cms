<?php

/**
 * Die Seitenleiste "Settings" — auf `Tables\Settings\Model`.
 *
 * Vorher lief das ueber `$module->page->settings`, also `pageSettings::get()`:
 * eine Abfrage ueber vier Ebenen mit zwei Selbstjoins, die die ganze
 * Deklaration je Seite neu liest. Jetzt sind es zwei Fragen und zwei
 * Abfragen — was es gibt, und was diese Seite davon gesetzt hat —, und die
 * erste faellt ab der zweiten Seite ganz weg, weil `Definitions` sie haelt.
 *
 * **Die Ausgabe ist byteweise dieselbe.** Das ist kein Selbstzweck: an den
 * Klassennamen und den `data-`Attributen haengt das JavaScript, und der
 * einzige Weg, einen Austausch des Unterbaus zu belegen, ist ein leerer Diff.
 * Gegengeprueft gegen `var/golden/sidebar-settings.node17.html` (Auswahl
 * getroffen, Haken gesetzt) und `.node18.html` (nichts gesetzt).
 *
 * Drei Eigenheiten des Bestands sind dabei mit uebernommen, weil sie sichtbar
 * sind und ihre Korrektur eine Entscheidung waere, keine Uebersetzung:
 *
 *   1. Ist der Haken nicht gesetzt, steht im `<input>` ein **doppeltes
 *      Leerzeichen** — dort, wo sonst `checked` steht.
 *   2. Dann ist auch `name=''` leer, weil der Name von der *gewaehlten*
 *      Option kommt und keine gewaehlt ist. Ausgewertet wird er nicht:
 *      `updateValue` laeuft `foreach (http::values() as $key => $value)` und
 *      benutzt `$key` nicht.
 *   3. Der Haken kommt aus der Junction-Zeile, **ohne Rueckfall auf die
 *      Vorgabe** — `SettingValues::stored()`, nicht `SettingValues::value()`. Genau das tut
 *      der Bestand auch, indem er `$setting->junction_int` direkt liest.
 *      Dass `pageSetting->value` etwas anderes sagt, steht in `SettingValues`.
 */

/** @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this */

use Systopic\Cms\Queries\SettingTree\SettingTree;
use Systopic\Cms\Queries\SettingValues\SettingValues;

$pageNodeId = (int) ($this->parent->page?->id ?? 0);
$definitions = SettingTree::load($this->parent->scope);
$values = SettingValues::of($this->parent->scope, $pageNodeId);
?>
<h3>Settings</h3>
<?php
$page = $this->parent->page ?? null;
$pageTemplate = $page ? (string) $page->templateName : '';
foreach ($definitions as $definition) {
	$only = array_values(array_filter(array_map('trim', explode(';', (string) ($definition->setting->showInTemplates ?? ''))), static fn(string $name): bool => $name !== ''));
	if ($only !== [] && !in_array($pageTemplate, $only, true)) {
		continue;
	}
	if ($definition->name === null) {
		// Eine Einstellung, die jemand angelegt und nie ausgefuellt hat. Der
		// Bestand lief hier in eine Deprecation (NULL als Array-Schluessel);
		// hier wird sie schlicht nicht gerendert.
		continue;
	}
	$firstOption = $definition->options[0] ?? null;

	switch ($definition->type) {
		case 'select':
			echo "<h4>{$definition->label}</h4>";
			echo "<select name='item_node_id' data-on_change='sidebar/updateSelect'"
				. " data-node_id='$pageNodeId' data-node_parent_node_id='{$definition->nodeId}'>";
			echo "<option>---</option>";
			foreach ($definition->options as $option) {
				// Gewaehlt ist, worauf eine Junction-Zeile zeigt — der Wert
				// spielt keine Rolle, die Verknuepfung selbst ist die Aussage.
				$selected = $values->has($option->nodeId) ? ' selected' : '';
				echo "<option$selected value='{$option->nodeId}'>{$option->label}</option>";
			}
			echo "</select>";
			break;

		case 'multi':
			echo "<h4>{$definition->label}</h4>";
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
			$name = $values->key($definition->name) ?? '';
			echo "<div class='checkboxContainer'><label class='checkbox'>";
			echo "<span><b>{$definition->label}</b></span>";
			echo "<input data-on_change='sidebar/updateValue' type='checkbox'"
				. " data-setting_node_id='$itemNodeId' $checked value='$value' name='$name'"
				. " class='switch tinyswitch'>";
			echo "<div><div></div></div></label></div>";
			break;

		case 'input':
			// Hier faellt die Vorgabe zu Recht ein: ein leeres Feld soll den
			// Wert zeigen, der ohne Zutun gilt.
			$itemNodeId = $firstOption?->nodeId ?? '';
			echo "<h4>{$definition->label}</h4>";
			echo "<input data-on_input='sidebar/updateValue' value='{$values->value($definition->name)}'"
				. " type='text' name='{$definition->name}' data-setting_node_id='$itemNodeId'  >";
			break;

		case 'group':
			// Mehrere Felder, die zusammengehören. Der Rahmen macht die
			// Gruppe von einem einzelnen Feld unterscheidbar.
			echo "<section class='settingGroup'>";
			echo "<h4>{$definition->label}</h4>";
			echo "<div data-on_input='sidebar/updateValue' data-node_id=$pageNodeId>";
			foreach ($definition->options as $option) {
				$itemValue = $values->junction($option->nodeId) === null
					? ''
					: \Systopic\Cms\Queries\SettingTree\Definition::valueIn(
						$values->junction($option->nodeId),
						$option->datatype,
					);
				echo "<label data-setting_node_id={$option->nodeId} >";
				echo $option->label;
				echo "<input name={$option->name} value=$itemValue>";
				echo "</label>";
			}
			echo '</div></section>';
			break;

		default:
			p("found setting of unknown Type: {$definition->type}");
	}
}
