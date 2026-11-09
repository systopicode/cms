<?php

/** @var \Systopic\Cms\Panels\Root\Admin\Settings\Website\Panel $this */

use Systopic\System\Settings\LanguageFlags;
use Systopic\System\Settings\ProjectForm;

if (LanguageFlags::handleAction($this)) {
    return;
}

ProjectForm::handle($this, 'page');
