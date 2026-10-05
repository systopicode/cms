<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Settings\Page\Panel $this */

use Systopic\Cms\Queries\SettingTree\SettingTree;
use Systopic\Cms\Tables\Settings\Operator as SettingOperator;

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
}
