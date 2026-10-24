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

$formatsOf = function (bool $all): array {
	$current = (string) http::get('format', (string) ($this->state->format ?? ''));
	if ($current === '' || $current === 'original') {
		foreach (mediaFormats::get() as $name => $config) {
			if (($config['cms_display'] ?? '') !== 'none' && $name !== 'original') {
				$current = (string) $name;
				break;
			}
		}
	}
	if (!$all) {
		return $current !== '' && $current !== 'original' ? [$current] : [];
	}
	$names = [];
	foreach (mediaFormats::get() as $name => $config) {
		if (($config['cms_display'] ?? '') === 'none' || $name === 'original') {
			continue;
		}
		$names[] = (string) $name;
	}
	return $names;
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

	case 'updateMediaType':
		if ($row !== NULL) {
			$type = strtolower(trim((string) http::values('type')));
			if ($type !== '' && (in_array($type, ['image', 'video', 'pdf', 'file'], true) || \Systopic\System\Tables\Medias\External::is($type))) {
				$row->type = $type;
				$save();
			}
		}
		break;

	case 'updateMediaUrl':
		if ($row !== NULL) {
			\Systopic\System\Tables\Medias\External::storeUrl($row, (string) http::values('url'));
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

	case 'adjustImage':
		if ($row === NULL || $row->id === null) {
			break;
		}
		$brightness = max(0, min(200, (int) http::get('brightness', 100)));
		$contrast = max(0, min(200, (int) http::get('contrast', 100)));
		$saturation = max(0, min(200, (int) http::get('saturation', 100)));
		$rotate = ((int) http::get('rotate', 0) % 360 + 360) % 360;
		$flip = (int) http::get('flip', 0) === 1;
		if ($brightness === 100 && $contrast === 100 && $saturation === 100 && $rotate === 0 && !$flip) {
			break;
		}
		$tune = ['b' => $brightness, 'c' => $contrast, 's' => $saturation, 'r' => $rotate, 'f' => $flip ? 1 : 0];
		$names = $formatsOf((int) http::get('apply_all', 0) === 1);
		if ($names === []) {
			header('X-Adjust: empty');
			http_response_code(422);
			break;
		}
		foreach ($names as $name) {
			MediaFiles::saveTune((int) $row->id, (string) $row->filename, $name, $tune);
			MediaFiles::dropFormatFile((int) $row->id, $name, (string) $row->filename);
		}
		header('X-Adjust: saved');
		break;

	case 'restoreOriginal':
		if ($row === NULL || $row->id === null) {
			break;
		}
		// A backup from before the original was written over. The original is no longer edited.
		$path = MediaFiles::originalPath($row);
		$master = $path . '.master';
		if (is_file($master)) {
			copy($master, $path);
			MediaFiles::describe($row);
			MediaFiles::saveTune((int) $row->id, (string) $row->filename, null, null);
			MediaFiles::removeFormats($row);
			$save();
		} else {
			foreach ($formatsOf((int) http::get('apply_all', 0) === 1) as $name) {
				MediaFiles::saveTune((int) $row->id, (string) $row->filename, $name, null);
				MediaFiles::dropFormatFile((int) $row->id, $name, (string) $row->filename);
			}
		}
		$pages->updateView('mml');
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
