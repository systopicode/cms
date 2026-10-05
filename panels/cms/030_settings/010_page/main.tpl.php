<h1>Edit Page Settings</h1>
<?php

/**
 * The declarations of page settings: the selected one with its fields, then
 * its children (at the root: the topmost level), each with its fields and
 * the buttons to move, open and delete it.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Settings\Page\Panel $this
 */

use Systopic\Cms\Queries\SettingTree\Definition;

$settingFieldnames = [
	'name' => '2',
	'label' => '2',
	'description' => '4',
	'type' => '1',
	'datatype' => '1',
];

$defaultFieldnames = [
	'varchar' => '2',
	'int' => '1',
	'decimal' => '1',
];

$selected = $this->selected;

/** Die fünf Spalten der Deklaration, als Zeichenkette fürs Formular. */
$feld = static function (?Definition $d, string $name): string {
	if ($d === null) {
		return '';
	}
	return match ($name) {
		'name' => (string) $d->setting->name,
		'label' => (string) $d->setting->label,
		// `description` ist ein `Text`, also ein Objekt — der Bestand hat an
		// dieser Stelle den Rohwert des Records und damit dasselbe Ergebnis.
		'description' => (string) ($d->setting->description ?? ''),
		'type' => (string) $d->setting->type,
		'datatype' => (string) $d->setting->datatype,
		default => '',
	};
};

/** Die drei Wertspalten der Vorgabe — der Junction an der Vorlagenzeile. */
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

/** Der Text hinter dem Info-Symbol — dieselben fünf Zeilen wie im Bestand. */
$info = static function (Definition $d): string {
	$j = $d->defaultJunction;
	// `relationId()`: the loaded id, or the id of an object assigned since.
	$refId = static fn(?object $row, string $property): string => (string) ($row?->relationId($property) ?? '');
	return implode("\n", [
		'node_id:' . $d->nodeId,
		'node_parent_node_id:' . $refId($d->node, 'parentNode'),
		'setting_id:' . ($d->setting->id ?? ''),
		'junction_node_id:' . $refId($j, 'node') . '(1=default)',
		'junction_setting_node_id:' . $refId($j, 'settingNode'),
	]);
};

/** Die acht Eingabefelder einer Zeile. */
$felder = static function (Definition $d) use ($settingFieldnames, $defaultFieldnames, $feld, $vorgabe): void {
	foreach ($settingFieldnames as $fieldname => $size) {
		echo "<label class=_{$size}_8  data-on_input=updateSetting>$fieldname";
		echo "<input name=$fieldname value='" . $feld($d, $fieldname) . "'>";
		echo "</label>";
	}
	foreach ($defaultFieldnames as $fieldname => $size) {
		echo "<label class=_{$size}_8 data-on_input=updateDefault>$fieldname";
		echo "<input name=$fieldname value='" . $vorgabe($d, $fieldname) . "'>";
		echo "</label>";
	}
};

if ($selected !== null) {
	echo "<div class=layout data-id={$selected->nodeId}>";
	$felder($selected);
	echo "	<label class=_2_8><br/>";
	echo "		<a class='submit' href='delete/?id={$selected->nodeId}'><i class='fa-light fa-trash'></i></a>";
	echo "		<i title='" . $info($selected) . "' class='fas button transparent fa-info-circle'></i>";
	echo "	</label>";
	echo "</div>";
	echo "<hr/>";
	echo "<hr/>";
	echo "<h2>Children</h2>";
}

$children = $this->rows;

foreach ($children as $child) {
	echo "<div class=layout data-id={$child->nodeId}>";
	$felder($child);
	echo "	<label class=_2_8><br/>";
	echo "		<a class='submit' target='' href='?id={$child->nodeId}'><i class='fa-light fa-pen'></i></a>";
	echo "		<a class='submit' data-on_click=up><i class='fa-light fa-chevron-up'></i></a>";
	echo "		<a class='submit' data-on_click=down><i class='fa-light fa-chevron-down'></i></a>";
	echo "		<a class='submit' href='delete/?id={$child->nodeId}'><i class='fa-light fa-trash'></i></a>";
	echo "		<i title='" . $info($child) . "' class='fas button transparent fa-info-circle'></i>";
	echo "	</label>";
	echo "</div>";
	echo "<hr/>";
}

if ($selected === null) {
	echo "<a class='button submit' data-on_click=add data-id=1>add Page Setting</a>";
} else {
	echo "<a class='button submit' data-on_click=add data-id={$selected->nodeId}>add Child</a>";
}
