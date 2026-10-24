<?php
/**
 * Upload into the media library — the file comes in spans (upload/panel.js),
 * the last one creates or replaces the medium.
 *
 *   ?node_parent_node_id=<page>&group=<group>   a new medium at the end of the group
 *   ?node_id=<media node>                       a new file for an existing medium
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Upload\Panel $this
 */

use Systopic\System\Tables\Medias\Files as MediaFiles;
use Systopic\System\Tables\Medias\Operator as MediaOperator;

/** @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $pages */
$pages = $this->parent;

switch ($this->action) {
	case 'open':
		break;

	case 'upload':
		$renderStyle = 'none';
		$fileName = str::getSafeName(http::put('name'));
		$tmpdir = (string) fs::$siteRoot . 'var/tmp/upload/';
		if (!fs::isDir($tmpdir)) {
			fs::fsMkdir($tmpdir, 0777, TRUE);
		}
		$tmpFileNative = fs::toNative($tmpdir . $fileName);
		file_put_contents($tmpFileNative, (string) http::binary(), (int) http::put('spanIndex') === 0 ? 0 : FILE_APPEND);
		if (!http::put('lastSpan')) {
			break;
		}

		$replace = (int) http::get('node_id');
		$rows = [];
		if ($replace > 0) {
			$media = $pages->mediaOfNode($replace);
		} else {
			[$media, $rows] = MediaOperator::create($pages->scope, (int) http::get('node_parent_node_id'), (string) (http::get('group') ?: 'main'), $pages->userId());
		}
		if ($media === NULL) {
			message::error('Medium not found');
			break;
		}
		$info = http::put('info');
		$media->label = (string) http::put('label');
		$media->width = (int) ($info->width ?? 0);
		$media->height = (int) ($info->height ?? 0);
		MediaOperator::touch($media, $pages->userId());
		// the row first: the folder is named after the media id
		$uow = $pages->uow();
		foreach ([...$rows, $media] as $row) {
			$uow->persist($row);
		}
		$uow->commit();
		MediaFiles::store($media, $tmpFileNative, $fileName);   // type, mime, image size from the file
		$chosen = strtolower(trim((string) http::put('type')));
		if ($chosen !== '' && (in_array($chosen, ['image', 'video', 'pdf', 'file'], true) || \Systopic\System\Tables\Medias\External::is($chosen))) {
			$media->type = $chosen;
		}
		if (\Systopic\System\Tables\Medias\External::is((string) $media->type) || trim((string) http::put('url')) !== '') {
			\Systopic\System\Tables\Medias\External::storeUrl($media, (string) http::put('url'));
		}
		$pages->uow()->persist($media)->commit();
		// The library tile is this format. Make it now, so replace does not
		// leave the grey "file missing" card until something else asks for it.
		if (str_starts_with((string) $media->mimetype, 'image/') && $media->mimetype !== 'image/svg+xml' && class_exists('mediaImage')) {
			$original = MediaFiles::originalPath($media);
			try {
				$thumb = mediaImage::createImageFormat((int) $media->id, 'mmlThumb', $original, (string) $media->filename, is_file($original), '');
				MediaFiles::writeFormat($thumb, (int) $media->id, 'mmlThumb', (string) $media->filename);
				$thumb->clear();
			} catch (Throwable $e) {
				// the tile asks again on the next view
			}
		}
		if (function_exists('videoFormatsAfterUpload')) {
			videoFormatsAfterUpload($media);
		}
		// Several files stay on the page that is open. '../' from here is the
		// CMS root, which is the blank screen. The lightbox is the one case
		// that lives under upload/ and has to step back up.
		if (!http::put('queue')) {
			$pages->pageChanged();
			http::redirect('../');
		}
		break;
}
