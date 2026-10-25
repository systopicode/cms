<?php

/**
 * cms/pages — the actions.
 *
 * Included from PanelNode::runActions() with $this bound to the panel. Every
 * read and write goes through the panel's scope (see panel.php): rows come
 * out of `Tables\…\Views`, changes go through `UnitOfWork`, and what is
 * table knowledge — ordering, trash, copying content, typing form input —
 * sits in the `Operator` of the table.
 *
 * getImageEditor (the crop editor) still uses the legacy media classes; it
 * moves with the crops (legacy-db-removal.plan.md, 2d).
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this
 */

use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Nodes\Operator as NodeOperator;
use Systopic\Cms\Tables\Pages\Model as PageRow;
use Systopic\Cms\Tables\Pages\Operator as PageOperator;
use Systopic\Cms\Tables\Pages\Page;
use Systopic\Cms\Queries\PageSearch\PageSearch;
use Systopic\Cms\Pages\Fields\HtmlSource;

/** The node id an action is about: the dataset of the clicked element, else the address. */
$nodeIdOf = static function (): ?int {
    $id = http::dataset('node_id', http::get('id'));
    return $id === null || $id === '' ? null : (int) $id;
};

/** Save a page row with a modified stamp. */
$savePage = function (PageRow $row): void {
    PageOperator::touch($row, $this->userId());
    $this->uow()->persist($row)->commit();
};

switch ($this->action) {

    // ------------------------------------------------------------ the tree

    case 'getTreeContextmenu':
        client::contextmenu($this->printTreeContextmenu(http::dataset('node_id')));
        break;

    case 'fold':
        $folded = (int) http::dataset('id');
        $this->tree->fold($folded);
        $this->state->openFolds = $this->tree->openFolds();
        if ($this->tree->has((int) $this->state->id)) {
            // `updateView()` instead of `update()`: folding changes the folds,
            // not the data — the tree knows it has to rebuild, the view reuses
            // that.
            $this->updateView('tree');
            break;
        }
        // The shown page was folded out of sight: the node that was folded
        // becomes the page. The tree in hand was built for the old active id,
        // so it is built again — its queries are in the result cache, which
        // makes that free — and every view that shows the page follows.
        $this->state->id = $folded;
        $this->pageChanged();
        // The address still names the old page; a reload would select it
        // again and unfold the way to it. The browser is told the new one.
        client::pushState('?id=' . $folded);
        break;

    case 'closeAllFolds':
        $this->tree->closeAll();
        $this->state->openFolds = [];
        $this->updateView('tree');
        break;

    case 'hideTrash':
        $this->state->treeMode = 'normal';
        $this->updateView('tree');
        $this->updateView('pylon');
        break;

    case 'new':
    case 'newChild':
        // Next to the page the action came from, or as its last child; with
        // no page at all at the end of the topmost level.
        $ref = $this->pageFor($nodeIdOf() ?? (int) $this->state->id);
        [$parent, $index] = match (true) {
            $ref === null                  => [null, PHP_INT_MAX],
            $this->action === 'newChild'   => [$ref->node, PHP_INT_MAX],
            default                        => [$ref->node->parentNode, NodeOperator::indexOf($this->scope, $ref->node) + 1],
        };
        $template = (string) ($ref?->row?->templateName ?? '');
        $label = $template !== '' ? '##New ' . ucfirst($template) . ' Page' : '##New Page';

        $page = Page::create($this->scope, $this->params, $parent, $index, $label, $template, $this->userId());
        http::redirect('./?id=' . $page->id);
        break;

    // ------------------------------------------------------------- sites
    // A site is a node of type 'site' plus a row in cms_sites; it is edited in
    // main (site.tpl.php). The name field of a new site sends createSite.

    case 'createSite':
        $name = strtolower(trim((string) (http::values('siteName') ?: http::put('value'))));
        if ($name === '') {
            break;
        }
        try {
            $site = \Systopic\Cms\Pages\Sites\SiteRegistry::create($this->scope, $name, null, $configLog);
            $nodeId = $site->relationId('node');
            message::confirm("Site $name angelegt, Ordner sites/$name/ erstellt.");
            foreach ($configLog ?? [] as $line) {
                message::confirm("config/$line");
            }
            // a site with a node is selected like any node; the first site is the default site
            http::redirect($nodeId === null ? './?site=default' : './?id=' . $nodeId);
        } catch (\RuntimeException $e) {
            message::error($e->getMessage());
            $this->updateView('main');
        }
        break;

    case 'updateSiteMeta':
        // no redraw on success: the field keeps its content and its cursor
        if ($this->actionSite) {
            try {
                \Systopic\Cms\Pages\Sites\SiteRegistry::saveMeta($this->scope, $this->actionSite, (string) http::values('meta'));
            } catch (\RuntimeException $e) {
                message::error($e->getMessage());
                $this->updateView('main');
            }
        }
        break;

    case 'updateSiteAliases':
        // redrawn: the list comes back normalised (lower case, no www., no doubles)
        if ($this->actionSite) {
            try {
                \Systopic\Cms\Pages\Sites\SiteRegistry::saveAliases($this->scope, $this->actionSite, (string) http::values('aliases'));
            } catch (\RuntimeException $e) {
                message::error($e->getMessage());
            }
            $this->updateView('main');
        }
        break;

    case 'renameSite':
        // the row, the folder sites/<name>/ and this machine's directory links
        if ($this->actionSite) {
            try {
                $isDefault = $this->actionSite->isDefault;
                $nodeId = $this->actionSite->relationId('node');
                $log = \Systopic\Cms\Pages\Sites\SiteRegistry::rename($this->scope, $this->actionSite, (string) http::values('siteName'));
                foreach ($log as $line) {
                    message::confirm($line);
                }
                // a fresh request: the config of this one still knows the old name
                http::redirect($isDefault ? './?site=default' : './?id=' . $nodeId);
            } catch (\RuntimeException $e) {
                message::error($e->getMessage());
                $this->updateView('main');
            }
        }
        break;

    case 'createSiteFolder':
        // a site restored from a backup has its row but not sites/<name>/
        if ($this->actionSite) {
            try {
                $name = $this->actionSite->name;
                $created = \Systopic\Cms\Pages\Sites\SiteRegistry::createFolders($name);
                message::confirm($created
                    ? "Ordner sites/$name/ erstellt (" . count($created) . ' Ordner).'
                    : "Ordner sites/$name/ gibt es schon.");
            } catch (\RuntimeException $e) {
                message::error($e->getMessage());
            }
            $this->updateView('main');
        }
        break;

    case 'addSiteHostRules':
        // the same rules createSite writes - missing on a site from a backup
        if ($this->actionSite) {
            $isDefault = $this->actionSite->isDefault;
            $nodeId = $this->actionSite->relationId('node');
            $log = \Systopic\Cms\Pages\Sites\SiteRegistry::addHostRules($this->scope, $this->actionSite->name);
            foreach ($log as $line) {
                message::confirm("config/$line");
            }
            if ($log === []) {
                message::info('Keine Instanz-Datei geändert — die Regeln gibt es schon, oder keine Datei hat welche.');
            }
            // a fresh request: the config of this one does not know the rule yet
            http::redirect($isDefault ? './?site=default' : './?id=' . $nodeId);
        }
        break;

    case 'removeSite':
        if ($this->actionSite) {
            try {
                $name = $this->actionSite->name;
                $configLog = \Systopic\Cms\Pages\Sites\SiteRegistry::remove($this->scope, $this->actionSite);
                message::confirm("Site $name entfernt. Der Ordner sites/$name/ bleibt bestehen.");
                foreach ($configLog as $line) {
                    message::confirm("config/$line");
                }
                http::redirect('./?site=default');
            } catch (\RuntimeException $e) {
                message::error($e->getMessage());
                $this->updateView('main');
            }
        }
        break;

    case 'insertBefore':
    case 'insertAfter':
        $drag = $this->tree->getNode((int) http::dragdata('node_id'));
        $drop = $this->tree->getNode((int) http::dataset('node_id'));
        // getNode() returns NULL when the node is not in the drawn tree.
        if ($drag === null || $drop === null) {
            message::error('Node not in tree - cancel Action!');
            break;
        }
        if ($drag->isAncestorOf($drop)) {
            message::error('Impossible Acestry - cancel Action!');
            break;
        }
        $this->scope->save(...NodeOperator::placedBeside($this->scope, $drag->node, $drop->node, after: $this->action === 'insertAfter'));
        // multi-site: the paths of the moved branch follow it into its (maybe other) site
        \Systopic\Cms\Pages\Sites\NodeSite::rebuildBranch((int) $drag->node->id);
        $this->updateView('tree');   // the commit dropped the old tree; this draws a fresh one
        break;

    case 'insertAsFirstChild':
        $drag = $this->tree->getNode((int) http::dragdata('node_id'));
        $drop = $this->tree->getNode((int) http::dataset('node_id'));
        if ($drag === null || $drop === null) {
            message::error('Node not in tree - cancel Action!');
            break;
        }
        if ($drag->isAncestorOf($drop) || $drag === $drop) {
            message::error('Impossible Acestry - cancel Action!');
            break;
        }
        $this->scope->save(...NodeOperator::placed($this->scope, $drag->node, $drop->node, 0));
        // multi-site: the paths of the moved branch follow it into its (maybe other) site
        \Systopic\Cms\Pages\Sites\NodeSite::rebuildBranch((int) $drag->node->id);
        $this->updateView('tree');   // the commit dropped the old tree; this draws a fresh one
        break;

    case 'insertAliasAsFirstChild':
        $drag = $this->tree->getNode((int) http::dragdata('node_id'));
        $drop = $this->tree->getNode((int) http::dataset('node_id'));
        if ($drag === null || $drop === null) {
            message::error('Node not in tree - cancel Action!');
            break;
        }
        if ($drag->isSite) {
            message::error('A site cannot be aliased.');
            break;
        }
        $this->scope->save(...NodeOperator::aliased($this->scope, $drag->node, $drop->node, 0));
        $this->updateView('tree');   // the commit dropped the old tree; this draws a fresh one
        break;

    case 'createAlias':
        // From the context menu: an alias of node_original_node_id, right after
        // node_id — or as first child when node_id is the parent itself.
        $original = (int) http::request('node_original_node_id');
        $after = (int) http::request('node_id');
        $parentId = (int) http::request('node_parent_node_id');
        if ($original < 2) {
            message::error('createAlias: no original');
            break;
        }
        $source = $this->pageFor($original);
        if ($source === null) {
            message::error("createAlias: no page at node $original");
            break;
        }
        $parent = $parentId > 1 ? Node::withId($parentId) : null;
        $index = $after !== $parentId ? NodeOperator::indexAfter($this->scope, $parent, $after, 'page') : 0;
        $this->scope->save(...NodeOperator::aliased($this->scope, $source->node, $parent, $index));
        $this->updateView('tree');   // the commit dropped the old tree; this draws a fresh one
        break;

    case 'deleteAlias':
        $page = $this->pageFor((int) http::request('node_id'));
        if ($page === null || !$page->isAlias) {
            message::error('only alias objects supported use hide function');
            break;
        }
        $originalId = $page->contentNodeId;
        $this->uow()->remove($page->node)->commit();
        if ((int) $this->state->id === $page->id) {
            $this->state->id = $originalId;
            $this->updateView('main');
        }
        $this->updateView('tree');   // the commit dropped the old tree; this draws a fresh one
        break;

    case 'editLabel':
        $entry = $this->tree->getNode((int) http::dataset('id'));
        if ($entry !== null) {
            $entry->edit = true;
        }
        $this->updateView('tree');
        break;

    case 'updateLabel':
        $page = $this->pageFor($nodeIdOf());
        if ($page?->row === null) {
            break;
        }
        $label = strip_tags((string) http::put('html'));
        $label = html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label = html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        PageOperator::assign($page->row, 'label', $label);
        $savePage($page->row);
        $entry = $this->tree->getNode($page->id);
        if ($entry !== null) {
            $entry->edit = false;
        }
        // The tree draws from the same row: no reload.
        $this->updateView('tree');
        $this->child('sidebar')?->refreshSeoPreview();
        break;

    case 'cmsPageSearch':
        $this->state->cmsPageSearchstring = (string) http::values('search');
        $results = PageSearch::byLabel($this->scope, $this->state->cmsPageSearchstring, $this->params->lang);
        $html = '';
        foreach ($results as $result) {
            $html .= "<li><a class='ajax' href=./?id=" . (int) $result->relationId('node') . '>'
                . htmlspecialchars((string) $result->label) . '</a></li>';
        }
        if ($html === '') {
            $html = '<li class="none">No results</li>';
        }
        $this->state->cmsPageSearchResults = $html;
        client::replaceInner('ul#cmsSearchResults', $html);
        client::callFunction('setFocus', ['#cmsSearchInput']);
        break;

    // ------------------------------------------------------- page content

    case 'updatePageParam':  // contenteditable fields: text as html, html fields
    case 'updateTextfield':  // plain inputs and textareas
    case 'updateHTML':       // the HTML lightbox
        if ($this->action === 'updateHTML') {
            $nodeId = (int) http::post('node_id');
            $field = (string) http::post('field');
            $value = HtmlSource::compact((string) http::post('html'));
            $lang = null;
        } else {
            $nodeId = (int) $nodeIdOf();
            $field = (string) http::dataset('field');
            $value = $this->action === 'updateTextfield' ? http::values($field) : http::put('html');
            $lang = http::dataset('language_iso');
        }
        if (!$this->updateField($nodeId, $lang, $field, $value)) {
            break;
        }
        if ($this->action === 'updateHTML') {
            $this->updateView('main');
        }
        // The inline editors are not redrawn: the field keeps its cursor.
        break;

    case 'newLang':
        // The page of a node in another language — empty, or copied from one
        // that exists.
        $nodeId = (int) http::dataset('node_id');
        $lang = (string) http::request('lang', $this->state->lang);
        $page = $this->pageFor($nodeId);
        if ($page === null) {
            break;
        }
        $row = PageOperator::create(Node::withId($page->contentNodeId), $lang, $this->userId(), '## New Page');
        $sourceLang = http::dataset('source_lang', false);
        $source = $sourceLang ? ($page->translations[$sourceLang] ?? null) : null;
        if ($source !== null) {
            PageOperator::copy($source, $row, ['label' => '## ' . $source->label]);
        }
        $this->uow()->persist($row)->commit();
        http::redirect('./?id=' . $nodeId . '&lang=' . $lang);
        break;

    case 'duplicate':
        // A new node right after the source, every language copied.
        $source = $this->pageFor((int) http::dataset('id', http::get('node_id')));
        if ($source === null) {
            break;
        }
        $copy = $source->duplicate($this->userId());
        http::redirect('./?id=' . $copy->id);
        break;

    // --------------------------------------------------------- publishing

    case 'publish':
    case 'unpublish':
        // The context menu sends ?id=, the sidebar nothing (the shown page).
        $page = $this->pageFor((int) http::get('id', $this->state->id));
        if ($page?->row === null) {
            break;
        }
        $page->row->isPublished = $this->action === 'publish';
        $savePage($page->row);
        $this->updateView('tree');
        $this->child('sidebar')?->updateView('main');
        break;

    case 'publishAll':
    case 'unPublishAll':
        $nodeId = (int) http::get('id', http::dataset('node_id', $this->state->id));
        $count = $this->publishBranch($nodeId, $this->action === 'publishAll');
        message::confirm("$count page(s) " . ($this->action === 'publishAll' ? 'published' : 'unpublished'));
        $this->updateView('tree');   // the commit dropped the old tree; this draws a fresh one
        $this->child('sidebar')?->updateView('main');
        break;

    case 'redirectHere':
        $page = $this->pageFor((int) http::request('node_id'));
        $target = (int) http::request('node_redirect_node_id');
        if ($page === null) {
            break;
        }
        $page->node->redirectNode = $target > 1 ? Node::withId($target) : null;
        $this->uow()->persist($page->node)->commit();
        $this->updateView('tree');   // the commit dropped the old tree; this draws a fresh one
        break;

    // -------------------------------------------------------------- trash

    case 'deletePage':
        $nodeId = (int) http::dataset('node_id', 0);
        if ($nodeId < 2) {
            break;
        }
        $page = $this->pageFor($nodeId);
        $parentId = $page?->node->relationId('parentNode');
        $this->trashPage($nodeId);
        $this->updateView('tree');   // the commit dropped the old tree; this draws a fresh one
        if ($nodeId === (int) $this->state->id) {
            http::redirect('?id=' . (int) $parentId);
        }
        break;

    case 'recoverPage':
    case 'recoverePage':  // the spelling the sidebar used to send
        $nodeId = (int) http::dataset('node_id', 0);
        if ($nodeId > 1) {
            $count = $this->recoverBranch($nodeId);
            message::confirm("$count node(s) recovered");
            $this->updateView('tree');   // the commit dropped the old tree; this draws a fresh one
            $this->updateView('main');
        }
        break;

    // ------------------------------------------------------ panel layout

    case 'updatePylonWidth':
        $width = (int) http::put('width');
        if ($width >= 200) {
            $this->state->pylonWidth = min($width, 900);
        }
        break;

    case 'createDefaultTemplate':
        $templateDir = fs::$siteRoot . template::$dir;
        if (!fs::isDir($templateDir)) {
            fs::fsMkdir($templateDir, 0777, true);
        }
        fs::file_put_contents("$templateDir/default.php", template::getDefaultTemplate());
        http::redirect('./');
        break;

    // ------------------------------------------------------------------ media

    case 'mediaAdded':  // an upload finished (upload/panel.js): only the library shows it
        $this->updateView('mml');
        break;

    case 'insertMediaInEditor':
        $tag = $this->editorTag((int) http::dragdata('node_id'));
		if ($tag === null) {
			break;
		}
		$field = http::dataset('field');
        client::insertHtmlAtCursor($tag, "div.editor[data-field='$field']");
        client::trigger("div.editor[data-field='$field']", 'input');
        break;

    case 'updateMediaCrop':
        // The crop set by hand in the image editor (imageEditor.js). It used to
        // be written by media.php itself, on a GET with ?action=updateMediaCrop
        // and no login check (`TRUE ||`); now the CMS saves it, and the editor
        // reloads the format with ?rebuild.
        [$media, $crop] = \Systopic\System\Queries\MediaCrop\MediaCrop::of(
            $this->scope, (int) http::params('media_id'), (string) http::params('image_format'),
        );
        if ($media === null || (string) http::params('image_format') === '') {
            break;
        }
        $this->scope->save(\Systopic\System\Tables\MediaCrops\Operator::cropped(
            $crop, $media, (string) http::params('image_format'),
            (float) http::params('left'), (float) http::params('top'),
            (float) http::params('width'), (float) http::params('height'),
        ));
        break;

    case 'getImageEditor':
        p()->erase();
        $imageEditor = mediaEditor::getImageEditor(http::params('media_id'), http::params('image_format'), http::params('width'));
        echo json_encode(['html' => $imageEditor['html'], 'data' => $imageEditor]);
        exit;
}
