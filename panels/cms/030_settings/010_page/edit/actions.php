<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Settings\Page\Edit\Panel $this */

use Systopic\Cms\Tables\Settings\Operator as SettingOperator;

$definition = $this->definition;

switch ($this->action) {
	case 'updateSetting':
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
