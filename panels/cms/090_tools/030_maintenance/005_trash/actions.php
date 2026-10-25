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

	case 'deleteNode':
	case 'emptyTrash':
		$trashed = $this->trashed;
		$ids = $this->action === 'deleteNode' ? [(int) \http::dataset('node_id')] : array_keys($trashed);
		$titles = array_map(fn(int $id): string => $this->pageTitle($trashed[$id]['pages'] ?? []), $ids);
		$gone = [];
		foreach ($ids as $i => $nodeId) {
			$result = \Systopic\Cms\Tables\Pages\Removal::branch($this->scope, (int) $nodeId);
			if (is_string($result)) {
				\message::error(htmlspecialchars('“' . $titles[$i] . '”: ' . $result, ENT_QUOTES));
			} else {
				$gone[] = $titles[$i];
			}
		}
		if ($gone !== []) {
			\message::confirm(htmlspecialchars(count($gone) === 1 ? 'Deleted “' . $gone[0] . '” for good.' : 'Deleted ' . count($gone) . ' pages for good.', ENT_QUOTES));
		}
		$this->updateView();
		break;
}
