<?php
/**
 * The file editor — label, file name, type, tags, delete: what every kind
 * of medium has. The kinds answer their own requests first and then hand
 * on to this file (image/, video/, svg/ actions.php); so do the tabs of the
 * image editor, whose address the sidebar's requests go to while one is open.
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

}
