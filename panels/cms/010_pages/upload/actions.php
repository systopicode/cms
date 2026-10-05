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
		$pages->uow()->persist($media)->commit();
		$pages->pageChanged();
		http::redirect('../');
		break;
}
