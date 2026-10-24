<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Duplicates\Panel $this */

switch ($this->action) {
	case 'deletePage':
		$page = $this->page;
		$title = trim((string) ($page->label ?? '')) ?: 'Untitled';
		if ($this->drop($page)) {
			\message::confirm(htmlspecialchars('Deleted the copy of “' . $title . '”.', ENT_QUOTES));
		}
		$this->updateView('main');
		break;
	case 'deleteCrop':
		$crop = $this->crop;
		$title = trim((string) ($crop?->media?->label ?? '')) ?: 'Untitled';
		if ($this->drop($crop)) {
			\message::confirm(htmlspecialchars('Deleted the copy of “' . $title . '”.', ENT_QUOTES));
		}
		$this->updateView('main');
		break;
}
