<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Unused;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\Cms\Queries\UnusedMedia\UnusedMedia;

/**
 * tools/maintenance/unused — media no page uses, to delete for good.
 */
class Panel extends PanelNode {

	public Scope $scope {
		get => Scope::default();
	}

	/** @return list<array{node: int, id: int, filename: string, type: string}> */
	public array $unused {
		get => UnusedMedia::of($this->scope);
	}
}
