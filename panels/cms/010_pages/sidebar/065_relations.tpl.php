<?php
/** @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this */
$pages = $this->parent;
$page = $pages->page;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$link = static function (int $id, string $kind) use ($pages, $e): string {
    $target = $pages->pageFor($id);
    $icon = $kind === 'alias' ? 'cmsIcon-file-import' : 'cmsIcon-route';
    return $target === null ? '<span>Unavailable page</span>' : '<a class="ajax sideAction relationLink ' . $kind . '" href="' . $e(\http::$root . 'cms/pages/?id=' . $id . '&lang=' . $pages->params->lang . '&relations=aliases') . '"><i class="cmsIcon ' . $icon . '"></i><span>' . $e($target->label->plain ?: ('Page ' . $id)) . '<small>' . $e($target->rewritepaths->path !== '' ? '/' . ltrim($target->rewritepaths->path . $target->rewritepaths->appendix, '/') : 'Page #' . $id) . '</small></span></a>';
};
$incoming = $this->incomingRelations();
$aliases = array_filter($incoming, fn($row) => (int) $row['node_original_node_id'] === $page->id);
$redirects = array_filter($incoming, fn($row) => (int) $row['node_redirect_node_id'] === $page->id);
$redirect = (int) $page->node->relationId('redirectNode');
?>
<div class="pageRelations">
    <?php if ($page->isAlias) { ?>
        <div class="relationBlock">
            <h4><i class="cmsIcon cmsIcon-file-import alias"></i>This page is an alias</h4>
            <p class="hint">This is another view of the original page below. Content changes on the original appear here too. This alias has its own address and page settings.</p>
            <?= $link($page->contentNodeId, 'alias') ?>
            <?php if (!$page->hasChildren) { ?>
            <a class="sideAction quietAction" data-on_click="userConfirm" data-action="<?= http::$root ?>cms/pages/deleteAlias?node_id=<?= $page->id ?>" data-confirm="Remove this alias? The original remains intact."><i class="cmsIcon cmsIcon-trash"></i>Remove alias</a>
            <?php } ?>
        </div>
    <?php } ?>
    <?php if ($aliases !== []) { ?>
    <section class="relationBlock" data-relation-section="aliases">
        <h4><i class="cmsIcon cmsIcon-file-import alias"></i>Aliases of this page (<?= count($aliases) ?>)</h4>
        <p class="hint">These pages share this page's content. Removing an alias keeps the original.</p>
        <ul class="relationIncoming"><?php foreach ($aliases as $row) { $aliasPage = $pages->pageFor((int) $row['node_id']); ?><li>
            <?= $link((int) $row['node_id'], 'alias') ?>
            <?php if ($aliasPage !== null && !$aliasPage->hasChildren) { ?><a class="button transparent icononly small" title="Remove alias" aria-label="Remove alias" data-on_click="userConfirm" data-action="<?= http::$root ?>cms/pages/deleteAlias?node_id=<?= (int) $row['node_id'] ?>" data-confirm="Remove this alias? The original remains intact."><i class="cmsIcon cmsIcon-trash"></i></a><?php } ?>
        </li><?php } ?></ul>
    </section>
    <?php } ?>
    <?php if ($redirect > 1) { ?>
        <div class="relationBlock">
            <h4><i class="cmsIcon cmsIcon-route redirect"></i>This page redirects</h4>
            <p class="hint">Visitors to this page are automatically sent to:</p>
            <ul class="relationIncoming"><li>
                <?= $link($redirect, 'redirect') ?>
                <button type="button" class="button transparent icononly small" title="Remove redirect" aria-label="Remove redirect" data-on_click="sidebar/removeRedirect" data-source="<?= $page->id ?>"><i class="cmsIcon cmsIcon-link-slash"></i></button>
            </li></ul>
        </div>
    <?php } ?>
    <section class="relationBlock">
        <h4><i class="cmsIcon cmsIcon-route redirect"></i>Redirects to this page</h4>
        <p class="hint">Visitors from these pages are sent here.</p>
        <div class="relationSearch" data-relation="redirectFrom">
            <label class="relationSearchLabel"><span class="visually-hidden">Add redirect from another page</span><input type="search" name="search" aria-label="Add redirect from another page" autocomplete="off" data-focus-id="relation-redirectFrom" data-on_input="sidebar/searchRelations" data-kind="redirectFrom" data-source="<?= $page->id ?>" placeholder="Add redirect from a page…"></label>
            <ul class="relationResults" aria-live="polite"></ul>
        </div>
        <?php if ($redirects !== []) { ?>

                <ul class="relationIncoming"><?php foreach ($redirects as $row) { ?><li>
                    <?= $link((int) $row['node_id'], 'redirect') ?>
                    <button type="button" class="button transparent icononly small" title="Remove redirect to this page" aria-label="Remove redirect to this page" data-on_click="sidebar/removeIncomingRedirect" data-source="<?= $page->id ?>" data-target="<?= (int) $row['node_id'] ?>"><i class="cmsIcon cmsIcon-link-slash"></i></button>
                </li><?php } ?></ul>
        <?php } ?>
        <?php if ($redirects === []) { ?><p class="hint relationNoRedirects">No redirects yet.</p><?php } ?>
    </section>
</div>
