<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Links\Panel $this */

use Systopic\Cms\Queries\LinkCheck\LinkCheck;

switch ($this->action) {
	case 'checkExternal':
		$check = LinkCheck::checkNext($this->scope);
		if (!$check['done']) {
			\http::refresh('./', 'checkExternal');
		}
		$this->updateView();
		break;
}
