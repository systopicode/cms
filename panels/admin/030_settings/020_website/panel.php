<?php

declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Admin\Settings\Website;

use Systopic\System\Panels\Root\Admin\Settings\Panel as SettingsPanel;

/**
 * The defaults of the website: who may see it, its meta data, its
 * languages. They lie in config/global.settings.json, beside what the system
 * needs (name, title separator, legal links: Global Settings). This panel
 * names its keys, Global Settings leaves them out.
 */
class Panel extends SettingsPanel
{
    public $icon = 'cmsIcon cmsIcon-globe';

    public bool $buildChildPanels = false;

    /**
     * The keys of global.settings.json only the cms reads.
     *
     * @var list<string>
     */
    public array $globalSettings = [
        'PAGE_IS_PUBLIC', 'PAGE_ACL', 'FRONTEND_EDIT_BAR',
        'PUBLIC_PAGE_TITLE', 'PUBLIC_PAGE_DESCRIPTION', 'PUBLIC_PAGE_KEYWORDS', 'PUBLIC_PAGE_AUTHOR', 'PUBLIC_PAGE_COPYRIGHT',
        'META_AUTHOR', 'META_KEYWORDS', 'META_DESCRIPTION', 'META_PAGE_TOPIC', 'META_ROBOTS',
        'DEFAULT_LANG', 'LANGUAGES', 'AUTO_LANGUAGE_DETECTION',
    ];

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Website';
    }

    function onLoad() {}

    function onLoadThisClass() {}

    public function searchHits(\Systopic\System\Sys\Search $search): void
    {
        \Systopic\System\Settings\ProjectForm::searchHits($search, 'page', $this->getHref(\http::$root), $this->globalSettings, [], 'Website');
    }
}
