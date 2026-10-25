<?php

/**
 * The link dialog writes the edited field through the page panel.
 *
 * @var \Systopic\System\Panels\PanelNode $this  cms/pages/editor/editLink
 */

/** @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $pages */
$pages = $this->parent->parent;

switch ($this->action) {
	case 'updatePageParam':
		$pages->updateField(
			(int) http::dataset('node_id'),
			http::dataset('language_iso'),
			(string) http::dataset('field'),
			http::put('html'),
		);
		http::redirect('../../');
		break;

	case 'searchLinkPages':
		$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
		$term = trim((string) http::values('search'));
		$html = '';
		foreach (\Systopic\Cms\Queries\PageSearch\PageSearch::byLabel($pages->scope, $term, $pages->params->lang) as $row) {
			$page = $pages->pageFor((int) $row->relationId('node'));
			if ($page === NULL) {
				continue;
			}
			$path = $page->rewritepaths->path . $page->rewritepaths->appendix;
			$link = 'page:' . (int) $row->relationId('node') . '?lang=' . $pages->params->lang;
			$html .= '<li><button type="button" data-href="' . $e($link) . '" data-label="' . $e($row->label) . '" onclick="linkPicked(this)">'
				. '<b>' . $e($row->label) . '</b><small>/' . $e($path) . '</small></button></li>';
		}
		if ($html === '') {
			$html = '<li class="linkHint">' . ($term === '' ? 'Type a few letters of the page title.' : 'No page with “' . $e($term) . '” in its title.') . '</li>';
		}
		client::replaceInner('.linkDialog ul.linkPages', $html);
		break;
}
