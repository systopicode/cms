<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Admin\Pages;

use Systopic\System\Tables\Nodes\Model as Node;

use Systopic\Cms\Pages\PageAccess;
use Systopic\Cms\Pages\Sites\SiteRegistry;
use Systopic\Cms\Tables\Sites\Model as SiteRow;
use Systopic\Db\Scope;
use Systopic\Db\UnitOfWork;
use Systopic\System\Panels\Root\Admin\Panel as CmsPanel;
use Systopic\System\Tables\Nodes\Operator as NodeOperator;
use Systopic\Cms\Tables\Pages\Operator as PageOperator;
use Systopic\Cms\Queries\Branch\Branch;
use Systopic\Cms\Tables\Pages\Languages;
use Systopic\Cms\Tables\Pages\Page;
use Systopic\Cms\Queries\PageTree\PageTree;
use Systopic\Cms\Queries\Params;

/**
 * admin/pages — the page tree, the page, and what can be done to them.
 *
 * Everything here reads and writes through one `Scope`: the tree, the page
 * being edited and every row an action touches share its identity map. A
 * label written by an action is the label the tree draws afterwards — the
 * same object, not a second copy that has to be reloaded.
 *
 * The preview in `main.tpl.php` is the exception. It renders the project's
 * templates, and those still speak the legacy page class; it gets id and
 * language from here and builds its own route.
 */
class Panel extends CmsPanel {

	/** Media group of files dropped or pasted into a text editor. */
	public const TEXT_GROUP = 'text';

	public $icon = 'cmsIcon cmsIcon-duotone cmsIcon-files';

	public function buildGranted() {
		return \Systopic\System\Auth\Session::hasOneRole('cmsAdmin', 'cmsEditor', 'cmsPageEditor');
	}

	/**
	 * The request's scope: connection, identity map, result cache. Everything
	 * this panel reads and writes goes through it, so the tree, the page and
	 * every action see one object per row — and a commit throws away the
	 * results that read a written table.
	 *
	 * The panel keeps nothing of its own. The tree and the page are objects
	 * built on query results, and they live where those results live: in the
	 * scope, under a key that says what they were built for. A commit clears
	 * them along with the results (`Scope::committed()`); a change of page or
	 * language is a different key. Nobody here has to remember to throw
	 * anything away.
	 */
	public Scope $scope {
		get => Scope::default();
	}

	/**
	 * What the page queries are read under.
	 *
	 * Preview on — the CMS shows unpublished pages —, links pointing into the
	 * panel, only page nodes in the tree. A change of language is a new set of
	 * parameters: a row belongs to exactly one language.
	 */
	public Params $params {
		get => new Params(
			lang:     (string) $this->state->lang,
			preview:  TRUE,
			root:     (string) \http::$root,
			siteUrl:  (string) \http::$siteUrl,
			inCms:    TRUE,
			nodeType: 'page',
			readAcls: PageAccess::readAcls(),
			owner:    (int) \Systopic\System\Auth\Session::id(),
		);
	}

	/**
	 * The page tree in the pylon — one per request, parameters and active
	 * page.
	 *
	 * The rows arrive per **level**, and they are the rows of `$scope` — so
	 * `$this->page` and the tree entry of the same node are one object. The
	 * folds are not part of the key: `fold()` changes the tree in hand and
	 * writes the state, both stay in step.
	 */
	public PageTree $tree {
		get {
			$params = $this->params;
			$active = $this->state->id ? (int) $this->state->id : NULL;
			return $this->scope->objectFor(PageTree::class, $params->key() . '#' . $active, function () use ($params, $active): PageTree {
				$tree = new PageTree($this->scope, $params, (array) ($this->state->openFolds ?? []), $active);
				// Successor of tree::autoFoldActiveNode(): unfold the branch the
				// selected page sits on — but only if it is not visible anyway.
				$tree->revealActive();
				$this->state->openFolds = $tree->openFolds();
				return $tree;
			});
		}
	}

	/**
	 * The page the panel shows — whole, every column.
	 *
	 * NULL when no page is selected or the node does not exist. A node without
	 * a page in the current language is a Page with `$row` NULL: the panel then
	 * offers to create it.
	 *
	 * `Page::load()` costs nothing the second time — the query is in the result
	 * cache and `Page::of()` hands back the same object — so nothing is kept
	 * here.
	 */
	public ?Page $page {
		get {
			$id = (int) ($this->state->id ?? 0);
			return $id > 1 ? Page::load($this->scope, $this->params, $id) : NULL;
		}
	}

	/**
	 * The media groups of the project — `$media_groups` in settings.php
	 * (label, public, file_extension). Not loaded into the panel scope, so
	 * read from the global, else from settings.media_groups.php.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public array $mediaGroups {
		get {
			global $media_groups;
			if (!is_array($media_groups ?? NULL) || $media_groups === []) {
				$file = (\fs::$siteRoot !== NULL && \fs::$siteRoot->exists) ? (string) \fs::$siteRoot . 'settings.media_groups.php' : '';
				if ($file !== '' && \fs::isFile($file)) {
					$media_groups = include \fs::toNative($file);
				}
			}
			return is_array($media_groups ?? NULL) ? $media_groups : [];
		}
	}

	/**
	 * The media library of the page: group name => its entries
	 * (Queries\MediaLibrary). The project's groups in their order, then
	 * 'unassigned' for every medium whose group the project does not know.
	 *
	 * @return array<string, list<array{node: \Systopic\System\Tables\Nodes\Model, media: ?\Systopic\System\Tables\Medias\Model, alias: bool, originalParentId: ?int}>>
	 */
	public array $library {
		get {
			$id = (int) ($this->state->id ?? 0);
			$groups = array_fill_keys(array_keys($this->mediaGroups), []);
			$groups += [self::TEXT_GROUP => []];
			foreach (\Systopic\System\Queries\MediaLibrary\MediaLibrary::of($this->scope, $id) as $entry) {
				// a media node without a medium (an empty folder) lands in 'unassigned', as before
				$group = (string) ($entry['media']?->group ?? '');
				if (!array_key_exists($group, $groups) || $group === 'unassigned') {
					$group = 'unassigned';
				}
				$groups[$group][] = $entry;
			}
			foreach ([self::TEXT_GROUP, 'unassigned'] as $optional) {
				if (isset($groups[$optional]) && $groups[$optional] === [] && !isset($this->mediaGroups[$optional])) {
					unset($groups[$optional]);
				}
			}
			// a group for some templates only ('templates' => 'a;b', Media Settings): left out
			// elsewhere, media it holds there move to 'unassigned' so they stay reachable
			$template = (string) ($this->page?->templateName ?? '');
			foreach ($this->mediaGroups as $name => $info) {
				$only = $info['templates'] ?? '';
				$only = array_filter(array_map('trim', is_array($only) ? $only : explode(';', (string) $only)));
				if ($only === [] || $template === '' || in_array($template, $only, true) || !isset($groups[$name])) {
					continue;
				}
				if ($groups[$name] !== []) {
					$groups['unassigned'] = [...($groups['unassigned'] ?? []), ...$groups[$name]];
				}
				unset($groups[$name]);
			}
			return $groups;
		}
	}

	/**
	 * The thumbnail address of a medium in the media library: the mmlThumb
	 * format, the poster of a video, the file itself for an svg.
	 */
	public function thumbOf(\Systopic\System\Tables\Medias\Model $media): string {
		$siteRoot = (string) \http::$siteRoot;
		$mime = (string) $media->mimetype;
		$filename = (string) $media->filename;
		$folders = \Systopic\System\Tables\Medias\Files::idPath((int) $media->id);
		if ($mime === 'image/svg+xml') {
			return "{$siteRoot}var/original/$folders$filename";
		}
		if ($media->type === 'video') {
			$stem = preg_replace('/\.(mp4|webm|mov|m4v)$/i', '', $filename);
			foreach (["var/original/$folders$stem.webp", "var/original/$folders$filename.webp"] as $poster) {
				$abs = \fs::$siteRoot . $poster;
				if (\fs::isFile($abs) && \fs::filesize($abs) > 0) {
					return $siteRoot . $poster . '?v=' . \fs::filemtime($abs);
				}
			}
		}
		$ext = in_array($mime, ['image/webp', 'image/png', 'image/jpeg'], TRUE) ? '' : '.webp';
		$thumb = "var/formats/mmlThumb/$folders$filename$ext";
		// The thumb may not exist yet: replace deletes it and the next request
		// builds it. The original's time still changes, so the address does.
		// fs::filemtime, because PHP's own filemtime fails on an internal path.
		$made = @\fs::filemtime(\fs::$siteRoot . $thumb)
			?: @\fs::filemtime(\fs::$siteRoot . "var/original/$folders$filename");
		return $siteRoot . $thumb . ($made ? '?v=' . $made : '');
	}

	/**
	 * A tile that has no picture on purpose. Audio has no cover, a SketchUp
	 * file has no preview. Neither is a broken image.
	 *
	 * @return array{mark: bool, icon: string}
	 */
	public function tileFace(?\Systopic\System\Tables\Medias\Model $media): array {
		$mime = strtolower((string) $media?->mimetype);
		$file = strtolower((string) $media?->filename);
		$type = (string) $media?->type;
		if (str_starts_with($mime, 'audio/') || preg_match('/\.(mp3|m4a|wav|ogg|aac)$/', $file)) {
			return ['mark' => true, 'icon' => 'music'];
		}
		if ($type === 'sketchup' || str_contains($mime, 'sketchup') || str_ends_with($file, '.skp')) {
			return ['mark' => true, 'icon' => 'cube'];
		}
        if (!\Systopic\System\Media\Presentation::hasThumbnail($mime)) {
            return ['mark' => true, 'icon' => \Systopic\System\Media\Presentation::icon($file)];
        }
		$icon = $type;
		if ($type === 'application' || $type === 'pdf') {
			$icon = 'file-pdf';
		} elseif ($mime === 'image/gif') {
			$icon = 'gif';
		} elseif (\Systopic\System\Tables\Medias\External::is($type)) {
			$icon = 'circle-play';
		} elseif ($mime === 'image/svg+xml') {
			$icon = 'vector-polygon';
		} elseif ($icon === '') {
			$icon = 'file';
		}
		return ['mark' => false, 'icon' => $icon];
	}

	/** The crossed-out icon a thumb shows when it does not load (`img[data-broken]`, gui/ui.js). */
	public function brokenIcon(?\Systopic\System\Tables\Medias\Model $media): string {
		$type = (string) $media?->type;
		return match (true) {
			$type === 'image' => 'image-slash',
			$type === 'video', \Systopic\System\Tables\Medias\External::is($type) => 'video-slash',
			default => 'file-slash',
		};
	}

	/** The tag a text editor shows for a medium, null for a type it cannot show. */
    /** The page behind informational lightboxes, including its current language. */
    public function currentViewHref(): string
    {
        $query = array_filter(['id' => (int) ($this->state->id ?? 0), 'lang' => (string) $this->params->lang], static fn($value) => $value !== '' && $value !== 0);
        return $this->href . ($query !== [] ? '?' . http_build_query($query) : '');
    }

	public function editorTag(int $nodeId): ?string {
		$row = $this->mediaOfNode($nodeId);
		if ($row === NULL) {
			return NULL;
		}
		$file = new \Systopic\System\Tables\Medias\Media($row);
        $stored = \Systopic\Cms\Media\EditorTag::stored($nodeId, (string) $file->filename, (string) $file->label, (string) $file->mimetype, (string) array_key_first(\mediaFormats::get()));
		return $stored === null ? null : (new \Systopic\Cms\Refs\Resolver($this->scope, $this->params))->renderForEditor($stored);
	}

	/** The medium of a media node — what a library action is about. */
	public function mediaOfNode(int $nodeId): ?\Systopic\System\Tables\Medias\Model {
		return $nodeId > 0
			? (\Systopic\System\Queries\MediaNodes\MediaNodes::byNodeIds($this->scope, [$nodeId])[$nodeId] ?? NULL)
			: NULL;
	}

	/**
	 * Any page by node id, under the panel's scope.
	 *
	 * The shown page when it is that one — no second load, no second object.
	 */
	public function pageFor(?int $nodeId): ?Page {
		if ($nodeId === NULL || $nodeId < 2) {
			return NULL;
		}
		$shown = $this->page;
		if ($shown !== NULL && $shown->id === $nodeId) {
			return $shown;
		}
		return Page::load($this->scope, $this->params, $nodeId);
	}

	/**
	 * The node of a page or of a medium — what a tag or a right is about,
	 * loaded, so its groups can be asked.
	 */
	public function nodeFor(int $nodeId): ?\Systopic\System\Tables\Nodes\Model {
		return $this->pageFor($nodeId)?->node ?? $this->mediaOfNode($nodeId)?->node;
	}

	/**
	 * A page in a given language — the panel's parameters for its language,
	 * the same in another one (the inline editors say which language they
	 * edit). The scope is the same either way: one identity map.
	 */
	public function pageIn(?int $nodeId, ?string $lang): ?Page {
		if ($lang === NULL || $lang === '' || $lang === $this->params->lang) {
			return $this->pageFor($nodeId);
		}
		if ($nodeId === NULL || $nodeId < 2) {
			return NULL;
		}
		return Page::load($this->scope, $this->params->in($lang), $nodeId);
	}

	/** A unit of work on the request's scope: it invalidates and announces. */
	public function uow(): UnitOfWork {
		return $this->scope->uow();
	}

	/** The id of the user acting — for created/modified stamps. */
	public function userId(): ?int {
		$id = \Systopic\System\Auth\Session::current()?->id ?? NULL;
		return $id === NULL ? NULL : (int) $id;
	}

    /** Raw graph edges shared by context-menu and sidebar validation. */
    public function relationNode(int $id): ?array {
        $rows = $this->scope->connection->all('SELECT node_original_node_id AS original, node_redirect_node_id AS redirect, node_parent_node_id AS parent, node_type AS type, node_is_trashed AS trashed FROM cms_nodes WHERE node_id = :id', [':id' => $id]);
        return $rows[0] ?? null;
    }

    public function setPageRelation(int $source, int $target, string $kind): void {
        if (!in_array($kind, ['alias', 'redirect'], true)) throw new \DomainException('Unknown page relation.');
        $page = $this->pageFor($source);
        if ($page === null || $page->row === null || $page->node->isTrashed) throw new \DomainException('Choose a page outside the trash.');
        if (PageAccess::refuses($page->node)) throw new \DomainException('You may not change this page.');
        if ($kind === 'alias' && !$page->isAlias) throw new \DomainException('Only an existing alias can change its original.');
        if ($target > 1) {
            \Systopic\Cms\Pages\RelationRules::target($source, $target, $this->relationNode(...));
            if ($kind === 'alias') {
                $target = $this->pageFor($target)?->contentNodeId ?? 0;
                \Systopic\Cms\Pages\RelationRules::target($source, $target, $this->relationNode(...));
                \Systopic\Cms\Pages\RelationRules::placement($target, (int) $page->node->relationId('parentNode'), $this->relationNode(...));
            }
        } elseif ($kind === 'alias') throw new \DomainException('An alias needs an original page.');
        if ($kind === 'alias') $page->node->originalNode = Node::withId($target);
        else $page->node->redirectNode = $target > 1 ? Node::withId($target) : null;
        $this->uow()->persist($page->node)->commit();
        if ($kind === 'alias') \Systopic\Cms\Pages\Sites\NodeSite::rebuildBranch($source);
    }

    /** A public address checked with public visibility, not preview parameters. */
    public function liveHref(int $id, ?string $lang = null): ?string {
        $page = Page::load($this->scope, new Params(lang: $lang ?? $this->params->lang,
            root: (string) \http::$siteRoot, siteUrl: (string) \http::$siteUrl), $id);
        if ($page === null || $page->row === null || !$page->meta->published) return null;
        foreach ($page->ancestors as $ancestor) {
            if ($ancestor->node->type === 'page' && !$ancestor->meta->published) return null;
        }
        return $page->rewritepaths->href;
    }

    /** Render the existing tree actions next to their Pages panel handlers. */
	public function printTreeContextmenu($nodeId): string {
		$node = $this->tree->getNode($nodeId === NULL ? NULL : (int) $nodeId);
		if ($node === NULL) {
			return '';
		}
		$tree = $this->tree;
		$active = $tree->active;
		ob_start();
		include __DIR__ . '/contextmenu.tpl.php';
		return (string) ob_get_clean();
	}

	/*	 * ************** SITES ****************** */

	/** Does main show a site instead of a page? */
	public function editsSite(): bool {
		if (in_array($this->state->editSite ?? NULL, ['default', 'new'], true)) {
			return TRUE;
		}
		return $this->page?->node->type === SiteRegistry::NODE_TYPE;
	}

	/** The site on screen — NULL for a new one, or while there is no default site yet. */
	public function siteOnScreen(): ?SiteRow {
		return match ($this->state->editSite ?? NULL) {
			'new' => NULL,
			'default' => SiteRegistry::defaultSite($this->scope),
			default => $this->page ? SiteRegistry::byNodeId($this->scope, $this->page->id) : NULL,
		};
	}

	/** Pages directly below a site — the topmost pages for the default site. */
	public function sitePageCount(SiteRow $site): int {
		return (int) $this->scope->connection->value(
			"SELECT COUNT(*) FROM cms_nodes WHERE node_type = 'page' AND !node_is_trashed AND COALESCE(node_parent_node_id, 0) = :parent",
			[':parent' => $site->relationId('node') ?? 0],
		);
	}

	/**
	 * Where the site answers on this machine — absolute, for the link that
	 * opens it in a new window. NULL when the site has no address here.
	 */
	public function siteUrl(SiteRow $site): ?string {
		$config = \Systopic\System\Config\Config::current();
		$configSite = $config?->sites->byName($site->name);
		return $configSite === NULL ? NULL : $config->baseUrlFor($configSite);
	}

	/** The site an action came from — `data-site_id`. */
	public ?SiteRow $actionSite {
		get => ($id = (int) \http::dataset('site_id')) > 0 ? SiteRegistry::byId($this->scope, $id) : NULL;
	}

	function onLoadThisClass() {
		if (!$this->inPath()) {
            if (!empty($this->state->pagesUiActive)) {
                $this->state->pagesUiActive = false;
                $this->child('sidebar')?->updateView('main');
                $this->updateView('controls');
            }
			return;
		}
        $this->state->pagesUiActive = true;
        if ($this->action === 'cmsPageSearch') return;
		if (\http::method() !== 'GET') {
			return;
		}
		// A page address closes the search. The hit may be the page already
		// open: then nothing else redraws the pylon, so the tree comes back here.
		$hadSearch = trim((string) ($this->state->cmsPageSearchstring ?? '')) !== '';
		$idBefore = $this->state->id;
		$this->checkIfPageChanged();
        if (\http::get('relations') === 'aliases') {
            $sidebar = $this->child('sidebar');
            if ($sidebar !== null) {
                $sidebar->state->sidebarGroup = 'publishing';
                $sidebar->state->sidebarCollapsed = false;
                $sidebar->updateView('main');
            }
            \client::callFunction('focusPageRelations', ['aliases']);
        }

		if ($hadSearch && (string) ($this->action ?? '') === '' && $this->state->id === $idBefore && trim((string) ($this->state->cmsPageSearchstring ?? '')) !== '') {
			$this->state->cmsPageSearchstring = '';
			$this->state->cmsPageSearchResults = '';
			$this->updateView('pylon');
			$this->updateView('tree');
		}
		// The media editor is open: remember that, and leave the preview.
		// It closes with the next request. The pictures were edited in
		// between, so the preview is drawn again — it still has the old
		// addresses. isSelected() is not used: the static tree is not the
		// one this request built, so it would never notice the editor.
		if ($this->mediaEditorOpen()) {
			$this->state->refreshPreview = true;
		} elseif (!empty($this->state->refreshPreview)) {
			$this->state->refreshPreview = false;
			$this->updateView('main');
		}
	}

	/** The media editor is the open panel — the preview stays as it is until it closes. */
	function mediaEditorOpen(): bool {
		$node = $this->childSelected;
		while ($node !== null && $node->childSelected !== null) {
			$node = $node->childSelected;
		}
		return $node !== null && $node->name === 'file';
	}

	function checkIfPageChanged() {
		// ?site=default (globe in the tree navi) or ?site=new (add menu): main
		// shows the site editor instead of a page; no page is active meanwhile.
		$siteParam = \http::get('site', NULL);
		if ($siteParam === 'default' || $siteParam === 'new') {
			if (($this->state->editSite ?? NULL) !== $siteParam || $this->state->id !== NULL) {
				$this->state->editSite = $siteParam;
				$this->state->id = NULL;
				$this->pageChanged();
			}
			return;
		}
		$idChanged = \http::get('id', $this->state->id) !== $this->state->id;
		$langChanged = \http::get('lang', $this->state->lang) !== $this->state->lang;
		if ($idChanged || $langChanged) { // standard click
			$this->state->id = (int) \http::get('id', $this->state->id);
			if ($this->state->id < 2) {
				$this->state->id = NULL; // don't load default page
			}
			$this->state->lang = \http::get('lang', $this->state->lang);
			$this->state->editSite = NULL;
			$this->pageChanged();
		}
	}

	/**
	 * Another page is shown: every view that shows it is redrawn.
	 *
	 * Nothing to throw away — the tree and the page are keyed by the active
	 * id in the scope, so the next read builds them for the new one.
	 */
	function pageChanged() {
		$this->state->cmsPageSearchstring = '';
		$this->state->cmsPageSearchResults = '';
		$this->updateView('main');
		$this->updateView('controls');
		$this->updateView('status');
		$this->updateView('pylon'); // Pylon mit neuem, damit MML-Wrapper die Klasse mml-empty verliert und sichtbar wird
		$this->updateView('mml');
		$this->updateView('tree');
		$this->child('sidebar')?->updateView('main');
	}

	function getDefaultState() {
		return (object) [
					'id' => NULL,
					'lang' => Languages::default(),
					'childSelected' => NULL,
					'pylonWidth' => 250,
					'pylonFooterHeight' => 300,
					'metaHeight' => 300,
					'openFolds' => [],
					'cmsPageSearchstring' => '',
                    'cmsPageLastSearch' => '',
					'cmsPageSearchResults' => '',
					'treeMode' => 'normal',
					'editSite' => NULL, // 'default' | 'new' while main shows the site editor without a node
					'refreshPreview' => false, // the media editor was open; the preview is drawn again when it closes
		];
	}

	/*	 * ************** ACTION HELPERS ****************** */

	/**
	 * Write one field of a page — the inline editors, the HTML lightbox and
	 * the link dialogs all end up here.
	 *
	 * @return bool whether something was written
	 */
	function updateField(int $nodeId, ?string $lang, string $field, mixed $value): bool {
		$page = $this->pageIn($nodeId, $lang);
		if ($page?->row === NULL || $value === NULL) {
            \message::error('This field could not be saved. Reload the page and try again.');
			return FALSE;
		}
		if (PageAccess::refusesEdit($page)) {
			return FALSE;
		}
		try {
			// the editor sends urls with the reference beside them - store the reference
			$value = \Systopic\Cms\Refs\Resolver::normalize(ltrim((string) $value, "\r\n"));
			if (PageOperator::propertyOf($field) === null) throw new \InvalidArgumentException('Unknown page field.');
		} catch (\InvalidArgumentException $e) {
			\message::error($e->getMessage());
			return FALSE;
		}
        $expected = (string) \http::dataset('field_version', \http::post('field_version', ''));
        try {
            $this->uow()->persist($page->row)->commit(function ($connection) use ($page, $field, $value, $expected): void {
                // Compare and write under one row lock; live updates are
                // published by the UoW only after the transaction commits.
                $current = $connection->value('SELECT `page_' . $field . '` FROM cms_pages WHERE page_id = ? FOR UPDATE', [$page->row->id]);
                // Merely opening/leaving a field is not a content change. In
                // particular, do not touch its timestamp and broadcast a new
                // revision to every editor for an unchanged blur-save.
                if ((string) $current === (string) $value) return;
                if ($expected !== '' && !hash_equals(hash('sha256', (string) $current), $expected) && (string) $current !== (string) $value) {
                    throw new \Systopic\Cms\Pages\EditConflict(hash('sha256', (string) $current));
                }
                PageOperator::assign($page->row, $field, $value);
                PageOperator::touch($page->row, $this->userId());
            });
        } catch (\Systopic\Cms\Pages\EditConflict $conflict) {
            \client::callFunction('pageEditConflict', [$nodeId, $lang ?? $page->language, $field, $conflict->getMessage(), \Systopic\Cms\Pages\FieldVersion::latestMetadata($this->scope->connection, (int) $page->row->id)]);
            \message::error('This field was changed in another editor. Your changes have not been overwritten.');
            return false;
        }
        $property = PageOperator::propertyOf($field);
        $saved = $page->row->{$property};
        if ($saved instanceof \DateTimeInterface) $saved = $saved->format('Y-m-d H:i:s');
        elseif (is_bool($saved)) $saved = $saved ? '1' : '0';
        else $saved = (string) $value;
        \client::callFunction('pageEditorVersion', [$nodeId, $lang ?? $page->language, $field, hash('sha256', (string) $saved), true]);
        return true;
	}

	/**
	 * Publish or hide a page with everything below it, in this language —
	 * the pages the user may change; the others stay as they are.
	 *
	 * @return array{int, int} how many rows changed, how many were left alone
	 */
    /** Same branch as the action; counts are a preview, re-evaluated on execution. */
    public function branchConfirmation(int $nodeId, string $action): string
    {
        $branch = Branch::of($this->scope, $this->params, $nodeId);
        if ($action === 'deletePage') {
            return 'Move this branch to the trash? ' . count($branch->nodes)
                . ' tree items, including attached media, are affected in every language. '
                . 'This can be undone from Trash. References from other pages may stop working.';
        }
        $publish = $action === 'publishAll';
        $changed = array_filter($branch->rows, static fn($row): bool => (bool) $row->isPublished !== $publish);
        return ($publish ? 'Publish' : 'Unpublish') . ' this branch? ' . count($changed)
            . ' page records will change in language “' . $this->params->lang . '”. '
            . 'Other languages and alias originals outside this branch stay unchanged. '
            . ($publish ? 'Publishing dates still apply.' : 'Visitors will no longer be able to open these pages normally.');
    }

	function publishBranch(int $nodeId, bool $published): array {
		$branch = Branch::of($this->scope, $this->params, $nodeId);
		$nodes = [];
		foreach ($branch->nodes as $node) {
			$nodes[$node->id] = $node;
		}
		$uow = $this->uow();
		$count = 0;
		$skipped = 0;
		foreach ($branch->rows as $id => $row) {
			if ((bool) $row->isPublished === $published) {
				continue;
			}
			if (!PageAccess::mayWrite($nodes[$id] ?? NULL)) {
				$skipped++;
			} else {
				$row->isPublished = $published;
				PageOperator::touch($row, $this->userId());
				$uow->persist($row);
				$count++;
			}
		}
		$uow->commit();
		return [$count, $skipped];
	}

	/**
	 * Into the trash with everything below it — pages and their media, all
	 * languages (the trash is a property of the node).
	 *
	 * All or nothing: a branch with a node the user may not change — maybe
	 * one they do not even see — stays where it is.
	 *
	 * @return int|null how many nodes changed; NULL when refused
	 */
	function trashPage(int $nodeId, bool $trashed = TRUE): ?int {
		$branch = Branch::of($this->scope, $this->params, $nodeId);
		foreach ($branch->nodes as $node) {
			if (!PageAccess::mayWrite($node)) {
				\message::error('There are pages below you may not change (their content groups): nothing was ' . ($trashed ? 'deleted.' : 'recovered.'));
				return NULL;
			}
		}
		$changed = NodeOperator::trash($branch->nodes, $trashed);
		$uow = $this->uow();
		foreach ($changed as $node) {
			$uow->persist($node);
		}
		$uow->commit();
		return count($changed);
	}

	/**
	 * Out of the trash with everything below it.
	 *
	 * Not called `recoverPage`, the name of the action that calls it.
	 */
	function recoverBranch(int $nodeId): ?int {
		return $this->trashPage($nodeId, FALSE);
	}

	public function searchHits(\Systopic\System\Sys\Search $search): void {
		$pages = \http::$root . 'admin/pages/?id=';
		foreach (\Systopic\Cms\Queries\PageSearch\PageSearch::everywhere($search->scope, $search->query) as $page) {
			$search->add('Pages', 'cmsIcon-file-lines', $page['label'] !== '' ? $page['label'] : 'Untitled', strtoupper($page['lang']), $pages . $page['node'] . '&lang=' . $page['lang']);
		}
		foreach (\Systopic\Cms\Queries\PageSearch\PageSearch::media($search->scope, $search->query) as $media) {
			$file = strtolower($media['file']);
			$icon = match (true) {
				$media['type'] === 'image' => 'cmsIcon-image',
				$media['type'] === 'video' => 'cmsIcon-film',
				$media['type'] === 'audio' => 'cmsIcon-music',
				$media['type'] === 'pdf' || str_ends_with($file, '.pdf') => 'cmsIcon-file-pdf',
				in_array($media['type'], ['youtube', 'vimeo'], true) => 'cmsIcon-circle-play',
				$media['type'] === 'sketchup' || str_ends_with($file, '.skp') => 'cmsIcon-cube',
				default => 'cmsIcon-file',
			};
			$title = $media['label'] !== '' ? $media['label'] : $media['file'];
			$search->add('Media', $icon, $title, $title === $media['file'] ? '' : $media['file'], $pages . $media['page']);
		}
	}
}
