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

use Systopic\System\Media\GroupPolicy;
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
	$existing = $replace > 0 ? $pages->mediaOfNode($replace) : null;
    $previousType = (string) ($existing?->type ?? '');
	$group = $replace > 0 ? (string) ($existing?->group ?? '') : (string) (http::get('group') ?: 'main');
	$error = $replace > 0 && $existing === null ? 'Medium not found.'
		: (GroupPolicy::error($pages->mediaGroups, $group, $fileName) ?? GroupPolicy::contentError($tmpFileNative, $fileName));
	if ($error !== null) {
		client::done(['error' => $error]);
		is_file($tmpFileNative) && unlink($tmpFileNative);
		return false;
	}
	$fileName = GroupPolicy::storageName($fileName);
	if ($replace > 0) {
		$media = $existing;
	} else {
		$parent = (int) http::get('node_parent_node_id');
		if (http::put('editor')) {
			// an editor shown through an alias writes the original page's text: its library
			$parent = (int) ($pages->pageFor($parent)?->row?->relationId('node') ?: $parent);
		}
		[$media, $rows] = MediaOperator::create($pages->scope, $parent, $group, $pages->userId());
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
    $media->type = \Systopic\System\Media\TypePolicy::selected(
        (string) $media->mimetype, (string) $media->filename, $chosen, $previousType, \Systopic\System\Auth\Session::isSuperuser()
    );
    if (\Systopic\System\Tables\Medias\External::is((string) $media->type) && http::put('url', null) !== null) {
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
	// The library is drawn again by this answer when several files stay on
	// the page, and by the step back when the lightbox closes.
	$pages->updateView('mml');
	if (!http::put('queue')) {
		$pages->pageChanged();
		http::redirect('../', '', array_filter([
			'id' => (int) ($pages->state->id ?? 0),
			'lang' => (string) ($pages->state->lang ?? ''),
		], static fn($value): bool => $value !== '' && $value !== 0));
	}
	return TRUE;
};

// Session-scoped receipts make explicit retries safe after a lost final response.
$uploadKey = (string) http::put('uploadKey');
$receipt = preg_match('/^[a-f0-9-]{36}$/D', $uploadKey) ? $uploadKey : null;
$fingerprint = hash('sha256', json_encode([$this->action, http::put('name'), http::put('size'), http::put('url'), http::get('node_id'), http::get('node_parent_node_id'), http::get('group')]));
$receipts = (array) ($this->state->uploadReceipts ?? []);
if ($receipt !== null && isset($receipts[$receipt])) {
    $renderStyle = 'none';
    client::done($receipts[$receipt] === $fingerprint ? ['uploadComplete' => true] : ['error' => 'Upload identity changed. Choose the file again.']);
    return;
}
$rememberUpload = function (bool $stored) use ($receipt, $fingerprint, $receipts): void {
    if (!$stored || $receipt === null) return;
    $receipts[$receipt] = $fingerprint;
    // Receipts live for this editing session; never silently evict a retry identity.
    $this->state->uploadReceipts = $receipts;
};

switch ($this->action) {
	case 'open':
		break;

	case 'upload':
		$fileName = str::getSafeName(http::put('name'));
		$tmpFileNative = fs::toNative($tmpdir . ($receipt !== null ? $receipt . '-' : '') . $fileName);
        $binary = (string) http::binary();
        if ($receipt !== null) {
            // A browser can replay a chunk after losing its response. Write at
            // its fixed offset, never append the same chunk twice.
            $index = (int) http::put('spanIndex');
            $offset = (int) http::put('spanStart');
            if ($index < 0 || $offset !== $index * 3 * 1024 * 1024 || strlen($binary) > 3 * 1024 * 1024) {
                $renderStyle = 'none'; client::done(['error' => 'Invalid upload chunk.']); break;
            }
            $handle = @fopen($tmpFileNative, 'c+b');
            $written = false;
            if ($handle !== false && flock($handle, LOCK_EX)) {
                $available = fstat($handle)['size'];
                if ($offset === 0) ftruncate($handle, 0);
                if ($offset === 0 || $available >= $offset) {
                    fseek($handle, $offset);
                    $written = fwrite($handle, $binary) === strlen($binary);
                    if ($written && http::put('lastSpan')) ftruncate($handle, $offset + strlen($binary));
                }
                flock($handle, LOCK_UN);
            }
            if ($handle !== false) fclose($handle);
            if (!$written) { $renderStyle = 'none'; client::done(['error' => 'The upload chunk could not be stored. Retry the file.']); break; }
        } else {
            file_put_contents($tmpFileNative, $binary, (int) http::put('spanIndex') === 0 ? 0 : FILE_APPEND);
        }
		if (!http::put('lastSpan')) {
			$renderStyle = 'none';
			break;
		}
		// A render from here would leave the upload panel selected and
		// replace the page. The client asks the page for its library again.
		$renderStyle = 'none';
		$rememberUpload($store($tmpFileNative, $fileName, (string) http::put('label')));
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
		$context = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 1, 'max_redirects' => 3, 'user_agent' => 'fluxo']]);
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
		$rememberUpload($store($tmpFileNative, $fileName, $base));
		break;
}
