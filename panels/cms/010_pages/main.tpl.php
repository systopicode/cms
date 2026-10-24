<?php
/**
 * cms/pages — the article area: the page preview.
 *
 * The preview renders the project's templates through the render route
 * (`Pages\Route\Route`), which still builds legacy page objects; from here
 * it only gets the node id and the language.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this
 */
$this->loadJS();
echo message::flush(['template']);
$page = $this->page;
// A site instead of a page: a site node from the tree, the default site
// (globe in the tree navi) or a new one (add menu). See site.tpl.php.
if ($this->editsSite()) {
	$site = $this->siteOnScreen();
	include 'site.tpl.php';
	return;
}
if ($page === null || $page->row === null) {
	include "controls.languages.tpl.php";
	return;
}
?>
<div class="cmsPreview"><article class="cmsContent">
	<?php
	// The opened page becomes a regular DomLayer inside the panel layer tree.
	// That is deliberate — it gives the preview its own diff slot, so switching
	// pages in the tree re-renders just this subtree.
	$route   = \Systopic\Cms\Pages\Route\Route::getInstance();
	$pageNav = \Systopic\Cms\Pages\Route\RouteNode::getNavigator();

	if ($route === null || $pageNav === null) {
		message::error('pages module: page renderer not available');
		echo message::flush();
	} else {
		$route->build($page->id, $page->language);
		$pageNav->setActive($route->getRoot());

		if ($pageNav->openSelected('main')) {
			// A page without a template opens INLINE: no view is resolved and
			// the working directory stays in this panel folder (see
			// PageNavigator::openNode). Rendering 'main.tpl.php' from there
			// would include THIS file a second time.
			// The trash root is such a page, and so is every container page.
			if ($pageNav->getActive()->getViewBasePath() === '') {
				message::info('<b>' . htmlspecialchars((string) $page->row->label) . '</b> hat kein Template - keine Vorschau.');
				echo message::flush();
			} else {
				$pageNav->getActive()->render('main.tpl.php');
			}
			$pageNav->close();
		} else {
			message::error('pages module: could not open template <b>' . htmlspecialchars((string) $page->row->templateName) . '</b>');
			logroute('cms/pages preview: openSelected failed', $pageNav->debugInfo());
		}
	}
	?>
</article></div>
