<?php

/**
 * Die Schreibseite der Settings-Seitenleiste — auf `Tables\Junctions\Model`.
 *
 * Vorher waren das vier von Hand gebaute Anweisungen mit interpoliertem
 * Spaltennamen (`junction_{$setting->datatype}`) und einem `IN (SELECT …)`
 * ueber `cms_nodes`. Jetzt schreibt der `UnitOfWork`, und welche der drei
 * Wertspalten gemeint ist, sagt die Deklaration statt einer Zeichenkette im
 * SQL.
 *
 * **Eine Verhaltensaenderung, und zwar mit Absicht:** `---` im Dropdown nimmt
 * die Zuordnung jetzt weg. Der Bestand steigt dort aus —
 *
 *     if (!http::values('item_node_id')) { p('NO SETTING ID - skip query'); return; }
 *
 * — und laesst die alte Auswahl stehen. Das Dropdown bietet also einen
 * Eintrag an, der nichts tut, und eine einmal getroffene Auswahl liess sich
 * ueber die Oberflaeche nicht mehr loesen. Wer das anders will, sagt es; es
 * ist der `if`-Block unten.
 */

use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Junctions\Model as NodeJunction;
use Systopic\Cms\Queries\SettingTree\SettingTree;
use Systopic\Cms\Queries\SettingValues\SettingValues;

/** @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this */

switch ($this->action) {
	case 'updateSelect':
		$pageNodeId = (int) http::dataset('node_id');
		$settingNodeId = (int) http::dataset('node_parent_node_id');
		if ($pageNodeId === 0 || $settingNodeId === 0) {
			p('updateSelect ohne Knoten — nichts zu tun');
			break;
		}

		$scope = $this->parent->scope;
		$definition = SettingTree::byNodeId($scope, $settingNodeId);
		if ($definition === null) {
			p("keine Einstellung am Knoten $settingNodeId");
			break;
		}

		$values = SettingValues::of($scope, $pageNodeId);
		$uow = $scope->uow();

		// Erst weg, was da war. Eine Auswahl ist genau eine Zeile — mehrere
		// waeren zwei Antworten auf dieselbe Frage, und der Bestand raeumt
		// hier aus demselben Grund mit einem DELETE ueber alle Optionen auf.
		foreach ($definition->options as $option) {
			$vorhanden = $values->junction($option->nodeId);
			if ($vorhanden !== null) {
				$uow->remove($vorhanden);
			}
		}

		// Dann das Gewaehlte. Ohne Wert — bei einem Select ist die
		// Verknuepfung selbst die Aussage.
		$chosenNodeId = (int) http::values('item_node_id');
		if ($chosenNodeId !== 0 && $definition->optionByNodeId($chosenNodeId) !== null) {
			$junction = new NodeJunction();
			$junction->node = Node::withId($pageNodeId);
			$junction->settingNode = Node::withId($chosenNodeId);
			$uow->persist($junction);
		}

		$uow->commit();
		break;

	case 'updateValue':
		$pageNodeId = (int) http::dataset('node_id');
		$optionNodeId = (int) http::dataset('setting_node_id');
		if ($pageNodeId === 0 || $optionNodeId === 0) {
			p('updateValue ohne Knoten — nichts zu tun');
			break;
		}

		$scope = $this->parent->scope;
		$option = SettingTree::byNodeId($scope, $optionNodeId);
		if ($option === null) {
			p("keine Einstellung am Knoten $optionNodeId");
			break;
		}

		$values = SettingValues::of($scope, $pageNodeId);
		$uow = $scope->uow();

		// Die Zeile gibt es oder sie entsteht — das ist das
		// `ON DUPLICATE KEY UPDATE` des Bestands, nur dass hier der
		// UnitOfWork die Unterscheidung trifft: eine geladene Zeile ist
		// persistiert und wird zum UPDATE, eine neue zum INSERT.
		$junction = $values->junction($optionNodeId);
		if ($junction === null) {
			$junction = new NodeJunction();
			$junction->node = Node::withId($pageNodeId);
			$junction->settingNode = Node::withId($optionNodeId);
		}

		// `http::values()` ist eine Liste — der Bestand laeuft sie durch und
		// benutzt den Schluessel nicht. Der letzte Wert gewinnt, wie dort.
		foreach ((array) http::values() as $value) {
			// Welche der drei Spalten gemeint ist, sagt die Deklaration.
			// Im Bestand stand der Spaltenname interpoliert im SQL.
			match ($option->datatype) {
				'int' => $junction->int = $value === null || $value === '' ? null : (int) $value,
				'decimal' => $junction->decimal = $value === null || $value === ''
					? null
					: \Systopic\Db\Type\Decimal::from($value),
				default => $junction->varchar = $value === null ? null : (string) $value,
			};
		}

		$uow->persist($junction)->commit();
		break;
}
