<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Admin\Pages\Sidebar;

use Systopic\System\Panels\Root\Admin\Panel as CmsPanel;

/**
 * admin/pages/sidebar — the properties of the page open in the editor.
 *
 * Its groups (the tabs) and their sections (the folds) are panels below it,
 * sharing this class (`bequests`) - but no address: they are opened by name
 * (main.tpl.php, two loops), not by the URL. What is open is state:
 *
 *   sidebar/                      state: sidebarGroup (the tab), width, collapsed
 *     content/  seo/  publishing/ a group: the tab, its label
 *       templates/ settings/ …    a section: its main.tpl.php, state->open (the fold)
 *
 * The requests stay the sidebar's (`sidebar/<action>`, actions.php).
 */
class Panel extends CmsPanel {

	/** Groups and sections share this class. */
	public bool $bequests = true;

	/** The tabs: group => label. Their sections are the folders below each. */
	public const GROUPS = ['content' => 'Content', 'seo' => 'SEO & AI', 'publishing' => 'Publishing'];

	/** Section labels that are not just the name. */
	public const LABELS = ['urls' => 'URLs', 'publishing' => 'Release', 'relations' => 'Aliases & Redirects', 'search' => 'Search and AI'];

	/** The sidebar itself - from a group or a section, too. */
	public function sidebar(): self {
		$panel = $this;
		while ($panel->parent instanceof self) {
			$panel = $panel->parent;
		}
		return $panel;
	}

	/** The pages panel: the page in the editor is its `page`. */
	public function pages(): \Systopic\System\Panels\PanelNode {
		return $this->sidebar()->parent;
	}

	/** A tab, below the sidebar. */
	public bool $isGroup {
		get => $this->parent instanceof self && !($this->parent->parent instanceof self);
	}

	/** A fold, below a tab. */
	public bool $isSection {
		get => $this->parent instanceof self && $this->parent->parent instanceof self;
	}

	public function onBuild() {
		parent::onBuild();
		if ($this->isGroup) {
			$this->label = self::GROUPS[$this->name] ?? $this->label;
			// the builder passes a class on one level only: a tab hands it on to its folds
			$this->bequests = isset(self::GROUPS[$this->name]);
		} elseif ($this->isSection) {
			$this->label = self::LABELS[$this->name] ?? $this->label;
		}
	}

	/** The site of the page in main (its name is its folder under sites/) — null without config/. */
	public ?\Systopic\System\Config\Site $pageSite {
		get {
			$id = $this->pages()->page?->id;
			return $id ? \Systopic\Cms\Pages\Sites\NodeSite::siteOf($id) : null;
		}
	}

	/** The page templates this page can get: the shared ones and its site's own. */
	public object $pageTemplates {
		get => \template::forSite($this->pageSite?->name);
	}

    public function incomingRelations(): array {
        $id = $this->pages()->page?->id;
        if (!$id) return [];
        return $this->scope->connection->all('SELECT node_id, node_original_node_id, node_redirect_node_id FROM cms_nodes WHERE node_type = :type AND !node_is_trashed AND (node_original_node_id = :original OR node_redirect_node_id = :redirect) ORDER BY node_id', [':type' => 'page', ':original' => $id, ':redirect' => $id]);
    }

	/**
	 * The tabs there are: name => the group panel.
	 *
	 * @return array<string, self>
	 */
	public function groups(): array {
		$sidebar = $this->sidebar();
		$groups = [];
		foreach (self::GROUPS as $name => $label) {
			$group = $sidebar->child($name);
			if ($group !== null && $group->sections() !== []) {
				$groups[$name] = $group;
			}
		}
		return $groups;
	}

	/**
	 * The folds of a group, in folder order.
	 *
	 * @return list<self>
	 */
	public function sections(): array {
		$sections = [];
		for ($child = $this->firstChild; $child !== null; $child = $child->next) {
			$sections[] = $child;
		}
		return $sections;
	}

	/** The tab on screen: the one chosen last (state), else the first. */
	function activeGroup(): string {
		$groups = $this->groups();
		$name = (string) ($this->sidebar()->state->sidebarGroup ?? '');
		return isset($groups[$name]) ? $name : (string) array_key_first($groups);
	}

	/**
	 * The SEO facts of the page open in the editor. Same values the public
	 * page, sitemap.xml and llms.txt use, read now, not from a saved file.
	 */
	public function seoPreview(): string {
		$page = $this->pages()->page ?? null;
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
			'Default language' => \Systopic\Cms\Tables\Pages\Languages::default(),
			'Effective robots' => \app::site()->robotsFor($page),
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
		$html .= '<p class="hint">What a search engine and an AI read for this page. It is written from the fields above.</p>';
		$html .= '<div class="cmsOutputCheck"><button type="button" class="button" data-output-url="' . $e($record['url']) . '" onclick="cmsCheckPageOutput(this)">Check public output</button><div class="cmsOutputCheckResult" role="status" aria-live="polite"></div></div>';
		$html .= '<p class="hint"><a class="cmsTextLink cmsExternalLink" href="' . $e($root . 'sitemap.xml') . '" target="_blank" rel="noopener">sitemap.xml <i class="cmsIcon cmsIcon-arrow-up-right-from-square" aria-hidden="true"></i></a> lists the pages. <a class="cmsTextLink cmsExternalLink" href="' . $e($root . 'llms.txt') . '" target="_blank" rel="noopener">llms.txt <i class="cmsIcon cmsIcon-arrow-up-right-from-square" aria-hidden="true"></i></a> is that list in plain text, for an AI. Both are written when they are opened.</p>';
		return $html;
	}

	/** The share image of the page in the editor, read from its row: a drop shows at once. */
	public function shareImage(): string {
		$picker = '<button type="button" class="shareImagePicker" data-page-media-picker="share" aria-label="Select share image from Media Library"><i class="cmsIcon cmsIcon-image" aria-hidden="true"></i><span class="shareImageDropLabel">Drop image from Media Library</span><span class="shareImageTapLabel">Tap to select from Media Library</span></button>'
			. '<button type="button" class="button cmsMobileOnly" data-page-media-paste="share"><i class="cmsIcon cmsIcon-copy" aria-hidden="true"></i> Paste image</button>';
		$id = $this->pages()->page?->meta->shareImageId;
		$media = $id ? \Systopic\System\Tables\Medias\Media::byNodeId(\Systopic\Db\Scope::default(), $id) : null;
		if ($media === null) {
			return $picker;
		}
		$src = htmlspecialchars((string) $media->setFormat('shareImage')->src, ENT_QUOTES);
		return $picker . "<img src='$src' alt=''><div class='dropActions'>"
			. "<a class='ajax' href='" . htmlspecialchars($this->pages()->href . \Systopic\System\Media\Presentation::editorPath((int) $id, (string) $media->mimetype, (string) $media->filename, (string) $media->type, 'shareImage'), ENT_QUOTES) . "' title='Crop and adjust'><i class='cmsIcon cmsIcon-pen'></i></a>"
			. "<button type='button' data-on_click='sidebar/resetShareImage' title='Use the site image' aria-label='Use the site image' onmousedown='event.stopPropagation()' onpointerdown='event.stopPropagation()'><i class='cmsIcon cmsIcon-arrow-rotate-left'></i></button>"
			. "</div>";
	}

	public function refreshSeoPreview(): void {
		\client::replaceInner('aside .cmsSeoPreview', $this->seoPreview());
	}

	function getDefaultState() {
		if ($this->isSection) {
			return (object) ['open' => false];   // the fold, closed until opened
		}
		if ($this->isGroup) {
			return (object) [];
		}
		return (object) [
				'activeSidebarTemplates' => [],
				'sidebarGroup' => null,
				'sidebarWidth' => 280,
				'sidebarCollapsed' => false,
		];
	}

}
