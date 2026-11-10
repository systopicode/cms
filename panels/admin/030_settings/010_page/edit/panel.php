<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Admin\Settings\Page\Edit;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\Cms\Queries\SettingTree\Definition;
use Systopic\Cms\Queries\SettingTree\SettingTree;

/**
 * The lightbox of one page setting (`?id=` is its node).
 */
class Panel extends PanelNode {

	public bool $inNavi = false;

    public function onLoadThisClass(): void
    {
        // Same dialog, another definition (or a new-field form): redraw its contents.
        if ($this->inPath() && (\http::$method ?? 'GET') === 'GET') {
            $this->updateView('lightbox');
        }
    }

    public function backHref(?Definition $parent): string
    {
        return $this->parent->href . ($parent && \http::get('from') !== 'list' ? 'edit/?id=' . $parent->nodeId : '');
    }

	public Scope $scope {
		get => Scope::default();
	}

	public ?Definition $definition {
		get => ($id = (int) \http::get('id')) > 1 ? SettingTree::byNodeId($this->scope, $id) : NULL;
	}
}
