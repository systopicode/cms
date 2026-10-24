<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Pages\Upload;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

class Panel extends CmsPanel {

	function getDefaultState() {
		return (object) ['nodeId' => 0];
	}

	function onLoad() {
		\fs::load('mediaInfo');
		$id = (int) \http::params('node_id', 0);
		if ($id > 0) {
			$this->state->nodeId = $id;
		}
	}

	/** The medium being replaced, once the dialog was opened on one. */
	public int $nodeId {
		get => (int) ($this->state->nodeId ?? 0);
	}

}
