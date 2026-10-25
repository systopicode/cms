<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Migration\Panel $this */

switch ($this->action) {
	case 'convertHtmlRefs':
		// asked in the browser (userConfirm in main.tpl.php)
		$done = $this->htmlMigration->run(TRUE);
		\message::confirm($done['changed'] . ' page rows converted.');
		$this->updateView();
		break;
}
