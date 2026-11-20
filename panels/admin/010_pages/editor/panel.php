<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Admin\Pages\Editor;

use Systopic\System\Panels\Root\Admin\Panel as CmsPanel;

class Panel extends CmsPanel {

	/** With admin/pages open, the editor's dialogs pick up what they were opened for (file: target_id). */
	public bool $loadChildPanels {
		get => $this->parent !== null && $this->parent->inPath();
	}

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
