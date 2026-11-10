<?php

/** @var \Systopic\Cms\Panels\Root\Admin\Settings\Page\Panel $this */

use Systopic\Cms\Queries\SettingTree\SettingTree;
use Systopic\Cms\Tables\Settings\Operator as SettingOperator;

$definition = $this->definition;

switch ($this->action) {
    case 'toggleGroup':
        $id = (int) \http::dataset('id');
        $open = array_map('intval', (array) $this->state->openFolds);
        $this->state->openFolds = in_array($id, $open, true) ? array_values(array_diff($open, [$id])) : [...$open, $id];
        $this->updateView('main');
        break;

    case 'placeSetting':
        $item = SettingTree::byNodeId($this->scope, (int) \http::dragdata('id'));
        $target = SettingTree::byNodeId($this->scope, (int) \http::dataset('id'));
        $position = (string) \http::dataset('position');
        if (!in_array($position, ['root','inside','before','after'], true) || !$item || ($position !== 'root' && !$target) || $item->nodeId === $target?->nodeId) break;
        try {
            $parent = $position === 'inside' ? $target : ($position === 'root' ? null : $target->parent);
            $before = $position === 'before' ? $target : null;
            if ($position === 'after') {
                $siblings = array_values(array_filter($parent?->options ?? SettingTree::load($this->scope), static fn($d) => $d->nodeId !== $item->nodeId));
                foreach ($siblings as $index => $sibling) if ($sibling->nodeId === $target->nodeId) $before = $siblings[$index+1] ?? null;
            }
            \Systopic\Cms\Settings\TemplateEditor::place($this->scope, $item, $parent, $before);
            if ($parent) $this->state->openFolds = array_values(array_unique([...$this->state->openFolds, $parent->nodeId]));
            $this->updateView('main');
        } catch (\InvalidArgumentException $error) { \message::error($error->getMessage()); }
        break;

	case 'add':
		// data-id 1 (the root) adds to the topmost level.
		$this->scope->save(SettingOperator::create($definition, $definition?->options ?? SettingTree::load($this->scope)));
		$this->parent->updateView('main');
		break;

	case 'updateSetting':
        $postedValues = (array) (\http::values() ?: []);
        if (isset($postedValues['name']) && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', (string) $postedValues['name'])) {
            \message::error('Use a valid key: letters, digits and underscores; start with a letter or underscore.');
            return;
        }
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

	case 'delete':
		if ($definition === null) {
			\message::error('No setting at that node.');
			break;
		}
		if ($definition->options !== []) {
			\message::error('A setting that still has children cannot be deleted.');
			break;
		}
		$this->child('delete')?->removeDefinition($definition);
		\message::confirm('Setting removed.');
		$this->updateView('main');
		break;
}
