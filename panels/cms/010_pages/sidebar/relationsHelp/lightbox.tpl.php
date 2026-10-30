<?php $pages = $this->parent->parent; ?>
<div class="cmslightbox cms relationsHelp" data-transient-info>
    <section>
        <header><h1>Aliases &amp; Redirects</h1><ul class="barmenu"><li><a class="ajax" data-history="false" href="<?= htmlspecialchars($pages->currentViewHref(), ENT_QUOTES) ?>" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a></li></ul></header>
        <main>
            <h2><i class="cmsIcon cmsIcon-file-import"></i> Alias</h2>
            <p>An alias shows the original page's content at another place in the page tree. It is a second view of the same content, rather than an independent copy.</p>
            <p>Editing the original's text or media changes what appears through its aliases too. An alias keeps its own address, position and page settings. To create independent content, duplicate the page instead.</p>
            <p>Create aliases in the page tree. The original-page link in the sidebar opens the shared content. Removing an alias leaves the original page intact.</p>
            <h2><i class="cmsIcon cmsIcon-route"></i> Redirect</h2>
            <p>A redirect sends visitors to a different page instead of showing the source page's content. It does not mirror or copy content.</p>
            <p>“Redirects to this page” lists other pages whose visitors are sent to the page currently open. Search for a source page to redirect it here. Removing the connection stops that forwarding; it does not delete either page.</p>
            <p>A page can have one outgoing redirect and several incoming redirects. Self-references and circular connections are rejected.</p>
        </main>
    </section>
</div>
