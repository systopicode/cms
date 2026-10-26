<?php
if (function_exists('nodeTagIds')) {
	return;
}

/**
 * Media-Tags nutzen dieselbe Junction wie Pages: cms_tags_in_nodes (tin_node_id).
 */

function nodeTagIds(int $nodeId): array {
	if ($nodeId < 1) {
		return [];
	}
	$concat = db::queryValue(
		'SELECT GROUP_CONCAT(tin_tag_id) FROM cms_tags_in_nodes WHERE tin_node_id = ?',
		$nodeId
	);
	if (!$concat) {
		return [];
	}
	return array_values(array_filter(array_map('strval', explode(',', (string) $concat)), static fn($id) => $id !== ''));
}

function nodeAddTag(int $nodeId, int $tagId): void {
	if ($nodeId < 1 || $tagId < 1) {
		return;
	}
	db::query('INSERT IGNORE INTO cms_tags_in_nodes SET tin_tag_id = ?, tin_node_id = ?', [$tagId, $nodeId]);
}

function nodeRemoveTag(int $nodeId, int $tagId): void {
	if ($nodeId < 1 || $tagId < 1) {
		return;
	}
	db::query('DELETE FROM cms_tags_in_nodes WHERE tin_tag_id = ? AND tin_node_id = ?', [$tagId, $nodeId]);
}

function nodeReplaceTags(int $nodeId, array $tagIds): void {
	if ($nodeId < 1) {
		return;
	}
	db::query('DELETE FROM cms_tags_in_nodes WHERE tin_node_id = ?', [$nodeId]);
	foreach ($tagIds as $tagId) {
		nodeAddTag($nodeId, (int) $tagId);
	}
}

function mediaTagIds($media): array {
	if (!$media) {
		return [];
	}
	$ids = $media->tag_ids ?? [];
	if (!is_array($ids)) {
		$ids = $ids ? [(string) $ids] : [];
	}
	return array_values(array_filter(array_map('strval', $ids), static fn($id) => $id !== ''));
}

function mediaHasTag($media, string $tagId): bool {
	$tagId = (string) $tagId;
	if ($tagId === '') {
		return false;
	}
	foreach (mediaTagIds($media) as $id) {
		if ((string) $id === $tagId) {
			return true;
		}
	}
	return false;
}

function mediaTagsPanelHtml(int $nodeId = 0, bool $pending = false): string {
	return (string) tags::panelForNode($nodeId, $pending);
}

/** CMS-Action addTagTo_node / removeTagFrom_node. */
function mediaTagsHandleAction(string $action): bool {
	if ($action !== 'addTagTo_node' && $action !== 'removeTagFrom_node') {
		return false;
	}
	$nodeId = (int) http::dataset('node_id', 0);
	if (!$nodeId) {
		$nodeId = (int) http::dataset('id', 0);
	}
	$tagId = (int) http::dataset('tag_id', 0);
	if ($nodeId && $tagId) {
		if ($action === 'addTagTo_node') {
			nodeAddTag($nodeId, $tagId);
		} else {
			nodeRemoveTag($nodeId, $tagId);
		}
	}
	client::replace('.mediaTagPanel', mediaTagsPanelHtml($nodeId, false));
	return true;
}

function mediaTagsFromUploadPayload($raw): array {
	if (is_object($raw)) {
		$raw = (array) $raw;
	}
	if (is_string($raw)) {
		$decoded = json_decode($raw, true);
		$raw = is_array($decoded) ? $decoded : explode(',', $raw);
	}
	if (!is_array($raw)) {
		return [];
	}
	$ids = [];
	foreach ($raw as $id) {
		$id = (int) $id;
		if ($id > 0) {
			$ids[] = $id;
		}
	}
	return array_values(array_unique($ids));
}

/** @return list<string> Tag-IDs, neueste zuerst: Skill-Kontext, dann Filter. */
function mediaPreviewTagIds(): array {
	$prefer = [];
	$explicit = $GLOBALS['tilePreviewTagId'] ?? '';
	if (is_array($explicit)) {
		foreach ($explicit as $id) {
			$id = (string) $id;
			if ($id !== '' && $id !== '0') {
				$prefer[] = $id;
			}
		}
	} else {
		$explicit = (string) $explicit;
		if ($explicit !== '' && $explicit !== '0') {
			$prefer[] = $explicit;
		}
	}
	$selected = $GLOBALS['collectionFilterSelected'] ?? [];
	$order = $GLOBALS['collectionFilterOrder'] ?? array_keys($selected);
	if ($selected) {
		foreach (array_reverse(array_values($order)) as $tagId) {
			$tagId = (string) $tagId;
			if ($tagId === '' || !isset($selected[$tagId])) {
				continue;
			}
			$prefer[] = $tagId;
		}
	}
	return $prefer;
}

/**
 * Tile-Vorschau: erstes Main-Medium, sonst Medium mit Tag
 * (Collection-Filter oder $GLOBALS['tilePreviewTagId'], z. B. Skill).
 */
function pageTilePreviewMedia($page) {
	if (!$page) {
		return null;
	}
	if (function_exists('spIsNewPage') && spIsNewPage($page)) {
		if (!$page->medias->has('main')) {
			return null;
		}
		$group = $page->medias->main;
		$prefer = mediaPreviewTagIds();
		foreach ($prefer as $tagId) {
			foreach ($group as $media) {
				if (mediaHasTag($media, $tagId)) {
					return $media;
				}
			}
		}
		return $group->first;
	}
	if ((string) ($page->node_type ?? '') === 'media' && class_exists('media')) {
		try {
			$m = media::get((int) $page->node_id);
			if ($m && (string) ($m->filename ?? '') !== '') {
				return $m;
			}
		} catch (Throwable $e) {
			return null;
		}
	}
	if (!$page->medias || !$page->medias->hasGroup('main') || !$page->medias->main->count()) {
		return null;
	}
	$group = $page->medias->main;
	$prefer = mediaPreviewTagIds();
	foreach ($prefer as $tagId) {
		foreach ($group as $media) {
			if (mediaHasTag($media, $tagId)) {
				return $media;
			}
		}
	}
	return $group->first;
}
