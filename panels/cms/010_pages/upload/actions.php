<?php
/**
 * Upload into the media library — the file comes in spans (upload/panel.js),
 * the last one creates or replaces the medium. `fetch` takes an address
 * instead (an image dragged out of another browser) and loads it here.
 *
 *   ?node_parent_node_id=<page>&group=<group>   a new medium at the end of the group
 *   ?node_id=<media node>                       a new file for an existing medium
 *
 *   put.editor   the file went into a text editor: answer with its tag (done)
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Upload\Panel $this
 */

use Systopic\System\Queries\TagGroups\TagGroups;
use Systopic\System\Queries\TagLinks\TagLinks;
use Systopic\System\Tables\Medias\Files as MediaFiles;
use Systopic\System\Tables\Medias\Operator as MediaOperator;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\TagGroups\Model as TagGroup;
use Systopic\System\Tables\Tags\Model as Tag;
use Systopic\System\Tables\TagsInNodes\Model as TagInNode;

/** @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $pages */
$pages = $this->parent;

if (in_array($this->action, ['addTag', 'addTagTo_page', 'removeTagFrom_page'], true)) {
	$targetNode = (int) \http::posted('node_id', \http::dataset('id', 0));
	if ($this->action !== 'addTag') {
		$targetNode = (int) \http::dataset('id', $targetNode);
	}
	if ($targetNode < 2) {
		$pending = array_values(array_unique(array_map('intval', (array) ($this->state->pendingTags ?? []))));
		if ($this->action === 'addTag') {
			$name = trim((string) \http::posted('name', ''));
			$groupId = (int) \http::posted('group_id', 0);
			$group = null;
			foreach (TagGroups::load($pages->scope) as $candidate) {
				if ((int) $candidate->id === $groupId) {
					$group = $candidate;
					break;
				}
			}
			if ($name !== '' && $group instanceof TagGroup) {
				$tag = null;
				foreach ($group->tags as $existing) {
					if (strcasecmp((string) $existing->name, $name) === 0) {
						$tag = $existing;
						break;
					}
				}
				$uow = $pages->uow();
				if ($tag === null) {
					$tag = new Tag();
					$tag->name = $name;
					$tag->taggroup = $group;
					$uow->persist($tag)->commit();
				}
				$tagId = (int) $tag->id;
				if ($tagId > 0 && !in_array($tagId, $pending, true)) {
					$pending[] = $tagId;
				}
			}
		} else {
			$tagId = (int) \http::dataset('tag_id', \http::put('tag_id', 0));
			if ($tagId > 0 && $this->action === 'addTagTo_page' && !in_array($tagId, $pending, true)) {
				$pending[] = $tagId;
			}
			if ($tagId > 0 && $this->action === 'removeTagFrom_page') {
				$pending = array_values(array_filter($pending, static fn(int $id): bool => $id !== $tagId));
			}
		}
		$this->state->pendingTags = $pending;
		$this->updateView('tags');
		return;
	}
	$sidebar = $pages->child('sidebar');
	if ($sidebar) {
		$sidebar->action = $this->action;
		(function () {
			$cmsMediaTagsOnly = true;
			include dirname(__DIR__) . '/sidebar/actions.tags.php';
		})->call($sidebar);
	}
	$this->updateView('tags');
	return;
}

$tmpdir = (string) fs::$siteRoot . 'var/tmp/upload/';
if (!fs::isDir($tmpdir)) {
	fs::fsMkdir($tmpdir, 0777, TRUE);
}

/** File the finished upload as a medium. False when there is nothing to file it in. */
$panel = $this;
$store = static function (string $tmpFileNative, string $fileName, string $label) use ($pages, $panel): bool {
	$replace = (int) http::get('node_id');
	$rows = [];
	if ($replace > 0) {
		$media = $pages->mediaOfNode($replace);
	} else {
		$parent = (int) http::get('node_parent_node_id');
		if (http::put('editor')) {
			// an editor shown through an alias writes the original page's text: its library
			$parent = (int) ($pages->pageFor($parent)?->row?->relationId('node') ?: $parent);
		}
		[$media, $rows] = MediaOperator::create($pages->scope, $parent, (string) (http::get('group') ?: 'main'), $pages->userId());
	}
	if ($media === NULL) {
		message::error('Medium not found');
		return FALSE;
	}
	$info = http::put('info');
	$media->label = $label;
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
	if ($chosen !== '' && (in_array($chosen, ['image', 'video', 'audio', 'pdf', 'file'], true) || \Systopic\System\Tables\Medias\External::is($chosen))) {
		$media->type = $chosen;
	}
	if (\Systopic\System\Tables\Medias\External::is((string) $media->type) || trim((string) http::put('url')) !== '') {
		\Systopic\System\Tables\Medias\External::storeUrl($media, (string) http::put('url'));
	}
	$pages->uow()->persist($media)->commit();
	if ($replace < 1) {
		$raw = http::put('tags', null);
		if (is_string($raw)) {
			$tagIds = array_map('intval', explode(',', $raw));
		} elseif (is_array($raw)) {
			$tagIds = array_map('intval', $raw);
		} else {
			$tagIds = array_map('intval', (array) ($panel->state->pendingTags ?? []));
		}
		$tagIds = array_values(array_unique(array_filter($tagIds, static fn(int $id): bool => $id > 0)));
		$nodeId = (int) ($media->node->id ?? 0);
		if ($nodeId >= 2 && $tagIds !== []) {
			$links = $pages->uow();
			foreach ($tagIds as $tagId) {
				if (TagLinks::has($pages->scope, $tagId, $nodeId)) {
					continue;
				}
				$link = new TagInNode();
				$link->tag = Tag::withId($tagId);
				$link->node = Node::withId($nodeId);
				$links->persist($link);
			}
			$links->commit();
		}
		$panel->state->pendingTags = [];
	}
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
	if (http::put('editor')) {
		client::done(['tag' => $pages->editorTag((int) $media->node->id)]);
		return TRUE;
	}
	// Several files stay on the page that is open. '../' from here is the
	// CMS root, which is the blank screen. The lightbox is the one case
	// that lives under upload/ and has to step back up.
	if (!http::put('queue')) {
		$pages->pageChanged();
		http::redirect('../');
	}
	return TRUE;
};

switch ($this->action) {
	case 'open':
		break;

	case 'upload':
		$fileName = str::getSafeName(http::put('name'));
		$tmpFileNative = fs::toNative($tmpdir . $fileName);
		file_put_contents($tmpFileNative, (string) http::binary(), (int) http::put('spanIndex') === 0 ? 0 : FILE_APPEND);
		if (!http::put('lastSpan')) {
			$renderStyle = 'none';
			break;
		}
		// A render from here would be the upload panel's: it replaces the
		// page, the open text included. The client reloads instead.
		$renderStyle = 'none';
		$store($tmpFileNative, $fileName, (string) http::put('label'));
		break;

	case 'fetch':
		$renderStyle = 'none';
		$url = trim((string) http::put('url'));
		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
		if (!in_array($scheme, ['http', 'https'], true)) {
			client::done(['error' => 'Only files from a web address can be taken over.']);
			break;
		}
		$tmpFileNative = fs::toNative($tmpdir . 'fetch-' . bin2hex(random_bytes(6)));
		$context = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 1, 'max_redirects' => 3, 'user_agent' => 'fluxo cms']]);
		$in = @fopen($url, 'rb', false, $context);
		$out = $in ? fopen($tmpFileNative, 'wb') : false;
		$bytes = ($in && $out) ? stream_copy_to_stream($in, $out, 512 * 1024 * 1024) : false;
		$in && fclose($in);
		$out && fclose($out);
		$mime = $bytes ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmpFileNative) : '';
		$ext = mediaInfo::$mimetypes[$mime] ?? NULL;
		if ($ext === NULL || !preg_match('~^(image|video|audio)/~', $mime)) {
			is_file($tmpFileNative) && unlink($tmpFileNative);
			client::done(['error' => $bytes ? "This file type cannot be used here ($mime)." : 'The file could not be loaded from its address.']);
			break;
		}
		$base = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME) ?: 'media';
		$fileName = str::getSafeName($base) . '.' . $ext;
		$store($tmpFileNative, $fileName, $base);
		break;
}
