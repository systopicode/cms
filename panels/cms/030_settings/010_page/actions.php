<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Settings\Page\Panel $this */

use Systopic\Cms\Queries\SettingTree\Definition;
use Systopic\Cms\Queries\SettingTree\SettingTree;
use Systopic\Cms\Tables\Settings\Operator as SettingOperator;
use Systopic\System\Tables\Nodes\Operator as NodeOperator;

$definition = $this->definition;

switch ($this->action) {
	case 'add':
		// data-id 1 (the root) adds to the topmost level.
		$this->scope->save(SettingOperator::create($definition, $definition?->options ?? SettingTree::load($this->scope)));
		$this->parent->updateView('main');
		break;

	case 'updateSetting':
		// One field per keystroke, no redraw: the field is on screen already.
		if ($definition) {
			$this->scope->save(SettingOperator::assign($definition->setting, (array) (\http::values() ?: [])));
		}
		break;

	case 'updateDefault':
		if ($definition) {
			$this->scope->save(SettingOperator::assignDefault($definition, (array) (\http::values() ?: [])));
		}
		break;

	case 'up':
	case 'down':
		if ($definition) {
			$this->scope->save(...SettingTree::move($this->scope, $definition, $this->action === 'up' ? -1 : +1));
		}
		$this->updateView();
		break;

	case 'insertBefore':
	case 'insertAfter':
		$drag = SettingTree::byNodeId($this->scope, (int) \http::dragdata('id'));
		$drop = SettingTree::byNodeId($this->scope, (int) \http::dataset('id'));
		if ($drag === null || $drop === null || $drag->nodeId === $drop->nodeId) {
			break;
		}
		if (($drag->parent?->nodeId ?? 0) !== ($drop->parent?->nodeId ?? 0)) {
			break;
		}
		$siblings = $drag->parent === null
			? SettingTree::load($this->scope)
			: $drag->parent->options;
		$nodes = array_map(static fn(Definition $row) => $row->node, $siblings);
		$others = array_values(array_filter($nodes, static fn($node) => $node !== $drag->node));
		$at = count($others);
		foreach ($others as $index => $node) {
			if ($node === $drop->node) {
				$at = $index + ($this->action === 'insertAfter' ? 1 : 0);
				break;
			}
		}
		$this->scope->save(...NodeOperator::place($nodes, $drag->node, $at));
		$this->updateView();
		break;
}
