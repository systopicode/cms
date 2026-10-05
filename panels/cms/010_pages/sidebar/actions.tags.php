<?php

/**
 * Tags on the page — a `cms_tags_in_nodes` row per tag and node.
 *
 * `client::done()` answers with the tag ids the page carries afterwards, as
 * the legacy did with a GROUP_CONCAT.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */

use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Tags\Model as Tag;
use Systopic\System\Tables\TagsInNodes\Model as TagInNode;
use Systopic\System\Queries\TagLinks\TagLinks;

switch ($this->action) {
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
		if ($nodeId < 2 || $tagId < 1) {
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
		client::done(implode(',', TagLinks::tagIdsOf($this->parent->scope, $nodeId)));
		$this->updateView('040_tags');
		$this->parent->updateView('main');
		break;
}
