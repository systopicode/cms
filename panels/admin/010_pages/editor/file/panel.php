<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Admin\Pages\Editor\File;

use Systopic\System\Panels\Root\Admin\Panel as CmsPanel;
use Systopic\System\Tables\Medias\Model as MediaRow;
use Systopic\System\Tables\Medias\Media;

/**
 * The file editor of a medium (lightbox over admin/pages): the frame -
 * header, label, file name, tags, delete - and the stage of its kind.
 *
 *   admin/pages/editor/file/?target_id=<media node>                the stage of its kind
 *   admin/pages/editor/file/image/(master|crops)/?target_id=…      the image editor, a tab
 *   admin/pages/editor/file/image/<action>?target_id=…             the image editor's requests
 *   admin/pages/editor/file/(video|svg)/<action>?target_id=…       those of a video, an svg
 *
 * A kind with its own requests or tabs is a panel below this one
 * (image, video, svg): its `stage.tpl.php` is shown in the frame
 * (`stage()`), its `actions.php` answers its requests - at whichever
 * address of the file editor they arrive (`runActions()`: the kind's,
 * then this folder's). The panels below share this class (`bequests`;
 * image has its own, for its tabs). Viewers without requests (pdf, audio,
 * SketchUp) are templates of this panel; an embed shows beside the stage.
 *
 * Whichever of them the address selects renders the frame: each has a
 * `lightbox.tpl.php` (and `tags.tpl.php`) that includes the one above. The
 * medium is this panel's state (`targetId`), so an action (which sends
 * only its dataset) and the redraw after it know it too.
 */
class Panel extends CmsPanel {

	/** The kinds below inherit this class (video, svg); image has its own. */
	public bool $bequests = true;

	/** The kinds with a panel of their own, by the stage they show. */
	public const KINDS = \Systopic\System\Media\Presentation::EDITOR_PANELS;

	function getDefaultState() {
		return (object) ['targetId' => 0, 'format' => ''];
	}

	function onLoad() {
		$id = (int) \http::params('target_id', \http::dataset('node_id', 0));
		if ($id > 0) {
			$this->file()->state->targetId = $id;
		}
		// An address with the medium starts afresh: what it asks for to show first, or nothing.
		if (\http::params('target_id', NULL) !== NULL) {
			$format = \http::params('format', '');
			$this->file()->state->format = is_string($format) ? $format : '';
		}
	}

	/** The file editor itself - also for the panels below it. */
	public function file(): self {
		$panel = $this;
		while ($panel->parent instanceof self) {
			$panel = $panel->parent;
		}
		return $panel;
	}

	/** The media node on screen. */
	public int $targetId {
		get => (int) ($this->file()->state->targetId ?? 0);
	}

	/** The row — dates, users, everything the editor writes. */
	public ?MediaRow $mediaRow {
		get => $this->pages()->mediaOfNode($this->targetId);
	}

	/** The medium as the site sees it — addresses, formats. */
	public ?Media $media {
		get => ($row = $this->mediaRow) === NULL ? NULL : new Media($row);
	}

	/** Which kind of stage the medium has: one of KINDS, or a viewer of this panel (pdf, audio, sketchup, webm, file). An embed shows beside it. */
	public string $kind {
		get {
			$row = $this->mediaRow;
			return $row === NULL ? 'file' : \Systopic\System\Media\Presentation::editorKind((string) $row->mimetype, (string) $row->filename, (string) $row->type);
		}
	}

	/**
	 * The panel of the medium's kind (image, video, svg) - the one the
	 * address selected, or below the file editor. Null for a viewer.
	 */
	public function kindPanel(): ?self {
		$kind = $this->kind;
		if (!in_array($kind, self::KINDS, true)) {
			return NULL;
		}
		for ($panel = $this; $panel instanceof self; $panel = $panel->parent) {
			if ($panel->name === $kind && $panel->parent === $this->file()) {
				return $panel;
			}
		}
		$child = $this->file()->child($kind);
		return $child instanceof self ? $child : NULL;
	}

	/**
	 * A request at any address of the file editor: the kind's own first
	 * (its actions.php - an svg's source, a video's poster, the image
	 * editor's requests), then the frame's (this folder's actions.php).
	 */
	public function runActions(string $file = 'actions.php'): void {
		$kind = $this->kindPanel();
		$kindFile = $kind === NULL ? '' : rtrim($kind->absPath, '/\\') . DIRECTORY_SEPARATOR . $file;
		if ($kindFile !== '' && is_file($kindFile)) {
			(function () use ($kindFile): void {
				include $kindFile;
			})->call($this);
		}
		parent::runActions(rtrim($this->file()->absPath, '/\\') . DIRECTORY_SEPARATOR . $file);
	}

	/** Shows the kind's `stage.tpl.php` with this panel as `$this`. */
	public function stage(Media $media, Media $original): void {
		$file = rtrim($this->absPath, '/\\') . '/stage.tpl.php';
		(function () use ($file, $media, $original): void {
			include $file;
		})->call($this);
	}

	/** Preview and the shared file details; image editing adds its own tabs. */
	public string $fileTab {
		get => $this->file()->childSelected?->name === 'details' ? 'details' : 'preview';
	}

	public function tabs(): string {
		$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
		$html = '<nav class="ceTabs" role="tablist" aria-label="What to edit">';
		$preview = $this->kind === 'video' ? 'Video & poster' : 'Preview';
		foreach (['preview' => $preview, 'details' => 'File details'] as $name => $label) {
			$url = $this->file()->href . ($name === 'details' ? 'details/' : '') . '?target_id=' . $this->targetId;
			$html .= '<a class="ajax" role="tab" href="' . $e($url) . '" aria-selected="' . ($this->fileTab === $name ? 'true' : 'false') . '"><span>' . $e($label) . '</span></a>';
		}
		return $html . '</nav>';
	}

	/** Header of the lightbox. */
	public string $lightboxTitle {
		get => \Systopic\System\Tables\Medias\External::is((string) $this->mediaRow?->type)
			? 'Edit ' . \Systopic\System\Tables\Medias\External::label((string) $this->mediaRow?->type)
			: match ($this->kind) {
				'image' => 'Edit image',
				'gif' => 'Edit GIF',
				'svg' => 'Edit SVG',
				'pdf' => 'Edit PDF',
				'video', 'webm' => 'Edit video',
				'audio' => 'Edit audio',
				'sketchup' => 'Edit SketchUp',
				default => match ((string) $this->mediaRow?->mimetype) {
					'image/tiff' => 'Edit image',
					'application/zip' => 'Edit ZIP',
					default => 'Edit file',
				},
			};
	}

	/** Stamps the medium (who, when) and saves it - after a kind changed its file. */
	public function saveMedia(): void {
		$row = $this->mediaRow;
		if ($row !== NULL) {
			\Systopic\System\Tables\Medias\Operator::touch($row, $this->pages()->userId());
			$this->pages()->uow()->persist($row)->commit();
		}
	}

	/** The pages panel — scope, user, the medium by node id. */
	public function pages(): \Systopic\Cms\Panels\Root\Admin\Pages\Panel {
		$panel = $this->parent;
		while (!$panel instanceof \Systopic\Cms\Panels\Root\Admin\Pages\Panel) {
			$panel = $panel->parent;
		}
		return $panel;
	}
}
