<?php

/**
 * admin/pages — the actions.
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
 * What changes a node asks `PageAccess` first: the node itself, and for what
 * is placed somewhere (new, moved, duplicated) the parent it lands under. A
 * refusal says so; in the tree it draws the tree again, so a drag snaps back.
 *
 * A drag that gives a page another parent needs the right `move` as well
 * (the controlmap, `Access::right()`; panel.config.json: cmsAdmin and
 * cmsEditor). A cmsPageEditor only orders pages within their parent.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Panel $this
 */

use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Nodes\Operator as NodeOperator;
use Systopic\Cms\Tables\Pages\Model as PageRow;
use Systopic\Cms\Tables\Pages\Operator as PageOperator;
use Systopic\Cms\Tables\Pages\Page;
use Systopic\Cms\Queries\PageSearch\PageSearch;
use Systopic\Cms\Pages\Fields\HtmlSource;
use Systopic\Cms\Pages\PageAccess;
use Systopic\System\Controlmap\Access;

// a drag to another parent: the right `move` (the controlmap)
$mayMove = function (Node $node, ?Node $newParent): bool {
    if ((int) $node->parentNode?->id === (int) $newParent?->id || Access::right($this, 'move')) {
        return true;
    }
    message::error('You may order pages, but not move them to another parent.');
    $this->updateView('tree');
    return false;
};

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

// A site is its node; a new one goes onto the topmost level.
if (in_array($this->action, ['createSite', 'updateSiteMeta', 'updateSiteAliases', 'renameSite', 'createSiteFolder', 'createSiteLink', 'removeSite'], true)
    && PageAccess::refuses($this->action === 'createSite' ? null : $this->actionSite?->node)) {
    $this->updateView('main');
    return;
}

switch ($this->action) {
    case 'editorPresence':
        $renderStyle = 'none';
        $id = (int) http::put('id');
        $lang = (string) http::put('lang');
        $token = (string) http::put('client');
        $page = $this->pageIn($id, $lang);
        if ($page?->row === null || !preg_match('/^[a-zA-Z0-9-]{16,80}$/D', $token)) {
            client::done(['available' => false]);
            break;
        }
        $previousToken = (string) http::put('previousClient');
        if ($previousToken !== $token && preg_match('/^[a-zA-Z0-9-]{16,80}$/D', $previousToken)) {
            \Systopic\Cms\Pages\EditorPresence::touch(
                \fs::toNative(\fs::$projectRoot . 'var/tmp/page-editors.json'), '', hash('sha256', session_id() . ':' . $previousToken), time()
            );
        }
        $peerIds = []; $peerSessions = [];
        $peers = \Systopic\Cms\Pages\EditorPresence::touch(
            \fs::toNative(\fs::$projectRoot . 'var/tmp/page-editors.json'),
            http::put('leave') ? '' : $page->row->id . ':' . $lang, hash('sha256', session_id() . ':' . $token), time(), $this->userId(), $peerIds, \Systopic\System\Auth\DeviceName::from(\Systopic\System\Auth\DeviceName::requestAgent()), $peerSessions
        );
        $peerNames = []; $namesById = [];
        $peerIds = array_values(array_unique(array_column($peerSessions, 'user')));
        if ($peerIds) {
            $rows = $this->scope->connection->all('SELECT user_id, user_name, user_fullname FROM usm_users WHERE user_id IN (' . implode(',', array_fill(0, count($peerIds), '?')) . ')', $peerIds);
            foreach ($rows as $peer) $namesById[(int) $peer['user_id']] = trim((string) ($peer['user_fullname'] ?: $peer['user_name']));
            foreach ($peerSessions as $peer) {
                $name = (int) $peer['user'] === $this->userId() ? 'Another session' : ($namesById[$peer['user']] ?? 'Another session');
                $peerNames[] = $name . ($peer['device'] !== '' ? ' · ' . $peer['device'] : '');
            }
            $peerNames = array_values(array_unique($peerNames));
        }
        $record = $this->scope->connection->all('SELECT * FROM cms_pages WHERE page_id = ?', [$page->row->id]);
        client::done(['available' => $peers !== null, 'others' => $peers ?? 0, 'names' => $peerNames,
            'revision' => hash('sha256', json_encode([$record, $this->scope->connection->all('SELECT junction_setting_node_id, junction_varchar, junction_int, junction_decimal FROM cms_junctions WHERE junction_node_id = ? ORDER BY junction_setting_node_id', [$id])], JSON_THROW_ON_ERROR))]);
        break;

    case 'resolveEditorLink':
        $renderStyle = 'none';
        $reference = preg_replace('~^(media|page)::(?=\d)~', '$1:', trim((string) http::put('ref')));
        $ref = \Systopic\Cms\Refs\Ref::fromAttribute($reference);
        $media = $ref?->type === 'media' ? $this->mediaOfNode($ref->id) : null;
        if ($media === null) {
            message::error('The linked file is no longer available.');
            client::done(['error' => true]);
            break;
        }
        $file = new \Systopic\System\Tables\Medias\Media($media);
        client::done([
            'url' => (string) $file->setFormat($ref->param('format', 'original'))->src,
            'filename' => (string) $media->filename,
            'inline' => \Systopic\System\Media\Presentation::opensInline((string) $media->mimetype),
        ]);
        break;

    // ------------------------------------------------------------ the tree

    case 'cancelEditLabel':
        $this->updateView('tree');
        break;

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
        if (PageAccess::refuses($parent)) {
            break;
        }
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
            message::confirm("Site $name created; folder sites/$name/ created.");
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

    case 'createSiteLink':
        // a site that came over by sync or a backup: the link of this machine, and its folder when that is missing too
        if ($this->actionSite) {
            try {
                $name = $this->actionSite->name;
                if (\Systopic\Cms\Pages\Sites\SiteRegistry::createFolders($name)) {
                    message::confirm("Folder sites/$name/ created.");
                }
                foreach (\Systopic\Cms\Pages\Sites\SiteRegistry::createLinks($name) as $path => $outcome) {
                    $outcome === 'created' ? message::confirm("Link $path created.") : message::error($outcome);
                }
            } catch (\RuntimeException $e) {
                message::error($e->getMessage());
            }
            $this->updateView('main');
        }
        break;

    case 'removeSite':
        if ($this->actionSite) {
            try {
                $name = $this->actionSite->name;
                $configLog = \Systopic\Cms\Pages\Sites\SiteRegistry::remove($this->scope, $this->actionSite);
                message::confirm("Site $name removed. The sites/$name/ directory has been retained.");
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
        if (PageAccess::refuses($drag->node, $drop->node->parentNode)) {
            $this->updateView('tree');
            break;
        }
        if (!$mayMove($drag->node, $drop->node->parentNode)) {
            break;
        }
        $this->scope->save(...NodeOperator::placedBeside($this->scope, $drag->node, $drop->node, after: $this->action === 'insertAfter'));
        // multi-site: the paths of the moved branch follow it into its (maybe other) site
        \Systopic\Cms\Pages\Sites\NodeSite::rebuildBranch((int) $drag->node->id);
        $this->updateView('controls');
        $this->child('sidebar')?->updateView('main');
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
        if (PageAccess::refuses($drag->node, $drop->node)) {
            $this->updateView('tree');
            break;
        }
        if (!$mayMove($drag->node, $drop->node)) {
            break;
        }
        $this->scope->save(...NodeOperator::placed($this->scope, $drag->node, $drop->node, 0));
        // multi-site: the paths of the moved branch follow it into its (maybe other) site
        \Systopic\Cms\Pages\Sites\NodeSite::rebuildBranch((int) $drag->node->id);
        $this->updateView('controls');
        $this->child('sidebar')?->updateView('main');
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
        if (PageAccess::refuses($drop->node)) {
            $this->updateView('tree');
            break;
        }
        try {
            \Systopic\Cms\Pages\RelationRules::placement($drag->contentId, $drop->id, $this->relationNode(...));
            $this->scope->save(...NodeOperator::aliased($this->scope, $drag->node, $drop->node, 0));
        } catch (\DomainException $error) { message::error($error->getMessage()); }

        $this->updateView('controls');
        $this->child('sidebar')?->updateView('main');
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
        if (PageAccess::refuses($parentId > 1 ? $this->pageFor($parentId)?->node : null)) {
            break;
        }
        try {
            \Systopic\Cms\Pages\RelationRules::placement($source->contentNodeId, $parentId, $this->relationNode(...));
        } catch (\DomainException $error) { message::error($error->getMessage()); break; }
        $parent = $parentId > 1 ? Node::withId($parentId) : null;
        $index = $after !== $parentId ? NodeOperator::indexAfter($this->scope, $parent, $after, 'page') : 0;
        $this->scope->save(...NodeOperator::aliased($this->scope, $source->node, $parent, $index));
        $this->updateView('controls');
        $this->child('sidebar')?->updateView('main');
        $this->updateView('tree');   // the commit dropped the old tree; this draws a fresh one
        break;

    case 'deleteAlias':
        $page = $this->pageFor((int) http::request('node_id'));
        if ($page === null || !$page->isAlias) {
            message::error('only alias objects supported use hide function');
            break;
        }
        if (PageAccess::refuses($page->node)) {
            break;
        }
        if ($page->hasChildren) { message::error('Move or remove child pages before removing this alias.'); break; }
        $originalId = $page->contentNodeId;
        $this->uow()->remove($page->node)->commit();
        if ((int) $this->state->id === $page->id) {
            $this->state->id = $originalId;
            $this->pageChanged();
        }
        $this->updateView('controls');
        $this->child('sidebar')?->updateView('main');
        $this->updateView('tree');   // the commit dropped the old tree; this draws a fresh one
        break;

    case 'editLabel':
        $entry = $this->tree->getNode((int) http::dataset('id'));
        if ($entry !== null && !PageAccess::refuses($entry->node)) {
            $entry->edit = true;
        }
        $this->updateView('tree');
        break;

    case 'updateLabel':
        $page = $this->pageFor($nodeIdOf());
        if ($page?->row === null || PageAccess::refusesEdit($page)) {
            break;
        }
        $label = strip_tags((string) http::put('html'));
        $label = html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label = html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        PageOperator::assign($page->row, 'label', $label);
        $savePage($page->row);
        client::callFunction('pageEditorOwnSave', [$page->id, (string) $page->language]);
        $entry = $this->tree->getNode($page->id);
        if ($entry !== null) {
            $entry->edit = false;
        }
        // The tree draws from the same row: no reload.
        $this->updateView('tree');
        $this->child('sidebar')?->refreshSeoPreview();
        break;

    case 'cmsPageSearch':
        $term = trim((string) http::values('search'));
        $this->state->cmsPageSearchstring = $term;
        if ($term !== '') $this->state->cmsPageLastSearch = $term;
        $html = '';
        if ($term !== '') {
            $active = (int) ($this->state->id ?? 0);
            foreach (PageSearch::byLabel($this->scope, $term, $this->params->lang) as $result) {
                $nodeId = (int) $result->relationId('node');
                $text = html_entity_decode((string) $result->label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = $text === '' ? '- - - -' : htmlentities($text);
                $html .= "<li data-node_id='$nodeId'" . ($nodeId === $active ? " class='active'" : '') . '>'
                    . "<label><a class='fold leaf'></a>"
                    . "<a class='label ajax' href='./?id=$nodeId'><i class='type cmsIcon cmsIcon-file'></i>$text</a>"
                    . '</label></li>';
            }
            if ($html === '') {
                $html = "<li class='none'><label><a class='fold leaf'></a><span class='label'>No results</span></label></li>";
            }
        }
        $this->state->cmsPageSearchResults = $html;
        client::callFunction('updatePageSearchResults', [$term, $html]);
        \Systopic\System\Auth\Session::storeState();
        $renderStyle = 'none'; // results only: never rebuild the focused search field
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
        if ($page === null || PageAccess::refusesEdit($page)) {
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
        if ($source === null || PageAccess::refuses($source->node->parentNode)) {
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
        if ($page?->row === null || PageAccess::refusesEdit($page)) {
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
        [$count, $skipped] = $this->publishBranch($nodeId, $this->action === 'publishAll');
        message::confirm("$count page(s) " . ($this->action === 'publishAll' ? 'published' : 'unpublished'));
        if ($skipped > 0) {
            message::error("$skipped page(s) left as they are: you may not change them (their content groups).");
        }
        $this->updateView('controls');
        $this->child('sidebar')?->updateView('main');
        $this->updateView('tree');   // the commit dropped the old tree; this draws a fresh one
        break;

    case 'redirectHere':
        try {
            $this->setPageRelation((int) http::request('node_id'), (int) http::request('node_redirect_node_id'), 'redirect');
            $this->updateView('tree');
            $this->child('sidebar')?->updateView('main');
        } catch (\DomainException $error) { message::error($error->getMessage()); }
        break;

    // -------------------------------------------------------------- trash

    case 'deletePage':
        $nodeId = (int) http::dataset('node_id', 0);
        if ($nodeId < 2) {
            break;
        }
        $page = $this->pageFor($nodeId);
        $parentId = $page?->node->relationId('parentNode');
        $trashed = $this->trashPage($nodeId);
        $this->updateView('controls');
        $this->child('sidebar')?->updateView('main');
        $this->updateView('tree');   // the commit dropped the old tree; this draws a fresh one
        if ($trashed !== null && $nodeId === (int) $this->state->id) {
            http::redirect('?id=' . (int) $parentId);
        }
        break;

    case 'recoverPage':
    case 'recoverePage':  // the spelling the sidebar used to send
        $nodeId = (int) http::dataset('node_id', 0);
        if ($nodeId > 1) {
            $count = $this->recoverBranch($nodeId);
            if ($count !== null) {
                message::confirm("$count node(s) recovered");
            }
            $this->updateView('controls');
            $this->child('sidebar')?->updateView('main');
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

    case 'insertMediaInEditor': // existing rendered fields still use this name
    case 'insertItemInEditor':
        $sourceId = (int) http::dragdata('node_id');
        if (http::dragdata('drag_type') === 'page') {
            $targetPage = $this->pageFor($sourceId);
            $stored = $targetPage === null ? null : \Systopic\Cms\Media\EditorTag::page($sourceId, (string) $targetPage->row->label, $this->params->lang);
            $tag = $stored === null ? null : (new \Systopic\Cms\Refs\Resolver($this->scope, $this->params))->renderForEditor($stored);
        } else {
            $tag = $this->editorTag($sourceId);
        }
        if ($tag === null) break;
        $field = (string) http::dataset('field');
        if (!in_array($field, PageOperator::EDITABLE, true)) break;
        $selector = '.editor[data-field="' . $field . '"]';
        if ($targetId = (int) http::dataset('node_id')) $selector .= '[data-node_id="' . $targetId . '"]';
        client::insertHtmlAtCursor($tag, $selector);
        client::trigger($selector, 'input');
        break;

    case 'updateMediaCrop':
        // The crop set by hand in the image editor (imageEditor.js). It used to
        // be written by media.php itself, on a GET with ?action=updateMediaCrop
        // and no login check (`TRUE ||`); now the CMS saves it, and the editor
        // reloads the format with ?rebuild.
        [$media, $crop] = \Systopic\System\Queries\MediaCrop\MediaCrop::of(
            $this->scope, (int) http::params('media_id'), (string) http::params('image_format'),
        );
        if ($media === null || (string) http::params('image_format') === '' || PageAccess::refuses($media->node)) {
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
