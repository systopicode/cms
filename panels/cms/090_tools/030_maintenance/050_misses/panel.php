<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Misses;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\Cms\Queries\Misses\Misses;

/**
 * tools/maintenance/misses — addresses that 404, to send to a page.
 */
class Panel extends PanelNode {

	public Scope $scope {
		get => Scope::default();
	}

	/** @return list<\Systopic\Cms\Tables\Misses\Model> */
	public array $misses {
		get => Misses::list($this->scope);
	}

	/** @return list<array{node: int, label: string}> */
	public array $pages {
		get => Misses::pages($this->scope);
	}
}
