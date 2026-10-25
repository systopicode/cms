<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Links;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;

/**
 * tools/maintenance/links — links in the page texts that lead nowhere:
 * inside ones on every visit, outside ones when the button asks the hosts.
 */
class Panel extends PanelNode {

	public Scope $scope {
		get => Scope::default();
	}
}
