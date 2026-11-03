<?php

/**
 * Tags on the page — a `cms_tags_in_nodes` row per tag and node.
 *
 * `client::done()` answers with the tag ids the page carries afterwards, as
 * the legacy did with a GROUP_CONCAT.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */

use Systopic\Cms\Pages\PageAccess;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Tags\Model as Tag;
use Systopic\System\Tables\TagGroups\Model as TagGroup;
use Systopic\System\Tables\TagsInNodes\Model as TagInNode;
use Systopic\System\Queries\TagGroups\TagGroups;
use Systopic\System\Queries\TagLinks\TagLinks;

$cmsMediaTagsOnly = $cmsMediaTagsOnly ?? false;

// The file editor and the replace dialog show the same tags. Their URL is
// not the page, so the click goes up to this panel; the open lightbox has
// to be drawn again or the chip stays as it was.
$panel = $this;
$refreshMediaTags = function () use ($panel): void {
	$file = $panel->parent->child('editor')?->child('file');
	if ($file && $file->inPath()) {
		$file->updateView('tags');
	}
	$upload = $panel->parent->child('upload');
	if ($upload && $upload->inPath()) {
		$upload->updateView('tags');
	}
};

switch ($this->action) {
	case 'addTag':
		$name = trim((string) http::posted('name', ''));
		$groupId = (int) http::posted('group_id', 0);
		$nodeId = (int) http::posted('node_id', $this->parent->page?->id ?? 0);
		if ($name === '' || $groupId < 1 || $nodeId < 2 || PageAccess::refuses($this->parent->nodeFor($nodeId))) {
			break;
		}
		$group = null;
		foreach (TagGroups::load($this->parent->scope) as $candidate) {
			if ((int) $candidate->id === $groupId) {
				$group = $candidate;
				break;
			}
		}
		if (!$group instanceof TagGroup) {
			break;
		}
		$tag = null;
		foreach ($group->tags as $existing) {
			if (strcasecmp((string) $existing->name, $name) === 0) {
				$tag = $existing;
				break;
			}
		}
		$uow = $this->parent->uow();
		if ($tag === null) {
			$tag = new Tag();
			$tag->name = $name;
			$tag->taggroup = $group;
			$uow->persist($tag)->commit();
		}
		if ($tag->id !== null && !TagLinks::has($this->parent->scope, (int) $tag->id, $nodeId)) {
			$link = new TagInNode();
			$link->tag = $tag;
			$link->node = Node::withId($nodeId);
			$uow->persist($link)->commit();
		}
		if (empty($cmsMediaTagsOnly)) {
			$this->updateView('040_tags');
			$this->parent->updateView('main');
		}
		$refreshMediaTags();
		break;

	case 'addTagTo_page':
	case 'removeTagFrom_page':
		$on = $this->action === 'addTagTo_page';
		// drop on a search result OR click in edit mode
		$nodeId = (int) ($on
			? http::dataset('id', http::get('id'))
			: http::put('item_id', http::dataset('id')));
		$tagId = (int) ($on
			? http::dragdata('tag_id', http::dataset('tag_id'))
			: http::put('tag_id', http::dataset('tag_id')));
		if ($nodeId < 2 || $tagId < 1 || PageAccess::refuses($this->parent->nodeFor($nodeId))) {
			break;
		}
		$uow = $this->parent->uow();

		$link = new TagInNode();
		$link->tag = Tag::withId($tagId);
		$link->node = Node::withId($nodeId);
		// Asked of the table itself: the groups' own index only lists links of
		// published pages, and a page being edited often is not one yet.
		$exists = TagLinks::has($this->parent->scope, $tagId, $nodeId);
		if ($on && !$exists) {
			$uow->persist($link)->commit();
		} elseif (!$on && $exists) {
			// A row is removed by its key; this one was never loaded, so it is
			// marked as saved first — it is the row in the table, by key.
			$link->markLoaded();
			$uow->remove($link)->commit();
		}
		if (empty($cmsMediaTagsOnly)) {
			client::done(implode(',', TagLinks::tagIdsOf($this->parent->scope, $nodeId)));
			$this->updateView('040_tags');
			$this->parent->updateView('main');
		}
		$refreshMediaTags();
		break;
}
