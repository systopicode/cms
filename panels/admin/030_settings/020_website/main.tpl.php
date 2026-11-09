<?php

/** @var \Systopic\Cms\Panels\Root\Admin\Settings\Website\Panel $this */

use Systopic\System\Settings\ProjectForm;

ProjectForm::render('page', $this->globalSettings, [], 'Website');
