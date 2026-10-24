<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Rewrite\Panel $this */

use Systopic\Cms\Queries\RewritepathIndex\RewritepathIndex;
use Systopic\Cms\Tables\Rewritepaths\Removal;

switch ($this->action) {
	case 'assignSites':
		// rewritepath_site_node_id from the place in the tree: the node of type
		// 'site' above the page, NULL for the default site. Never edited by hand.
		$changed = \Systopic\Cms\Pages\Sites\NodeSite::rebuildAll();
		\message::confirm($changed . ' paths assigned to their site.');
		$this->updateView();
		break;

	case 'buildAllAutoRewritepaths':
		[$written, $failures] = $this->rebuildAllPaths();
		\message::confirm("$written automatic paths written.");
		foreach ($failures as $failure) {
			\message::error($failure);
		}
		$this->updateView();
		break;

	case 'removeAllRewritepaths':
		$removed = Removal::remove($this->scope, ...RewritepathIndex::all($this->scope));
		\message::confirm($removed . ' rewrite paths removed.');
		$this->updateView();
		break;

	case 'deletePath':
		$path = $this->rewritepath;
		if ($path) {
			$shown = trim((string) $path->string) ?: '/';
			Removal::remove($this->scope, $path);
			\message::confirm(htmlspecialchars('Deleted “' . $shown . '”.', ENT_QUOTES));
		}
		$this->updateView('main');
		break;

	case 'deleteTrashedPaths':
		$removed = Removal::remove($this->scope, ...RewritepathIndex::trashed($this->scope));
		\message::confirm($removed . ' addresses of trashed pages deleted.');
		$this->updateView();
		break;
}
