<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Pages\Upload;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

class Panel extends CmsPanel {

	function onLoad() {
		\fs::load('mediaInfo');
	}

}
