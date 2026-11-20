<?php
/**
 * The requests of a video: a poster from a frame (`createPoster`, from
 * stage.tpl.php) - of the original, or of one of its formats.
 * The file editor runs this before its own (Panel::runActions()), at any of its addresses.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Panel $this
 */

use Systopic\Cms\Pages\PageAccess;
use Systopic\System\Tables\Medias\Files as MediaFiles;

$row = $this->mediaRow;
if ($this->action === 'createPoster') {
	if ($row !== NULL && !PageAccess::refuses($row->node)) {
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
		$this->saveMedia();
	}
}
