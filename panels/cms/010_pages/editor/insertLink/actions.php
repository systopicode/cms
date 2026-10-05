<?php

/**
 * The link dialog writes the edited field through the page panel.
 *
 * @var \Systopic\System\Panels\PanelNode $this  cms/pages/editor/insertLink
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
}
