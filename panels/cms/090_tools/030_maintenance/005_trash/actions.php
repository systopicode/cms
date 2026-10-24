<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Trash\Panel $this */

switch ($this->action) {
	case 'restoreNode':
		$nodeId = (int) \http::dataset('node_id');
		$entry = $this->trashed[$nodeId] ?? null;
		if ($entry !== null) {
			$title = $this->pageTitle($entry['pages']);
			$restored = $this->restoreBranch($nodeId);
			$text = $restored > 1
				? 'Restored “' . $title . '” and the pages under it.'
				: 'Restored “' . $title . '”.';
			\message::confirm(htmlspecialchars($text, ENT_QUOTES));
		}
		$this->updateView();
		break;
}
