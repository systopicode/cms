<?php
/**
 * The requests of the image editor (sys.cropEditor): answered by system
 * (Media\CropEditorEndpoint). They come to this panel's address
 * (stage.tpl.php: `url`); the file editor runs this before its own
 * (Panel::runActions()).
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Image\Panel $this
 */

use Systopic\Cms\Pages\PageAccess;
use Systopic\System\Media\CropEditorEndpoint;

if (CropEditorEndpoint::handles((string) $this->action)) {
	$row = $this->mediaRow;
	if ($row !== NULL && PageAccess::refuses($row->node)) {
		CropEditorEndpoint::send(['status' => 403, 'body' => ['error' => 'You may not change this picture.']]);
	}
	CropEditorEndpoint::send(CropEditorEndpoint::answer(
		(string) $this->action, $this->pages()->scope, $row === NULL ? 0 : (int) $row->id,
		static fn(string $name, mixed $default = NULL): mixed => http::get($name, $default)
	));
}
