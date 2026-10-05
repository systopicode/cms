<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Pages\Sidebar;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

class Panel extends CmsPanel {

	public $templates = [];

	/** The site of the page in main (its name is its folder under sites/) — null without config/. */
	public ?\Systopic\System\Config\Site $pageSite {
		get {
			$id = $this->parent->page?->id;
			return $id ? \Systopic\Cms\Pages\Sites\NodeSite::siteOf($id) : null;
		}
	}

	/** The page templates this page can get: the shared ones and its site's own. */
	public object $pageTemplates {
		get => \template::forSite($this->pageSite?->name);
	}

	function __construct($name, $parent) {
		parent::__construct($name, $parent);
		foreach (\fs::glob('0*.tpl.php') as $filename) {
			preg_match('~(^\d\d\d_(.*?))\.tpl.php$~', $filename, $match);
			$name = $match[2];
			$this->templates[$name] = (object) [
					'label' => ucfirst($name),
					'view' => $match[1],
					'filename' => $filename
			];
		}
	}

	function getDefaultState() {
		return (object) [
				'activeSidebarTemplates' => [],
				'sidebarWidth' => 200,
		];
	}

}
