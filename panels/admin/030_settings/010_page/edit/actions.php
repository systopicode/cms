<?php

/** @var \Systopic\Cms\Panels\Root\Admin\Settings\Page\Edit\Panel $this */

use Systopic\Cms\Tables\Settings\Operator as SettingOperator;
use Systopic\Cms\Queries\SettingTree\SettingTree;
use Systopic\Cms\Settings\TemplateEditor;

$definition = $this->definition;

switch ($this->action) {
    case 'createSetting':
        $values = (array) (\http::posted() ?: []);
        $parent = SettingTree::byNodeId($this->scope, (int) \http::get('parent_id'));
        if ((int) \http::get('parent_id') > 1 && !$parent) {
            \message::error('This group no longer exists.');
            break;
        }
        try {
            $created = TemplateEditor::create($this->scope, $parent, (string) ($values['kind'] ?? 'text'), (string) ($values['label'] ?? ''));
            \http::redirect($this->href . '?id=' . $created->nodeId);
        } catch (\InvalidArgumentException $error) {
            \message::error($error->getMessage());
        }
        break;

    case 'saveSetting':
        if (!$definition) { \message::error('This setting no longer exists.'); break; }
        $values = (array) (\http::posted() ?: []);
        $label = trim((string) ($values['label'] ?? ''));
        $name = trim((string) ($values['name'] ?? ''));
        $siblings = $definition->parent?->options ?? SettingTree::load($this->scope);
        if ($label === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) {
            \message::error('Enter a name and a valid template key.'); break;
        }
        foreach ($siblings as $sibling) {
            if ($sibling->nodeId !== $definition->nodeId && $sibling->name === $name) {
                \message::error('This template key is already in use.'); return;
            }
        }
        $default = $values['default'] ?? '';
        if ($default !== '' && $definition->datatype !== 'varchar' &&
            ($definition->datatype === 'int' ? !preg_match('/^-?\d+$/D', (string) $default) : !is_numeric($default))) {
            \message::error('Enter a valid default number.'); break;
        }
        $this->scope->connection->transactional(function () use ($definition, $values, $label, $name, $default) {
            $assigned = ['label' => $label, 'name' => $name, 'description' => (string) ($values['description'] ?? '')];
            if (!$definition->parent) {
                $known = array_keys((array) \template::getAll());
                $picked = array_values(array_intersect($known, (array) ($values['templates'] ?? [])));
                $assigned['showInTemplates'] = ($values['visibility'] ?? 'all') === 'all' ? '' : ($picked ? implode(';', $picked) : '__none__');
            }
            $this->scope->save(SettingOperator::assign($definition->setting, $assigned));
            if (array_key_exists('default', $values) && $definition->type === 'input' && !$definition->parent) {
                $this->scope->save(SettingOperator::assignDefault($definition, [$definition->datatype => $default]));
            }
        });
        \message::confirm('Setting saved.');
        $this->updateView('lightbox');
        $this->parent->updateView('main');
        break;

    case 'removeChild':
        $child = SettingTree::byNodeId($this->scope, (int) \http::dataset('child_id'));
        if (!$definition || !$child || $child->parent?->nodeId !== $definition->nodeId || $child->options !== []) {
            \message::error('This field cannot be removed here.'); break;
        }
        $this->parent->child('delete')->removeDefinition($child);
        $this->updateView('lightbox');
        $this->parent->updateView('main');
        break;

    case 'removeSetting':
        if (!$definition) {
            \message::error('This setting no longer exists.');
            break;
        }
        $back = $this->parent->href . ($definition->parent ? 'edit/?id=' . (int) $definition->parent->nodeId : '');
        $this->scope->connection->transactional(function () use ($definition) {
            $remove = function ($item) use (&$remove): void {
                foreach ($item->options as $child) $remove($child);
                $this->parent->child('delete')->removeDefinition($item);
            };
            $remove($definition);
        });
        $this->parent->updateView('main');
        \http::redirect($back);
        break;

	case 'updateSetting':
        $postedValues = (array) (\http::values() ?: []);
        if (isset($postedValues['name']) && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', (string) $postedValues['name'])) {
            \message::error('Use a valid key: letters, digits and underscores; start with a letter or underscore.');
            return;
        }
		if ($definition) {
			$values = (array) (\http::values() ?: []);
			$this->scope->save(SettingOperator::assign($definition->setting, $values));
			if (isset($values['type']) || isset($values['datatype'])) {
				$this->updateView('lightbox');
			}
			$this->parent->updateView('main');
		}
		break;

	case 'updateTemplates':
		if ($definition) {
			$name = trim((string) \http::dataset('template'));
			$values = (array) (\http::values() ?: []);
			$on = (int) ($values['on'] ?? 0) === 1;
			$current = array_values(array_filter(
				array_map('trim', explode(';', (string) ($definition->setting->showInTemplates ?? ''))),
				static fn(string $item): bool => $item !== '',
			));
			if ($name !== '' && !str_contains($name, ';')) {
				if ($on && !in_array($name, $current, true)) {
					$current[] = $name;
				}
				if (!$on) {
					$current = array_values(array_filter($current, static fn(string $item): bool => $item !== $name));
				}
				$definition->setting->showInTemplates = implode(';', $current);
				$this->scope->save($definition->setting);
			}
		}
		break;

	case 'updateDefault':
		if ($definition) {
			$this->scope->save(SettingOperator::assignDefault($definition, (array) (\http::values() ?: [])));
		}
		break;
}
