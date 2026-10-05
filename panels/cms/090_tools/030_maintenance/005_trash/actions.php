<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Trash\Panel $this */

switch ($this->action) {
	case 'restoreNode':
		$nodeId = (int) \http::dataset('node_id');
		if (isset($this->trashed[$nodeId])) {
			\message::confirm("page recovered: $nodeId (" . $this->restoreBranch($nodeId) . ' nodes)');
		}
		$this->updateView();
		break;
}
