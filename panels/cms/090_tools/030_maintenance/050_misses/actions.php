<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Misses\Panel $this */

use Systopic\Cms\Queries\Misses\Misses;
use Systopic\Cms\Queries\RewritepathLookup\RewritepathLookup;
use Systopic\Cms\Tables\Rewritepaths\Model as Rewritepath;
use Systopic\System\Tables\Nodes\Model as Node;

switch ($this->action) {
	case 'dismiss':
		$miss = Misses::byId($this->scope, (int) \http::dataset('miss_id'));
		if ($miss !== null) {
			$this->scope->remove($miss);
			\message::confirm(htmlspecialchars('Forgot “/' . $miss->path . '”.', ENT_QUOTES));
		}
		$this->updateView();
		break;

	case 'redirect':
		$miss = Misses::byId($this->scope, (int) \http::dataset('miss_id'));
		$nodeId = (int) \http::dataset('node_id');
		if ($miss === null) {
			$this->updateView();
			break;
		}
		if ($nodeId === 0) {
			\message::error('Choose a page first.');
			$this->updateView();
			break;
		}
		$site = $miss->siteNodeId;
		if (!RewritepathLookup::isUnique($this->scope, $miss->path, null, $site)) {
			$this->scope->remove($miss);
			\message::confirm(htmlspecialchars('“/' . $miss->path . '” already belongs to a page.', ENT_QUOTES));
			$this->updateView();
			break;
		}
		$page = Misses::pageOf($this->scope, $nodeId);
		if ($page === null) {
			\message::error('That page is gone.');
			$this->updateView();
			break;
		}
		$path = new Rewritepath();
		$path->page = $page;
		$path->siteNode = $site === null ? null : Node::withId($site);
		$path->string = $miss->path;
		$path->isPrimary = false;
		$this->scope->save($path);
		$this->scope->remove($miss);
		\message::confirm(htmlspecialchars('“/' . $miss->path . '” now opens “' . (trim((string) $page->label) ?: 'Untitled') . '”.', ENT_QUOTES));
		$this->updateView();
		break;
}
