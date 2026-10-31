                <details class="sidebarSection" data-section="media-usage" open>
                    <?php $uses = \Systopic\Cms\Queries\MediaUsage\MediaUsage::of($usageScope, $usageNode, $usageMedia); ?>
                    <summary><span class="sidebarLabel">Used on (<?= count($uses) ?>)</span><span class="sidebarTools"><i class="cmsIcon cmsIcon-chevron-down sidebarFold" aria-hidden="true"></i></span></summary>
                    <div class="sidebarItem pageRelations mediaUsage">
                        <p class="tagsHint">Used in page content, libraries or share images.</p>
                        <ul class="relationIncoming">
                        <?php foreach ($uses as $use): ?>
                        <li><a class="sideAction relationLink" href="<?= $e($usageHref . '?id=' . $use['node'] . '&lang=' . rawurlencode($use['lang'])) ?>"><i class="cmsIcon cmsIcon-file"></i><span><?= $e($use['label'] ?: 'Untitled') ?><small><?= $e(implode(' · ', $use['reasons']) . ($use['lang'] ? ' · ' . $use['lang'] : '')) ?></small></span></a></li>
                        <?php endforeach; ?>
                        </ul>
                        <?php if (!$uses): ?><p class="tagsHint">No page references found.</p><?php endif; ?>
                    </div>
                </details>
