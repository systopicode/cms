<?php
/**
 * The file editor — label, file name, svg source, video poster, and the
 * requests of the image editor (Media\CropEditorEndpoint).
 *
 * Dropped with the old editor of the named formats (2026-11-19):
 * `saveImageEdit`, `adjustImage`, `restoreOriginal`, `changeImageFormat`,
 * `startCroping` - the names are aliases of a ratio and a size now.
 *
 * Dropped with the ORM round: `changeFileParam` (no caller; it wrote a column
 * named by the request) and `updateJunction` (its form in settings.php was
 * never printed — the loop over an empty list began with `break`).
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Panel $this
 */

use Systopic\Cms\Pages\PageAccess;
use Systopic\System\Tables\Medias\Files as MediaFiles;
use Systopic\System\Tables\Medias\Operator as MediaOperator;
use Systopic\System\Tables\Medias\Removal as MediaRemoval;

$pages = $this->pages();
$row = $this->mediaRow;

if (in_array($this->action, ['addTag', 'addTagTo_page', 'removeTagFrom_page'], true)) {
	$sidebar = $pages->child('sidebar');
	if ($sidebar) {
		$sidebar->action = $this->action;
		(function () {
			$cmsMediaTagsOnly = true;
			include dirname(__DIR__, 2) . '/sidebar/actions.tags.php';
		})->call($sidebar);
	}
	$this->updateView('tags');
	return;
}

// Everything below changes the medium.
if ($row !== NULL && PageAccess::refuses($row->node)) {
	return;
}

/** Stamp and save the medium. */
$save = static function () use ($pages, $row): void {
	MediaOperator::touch($row, $pages->userId());
	$pages->uow()->persist($row)->commit();
};

switch ($this->action) {
	case 'deleteMedia':
		if ($row !== NULL) {
			MediaRemoval::remove($pages->scope, $row);
		}
		$pages->updateView('mml');
		$pages->updateView('main');
		http::redirect($pages->href);
		break;

	case 'updateFilename':
		if ($row === NULL) {
			break;
		}
		$name = trim((string) http::values('filename'));
		$old = (string) $row->filename;
		$ext = pathinfo($old, PATHINFO_EXTENSION);
		$stem = pathinfo($name, PATHINFO_FILENAME);
		if ($stem === '' || $stem === '.' || $name === '') {
			message::error('File name cant be empty');
			break;
		}
		// The ending is the type. Editing the name keeps it.
		if ($ext !== '' && strcasecmp((string) pathinfo($name, PATHINFO_EXTENSION), $ext) !== 0) {
			$name = $stem . '.' . $ext;
		}
		if ($name === $old) {
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

	case 'updateMediaType':
		if ($row !== NULL) {
			$type = strtolower(trim((string) http::values('type')));
			if (\Systopic\System\Media\TypePolicy::mayChoose($type, \Systopic\System\Auth\Session::isSuperuser())) {
				$row->type = $type;
				$save();
            } elseif ($type !== (string) $row->type) {
                message::error('The file type is detected automatically. Only a superuser can change it.');
			}
		}
		break;

	case 'updateMediaUrl':
		if ($row !== NULL && (\Systopic\System\Tables\Medias\External::is((string) $row->type) || \Systopic\System\Auth\Session::isSuperuser())) {
			\Systopic\System\Tables\Medias\External::storeUrl($row, (string) http::values('url'));
			$save();
		}
		break;

	case 'updateSVG':
		if ($row !== NULL) {
			MediaFiles::writeOriginal($row, (string) http::values('svgContent'));
			$save();
			client::replace('img#svgPreviewImage', "<img id='svgPreviewImage' src='{$this->media->setFormat('original')->url}?v=" . time() . "' />");
		}
		break;

	case 'createPoster':
		if ($row !== NULL) {
			$data = (string) http::post('value');
			$binary = (string) base64_decode(substr($data, strpos($data, ',') + 1));
			$format = (string) http::get('format', 'original');
			$parts = function_exists('videoFormatMediaParts') ? videoFormatMediaParts($this->media) : null;
			if ($format !== '' && $format !== 'original' && $parts && function_exists('videoFormatRel')) {
				$rel = preg_replace('/\.mp4$/', '.webp', videoFormatRel($format, $parts['folders'], $parts['filename']));
				$path = \fs::toNative((string) \fs::$siteRoot . $rel);
				$dir = dirname($path);
				if (!is_dir($dir)) {
					@mkdir($dir, 0775, true);
				}
				file_put_contents($path, $binary);
			} else {
				MediaFiles::writePoster($row, $binary);
			}
			$save();
		}
		break;

	case 'cropState':
	case 'saveMasterCrop':
	case 'saveRatioCrop':
	case 'saveRatioCrops':
	case 'resetCrop':
	case 'savePois':
		// The image editor of master and ratios: answered by system (Media\CropEditorEndpoint),
		// the rights were checked above (PageAccess).
		\Systopic\System\Media\CropEditorEndpoint::send(\Systopic\System\Media\CropEditorEndpoint::answer(
			$this->action, $pages->scope, $row === NULL ? 0 : (int) $row->id, static fn(string $name, mixed $default = NULL): mixed => http::get($name, $default)
		));
		break;
}
