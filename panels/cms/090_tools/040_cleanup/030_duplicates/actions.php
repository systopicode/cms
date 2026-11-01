<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Cleanup\Duplicates\Panel $this */

switch ($this->action) {
    case 'scanFiles':
        $this->fileDuplicates = \Systopic\Cms\Queries\Duplicates\Duplicates::files($this->scope);
        $this->updateView('main');
        break;
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
