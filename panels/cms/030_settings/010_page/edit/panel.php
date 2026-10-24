<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Settings\Page\Edit;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\Cms\Queries\SettingTree\Definition;
use Systopic\Cms\Queries\SettingTree\SettingTree;

/**
 * The lightbox of one page setting (`?id=` is its node).
 */
class Panel extends PanelNode {

	public bool $inNavi = false;

	public Scope $scope {
		get => Scope::default();
	}

	public ?Definition $definition {
		get => ($id = (int) \http::get('id')) > 1 ? SettingTree::byNodeId($this->scope, $id) : NULL;
	}
}
