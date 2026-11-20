<?php
/**
 * The requests of an svg: its source (`updateSVG`, from stage.tpl.php).
 * The file editor runs this before its own (Panel::runActions()), at any of its addresses.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Panel $this
 */

use Systopic\Cms\Pages\PageAccess;
use Systopic\System\Tables\Medias\Files as MediaFiles;

$row = $this->mediaRow;
if ($this->action === 'updateSVG') {
	if ($row !== NULL && !PageAccess::refuses($row->node)) {
		MediaFiles::writeOriginal($row, (string) http::values('svgContent'));
		$this->saveMedia();
		client::replace('img#svgPreviewImage', "<img id='svgPreviewImage' src='{$this->media->setFormat('original')->url}?v=" . time() . "' />");
	}
}
