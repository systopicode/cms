<?php
$row = $this->mediaRow;
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<main class="mediaDetails" data-node_id="<?= (int) $this->targetId ?>">
    <div class="mediaDetailCards">
        <section class="widget is-list">
            <header><h2>File</h2></header>
            <main><?php include __DIR__ . '/fields.php'; ?></main>
            <footer>
                <a class="button" href="<?= $e($original->src) ?>" target="_blank" rel="noopener"><i class="cmsIcon cmsIcon-eye" aria-hidden="true"></i>Open original</a>
                <a class="button" href="<?= $e($original->src) ?>" download><i class="cmsIcon cmsIcon-download" aria-hidden="true"></i>Download</a>
            </footer>
        </section>
        <section class="widget is-list">
            <header><h2>Tags</h2></header>
            <main><?php $this->withView('tags'); ?></main>
        </section>
        <?php
        $usageScope = $this->pages()->scope;
        $usageNode = $this->targetId;
        $usageMedia = (int) $row->id;
        $usageHref = $this->pages()->href;
        $usageAsWidget = true;
        include dirname(__DIR__, 2) . '/media-usage.tpl.php';
        ?>
        <section class="widget is-list">
            <header><h2>Technical details</h2></header>
            <main><?php include __DIR__ . '/facts.php'; ?></main>
        </section>
        <section class="widget is-list mediaDetailPermissions">
            <header><h2>Permissions</h2></header>
            <main><?php $this->withView('permissions'); ?></main>
        </section>
        <section class="widget is-list mediaDetailDelete">
            <header><h2>Delete file</h2></header>
            <main><p class="tagsHint">Removes this file from the media library. Pages that use it may lose their media or download.</p></main>
            <footer><?php include __DIR__ . '/delete-button.php'; ?></footer>
        </section>
    </div>
</main>
