<?php
/**
 * The file editor — label, file name, svg source, video poster, the image
 * format on screen.
 *
 * Dropped with the ORM round: `changeFileParam` (no caller; it wrote a column
 * named by the request) and `updateJunction` (its form in settings.php was
 * never printed — the loop over an empty list began with `break`).
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Editor\File\Panel $this
 */

use Systopic\System\Tables\Medias\Files as MediaFiles;
use Systopic\System\Tables\Medias\Operator as MediaOperator;

$pages = $this->pages();
$row = $this->mediaRow;

/** Stamp and save the medium. */
$save = static function () use ($pages, $row): void {
	MediaOperator::touch($row, $pages->userId());
	$pages->uow()->persist($row)->commit();
};

switch ($this->action) {
	case 'updateFilename':
		if ($row === NULL) {
			break;
		}
		$name = (string) http::values('filename');
		$old = (string) $row->filename;
		if ($name === '' || str_starts_with($name, '.') || $name === pathinfo($old, PATHINFO_EXTENSION)) {
			message::error('File name cant be empty');
			break;
		}
		if (pathinfo($old, PATHINFO_EXTENSION) !== pathinfo($name, PATHINFO_EXTENSION)) {
			message::error('File extensions don\'t match.');
			break;
		}
		if (MediaFiles::rename($row, str::getSafeName($name))) {
			$save();
			$pages->updateView('mml');
			message::confirm('rename succsessfull');
		} else {
			message::error('rename failed');
		}
		break;

	case 'updateMediaLabel':
		if ($row !== NULL) {
			$row->label = (string) http::values('label');
			$save();
		}
		break;

	case 'updateSVG':
		if ($row !== NULL) {
			MediaFiles::writeOriginal($row, (string) http::values('svgContent'));
			$save();
			client::replace('img#svgPreviewImage', "<img id='svgPreviewImage' src='{$this->media->setFormat('original')->src}?v=" . time() . "' />");
		}
		break;

	case 'createPoster':
		if ($row !== NULL) {
			$data = (string) http::post('value');
			MediaFiles::writePoster($row, (string) base64_decode(substr($data, strpos($data, ',') + 1)));
			$save();
		}
		break;

	case 'changeImageFormat':
		// The select sends the format NAME - the src it maps to is built here,
		// and the choice is remembered, so a re-render of the lightbox marks
		// the entry the image is actually showing (see image.tpl.php).
		$format = (string) http::values('imageEditorFormat');
		$this->state->format = $format;
		if ($this->media !== NULL) {
			client::callFunction('changeSrc', [$this->media->setFormat($format)->src]);
		}
		break;

	case 'startCroping':
		client::callFunction('startCroping');
		break;
}
