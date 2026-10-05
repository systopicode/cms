<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Pages\Editor;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

class Panel extends CmsPanel {
	public function __construct($name, $parent = NULL) {
		parent::__construct($name, $parent);
	}

	function onLoad() {

	}

	function getDefaultState() {
		return (object) [
		];
	}

}
