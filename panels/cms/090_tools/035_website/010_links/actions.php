<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Website\Links\Panel $this */

use Systopic\Cms\Queries\LinkCheck\LinkCheck;

switch ($this->action) {
	case 'checkExternal':
		$check = LinkCheck::checkNext($this->scope);
		if (!$check['done']) {
			\http::refresh($this->getHref(\http::$root), 'checkExternal');
		}
		$this->updateView();
		break;
}
