<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Pages\Sidebar;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

class Panel extends CmsPanel {

	public $templates = [];

	public array $groups = [
		'content' => ['label' => 'Content', 'templates' => ['templates', 'settings', 'tags']],
		'seo' => ['label' => 'SEO & KI', 'templates' => ['urls', 'meta']],
		'publishing' => ['label' => 'Publishing', 'templates' => ['publishing', 'info']],
	];

	public array $templateLabels = [
		'urls' => 'URLs',
		'templates' => 'Templates',
		'settings' => 'Settings',
		'tags' => 'Tags',
		'publishing' => 'Release',
		'info' => 'Info',
		'meta' => 'Meta',
	];

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
					'label' => $this->templateLabels[$name] ?? ucfirst($name),
					'view' => $match[1],
					'filename' => $filename
			];
		}
		$grouped = [];
		foreach ($this->groups as $groupName => $group) {
			$this->groups[$groupName] = (object) [
				'label' => $group['label'],
				'templates' => array_values(array_intersect($group['templates'], array_keys($this->templates))),
			];
			$grouped = array_merge($grouped, $this->groups[$groupName]->templates);
		}
		$ungrouped = array_values(array_diff(array_keys($this->templates), $grouped));
		if ($ungrouped) {
			$this->groups['more'] = (object) ['label' => 'More', 'templates' => $ungrouped];
		}
		$this->groups = array_filter($this->groups, fn($group) => count($group->templates) > 0);
	}

	function activeGroup(): string {
		$name = $this->state->sidebarGroup ?? null;
		return isset($this->groups[$name]) ? (string) $name : (string) array_key_first($this->groups);
	}

	/**
	 * The SEO facts of the page open in the editor. Same values the public
	 * page, sitemap.xml and llms.txt use, read now, not from a saved file.
	 */
	public function seoPreview(): string {
		$page = $this->parent->page ?? null;
		if ($page === null || $page->row === null) {
			return '';
		}
		$record = \app::site()->record($page);
		$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
		$root = rtrim((string) \http::$siteUrl, '/') . '/';
		$place = $record['crumbs'] === [] ? 'Top of the site' : implode(' › ', $record['crumbs']);
		$listed = $record['listed']
			? 'Included'
			: 'Left out. ' . implode('. ', $record['blockers']);
		$rows = [
			'Name' => $record['name'] !== '' ? $record['name'] : '—',
			'Description' => $record['description'] !== '' ? $record['description'] : '—',
			'Description from' => $record['descriptionFrom'],
			'Language' => $record['language'],
			'Address' => $record['url'] !== '' ? $record['url'] : '—',
			'Image' => $record['imageFrom'],
			'Place' => $place,
			'Sitemap and llms.txt' => $listed,
		];
		$html = '<dl class="cmsSeoFacts">';
		foreach ($rows as $label => $value) {
			$html .= '<dt>' . $e($label) . '</dt><dd>' . $e($value) . '</dd>';
		}
		$html .= '</dl>';
		$html .= '<p class="hint">Search engines and AI read the name, the description, the language and where the page sits. fluxo cms writes that from the fields above. You don’t type it.</p>';
		$html .= '<p class="hint"><a href="' . $e($root . 'sitemap.xml') . '" target="_blank" rel="noopener">sitemap.xml</a> is the page list. <a href="' . $e($root . 'llms.txt') . '" target="_blank" rel="noopener">llms.txt</a> is the same list as plain text, for AI. Both are built when they are opened, not stored as files.</p>';
		return $html;
	}

	/** The share image of the page in the editor, read from its row: a drop shows at once. */
	public function shareImage(): string {
		$id = $this->parent->page?->meta->shareImageId;
		$media = $id ? \Systopic\System\Tables\Medias\Media::byNodeId(\Systopic\Db\Scope::default(), $id) : null;
		if ($media === null) {
			return '';
		}
		$src = htmlspecialchars((string) $media->setFormat('shareImage')->src, ENT_QUOTES);
		return "<img src='$src' alt=''><div class='dropActions'><a class='ajax' href='editor/file/?target_id=$id&format=shareImage' title='Crop and adjust'><i class='fa-regular fa-pen'></i></a></div>";
	}

	public function refreshSeoPreview(): void {
		\client::replaceInner('aside .cmsSeoPreview', $this->seoPreview());
	}

	function getDefaultState() {
		return (object) [
				'activeSidebarTemplates' => [],
				'sidebarGroup' => null,
				'sidebarWidth' => 280,
				'sidebarCollapsed' => false,
		];
	}

}
