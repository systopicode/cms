<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Admin\Pages\Upload;

use Systopic\System\Panels\Root\Admin\Panel as CmsPanel;

class Panel extends CmsPanel {

	function getDefaultState() {
		return (object) ['nodeId' => 0, 'pendingTags' => []];
	}

	function onLoad() {
		\fs::load('mediaInfo');
		$id = (int) \http::params('node_id', 0);
		$tagging = in_array((string) $this->action, ['addTag', 'addTagTo_page', 'removeTagFrom_page'], true);
		if ($id > 0) {
			$this->state->nodeId = $id;
		} elseif (!$tagging && in_array((string) $this->action, ['', 'open'], true)) {
			$this->state->nodeId = 0;
			$this->state->pendingTags = [];
		}
	}

	/** The medium being replaced, once the dialog was opened on one. */
	public int $nodeId {
		get => (int) ($this->state->nodeId ?? 0);
	}

}
