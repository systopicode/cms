<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Settings\Page;

use Systopic\System\Panels\Root\Cms\Settings\Panel as SettingsPanel;
use Systopic\Cms\Queries\SettingTree\Definition;
use Systopic\Cms\Queries\SettingTree\SettingTree;

/**
 * cms/settings/page — the declarations of page settings, as a tree.
 *
 * Node ids address everything: `?id=` is the declaration on screen (1 or none
 * is the root, which is the list of all), `data-id` on a row the one an
 * action works on.
 */
class Panel extends SettingsPanel {

	public function __construct($name, $parent = NULL) {
		parent::__construct($name, $parent);
		$this->label = 'Page Settings';
	}

	/** The declaration on screen — NULL at the root. */
	public ?Definition $selected {
		get => ($id = (int) \http::get('id', 1)) > 1 ? SettingTree::byNodeId($this->scope, $id) : NULL;
	}

	/** @var list<Definition> the rows below the selected one, or the topmost level */
	public array $rows {
		get => $this->selected?->options ?? SettingTree::load($this->scope);
	}

	/** The declaration of the row an action came from. */
	public ?Definition $definition {
		get => ($id = (int) \http::dataset('id')) > 1 ? SettingTree::byNodeId($this->scope, $id) : NULL;
	}

	function getDefaultState() {
		return (object) [
					'id' => NULL,
					'childSelected' => NULL,
					'pylonWidth' => 250,
					'openFolds' => [],
		];
	}
}
