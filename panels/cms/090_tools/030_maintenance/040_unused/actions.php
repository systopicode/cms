<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Unused\Panel $this */

use Systopic\System\Tables\Medias\Media;
use Systopic\System\Tables\Medias\Removal;

switch ($this->action) {
	case 'deleteOne':
	case 'deleteAll':
		$unused = $this->unused;
		$ids = $this->action === 'deleteOne' ? [(int) \http::dataset('node_id')] : array_column($unused, 'node');
		$known = [];
		foreach ($unused as $row) {
			$known[$row['node']] = $row['filename'];
		}
		$rows = [];
		$names = [];
		foreach ($ids as $nodeId) {
			if (!isset($known[$nodeId])) {
				continue;
			}
			$media = Media::byNodeId($this->scope, $nodeId);
			if ($media !== null) {
				$rows[] = $media->row;
				$names[] = $known[$nodeId];
			}
		}
		$gone = Removal::remove($this->scope, ...$rows);
		if ($gone > 0) {
			$text = $gone === 1 ? 'Deleted “' . $names[0] . '”.' : 'Deleted ' . $gone . ' media.';
			\message::confirm(htmlspecialchars($text, ENT_QUOTES));
		}
		$this->updateView();
		break;
}
