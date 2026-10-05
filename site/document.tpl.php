<?php
/**
 * System default render host for pages — opened by site.php as
 * openRoot('document'). A project overrides it by shipping its own
 * pages/document.tpl.php (views roots resolve last-wins).
 *
 * It emits no markup: its only job is to produce exactly two child layers,
 * head and body, which PagesRenderer::exportDocument() hands to the client as
 * the two DOM roots to patch.
 *
 * Both are opened on their RENDER HOST (PageNavigator::openRenderHost): the
 * first node from the selected page upwards that has the view as its own —
 * a template folder shipping body.tpl.php (full-screen views), a site's
 * pages/<site>/ folder (multi-site: own head and body per site) — else the
 * tree root with the shared views. Inside the template `$this` is that host.
 *
 * Both opens are unconditional — the client walks child layers by index, so
 * the layer count must not depend on request state
 * (see .cursor/docs/panel-diff-rendering.md §6b).
 *
 * @var \Systopic\Cms\Pages\Route\RouteNode $this
 */

$this->withRenderHost('head');
$this->withRenderHost('body');
