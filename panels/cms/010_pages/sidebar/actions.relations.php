<?php
/** Existing Pages scope, role checks and node relations, shared with context actions. */
use Systopic\Cms\Pages\RelationRules;
use Systopic\Cms\Queries\PageSearch\PageSearch;

if ($page === null || $row === null) return;
if ($this->action === 'showRelations') {
    $this->state->sidebarGroup = 'publishing';
    $this->state->sidebarCollapsed = false;
    $this->updateView('main');
    client::callFunction('focusPageRelations', []);
    return;
}
if ((int) http::dataset('source') !== $page->id) { message::error('The selected page changed. Please choose the target again.'); return; }
$kind = (string) http::dataset('kind');
if ($this->action === 'searchRelations') {
    if ($kind !== 'redirectFrom') return;
    $term = trim((string) http::values('search'));
    $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
    $html = '';
    if (mb_strlen($term) >= 2) foreach (array_slice(PageSearch::byLabel($pages->scope, $term, $pages->params->lang), 0, 20) as $candidate) {
        $id = (int) $candidate->relationId('node');
        try {
            RelationRules::target($id, $page->id, $pages->relationNode(...));
            if ((int) ($pages->relationNode($id)['redirect'] ?? 0) === $page->id) continue;
        } catch (DomainException) { continue; }
        $targetPage = $pages->pageFor($id);
        $address = $targetPage !== null && $targetPage->rewritepaths->path !== '' ? '/' . ltrim($targetPage->rewritepaths->path . $targetPage->rewritepaths->appendix, '/') : 'Page #' . $id;
        $label = html_entity_decode((string) $candidate->label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html .= '<li class="relationResultRow"><span class="relationResultLabel"><b>' . $e($label) . '</b><small>' . $e($address) . '</small></span><button type="button" class="relationConnect" title="Redirect this page here" aria-label="Redirect ' . $e($label) . ' here" data-on_click="' . $e(\http::$root . 'cms/pages/sidebar/chooseRelation') . '" data-kind="' . $e($kind) . '" data-source="' . $page->id . '" data-target="' . $id . '"><i class="cmsIcon cmsIcon-link"></i></button></li>';
    }
    if ($html === '' && mb_strlen($term) >= 2) $html = '<li class="hint relationEmpty">No matching pages.</li>';
    client::callFunction('updatePageRelationResults', [$page->id, $kind, $term, $html]);
    return;
}
try {
    if ($this->action === 'removeIncomingRedirect') {
        $from = (int) http::dataset('target');
        if ((int) ($pages->relationNode($from)['redirect'] ?? 0) !== $page->id) throw new DomainException('This page no longer redirects here.');
        $pages->setPageRelation($from, 0, 'redirect');
    }
    elseif ($this->action === 'removeRedirect') $pages->setPageRelation($page->id, 0, 'redirect');
    elseif ($this->action === 'chooseRelation') {
        $target = (int) http::dataset('target');
        if ($kind !== 'redirectFrom') throw new DomainException('Choose a page to redirect here.');
        $pages->setPageRelation($target, $page->id, 'redirect');
    }
    $pages->updateView('tree');
    $pages->updateView('controls');
    $pages->updateView('main');
    $this->updateView('main');
    if ($this->action === 'chooseRelation') client::callFunction('clearPageRelationSearch', [$page->id]);
    message::confirm('Saved.');
} catch (DomainException $error) { message::error($error->getMessage()); }
