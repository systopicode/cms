<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Duplicates\Panel $this */

switch ($this->action) {
	case 'deletePage':
		$this->drop($this->page);
		$this->updateView('main');
		break;
	case 'deleteCrop':
		$this->drop($this->crop);
		$this->updateView('main');
		break;
}
