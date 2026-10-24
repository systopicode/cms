<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Pages\Editor\File;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;
use Systopic\System\Tables\Medias\Model as MediaRow;
use Systopic\System\Tables\Medias\Media;

/**
 * The file editor of a medium (lightbox over cms/pages): label, file name,
 * the image in its formats, an svg's source, a video's poster.
 *
 *   cms/pages/editor/file/?target_id=<media node>
 *
 * The node id is kept in the state, so an action (which sends only its
 * dataset) and the redraw after it know the medium too — the legacy template
 * computed it only when no action ran and warned on every save.
 */
class Panel extends CmsPanel {

	function getDefaultState() {
		return (object) ['targetId' => 0, 'format' => ''];
	}

	function onLoad() {
		$id = (int) \http::params('target_id', \http::dataset('node_id', 0));
		if ($id > 0) {
			$this->state->targetId = $id;
		}
	}

	/** The media node on screen. */
	public int $targetId {
		get => (int) ($this->state->targetId ?? 0);
	}

	/** The row — dates, users, everything the editor writes. */
	public ?MediaRow $mediaRow {
		get => $this->parent->parent->mediaOfNode($this->targetId);
	}

	/** The medium as the site sees it — addresses, formats. */
	public ?Media $media {
		get => ($row = $this->mediaRow) === NULL ? NULL : new Media($row);
	}

	/** Header of the lightbox. */
	public string $lightboxTitle {
		get => \Systopic\System\Tables\Medias\External::is((string) $this->mediaRow?->type)
			? 'Edit ' . \Systopic\System\Tables\Medias\External::label((string) $this->mediaRow?->type)
			: match ((string) $this->mediaRow?->mimetype) {
			'image/png', 'image/jpg', 'image/jpeg', 'image/gif', 'image/tiff', 'image/webp' => 'Edit image',
			'image/svg+xml', 'image/svg' => 'Edit SVG',
			'application/pdf' => 'Edit PDF',
			'application/zip' => 'Edit ZIP',
			'video/mp4' => 'Edit video',
			default => 'Edit file',
		};
	}

	/** The pages panel — scope, user, the medium by node id. */
	public function pages(): \Systopic\Cms\Panels\Root\Cms\Pages\Panel {
		return $this->parent->parent;
	}
}
