<?php
$uses = \Systopic\Cms\Queries\MediaUsage\MediaUsage::of($usageScope, $usageNode, $usageMedia);
$heading = 'Used on (' . count($uses) . ')';
if ($usageAsWidget ?? false) { ?>
<section class="widget is-list">
    <header><h2><?= $heading ?></h2></header>
    <main class="pageRelations mediaUsage">
<?php } else { ?>
<details class="sidebarSection" data-section="media-usage" open>
    <summary><span class="sidebarLabel"><?= $heading ?></span><span class="sidebarTools"><i class="cmsIcon cmsIcon-chevron-down sidebarFold" aria-hidden="true"></i></span></summary>
    <div class="sidebarItem pageRelations mediaUsage">
<?php } ?>
<p class="tagsHint">Used in page content, libraries or share images.</p>
<ul class="relationIncoming">
<?php foreach ($uses as $use): ?>
<li><a class="sideAction relationLink" data-cms-media-usage-link href="<?= $e($usageHref . '?id=' . $use['node'] . '&lang=' . rawurlencode($use['lang'])) ?>"><i class="cmsIcon cmsIcon-file"></i><span><?= $e($use['label'] ?: 'Untitled') ?><small><?= $e(implode(' · ', $use['reasons']) . ($use['lang'] ? ' · ' . $use['lang'] : '')) ?></small></span></a></li>
<?php endforeach; ?>
</ul>
<?php if (!$uses): ?><p class="tagsHint">No page references were found by this check. Project templates or external links may still use this file.</p><?php endif; ?>
<?php if ($usageAsWidget ?? false) { ?>
    </main>
</section>
<?php } else { ?>
    </div>
</details>
<?php } ?>
