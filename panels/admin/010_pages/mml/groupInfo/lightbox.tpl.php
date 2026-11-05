<?php
use Systopic\System\Media\GroupPolicy;
$pages = $this->parent->parent;
$group = (string) http::get('group');
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$label = $pages->mediaGroups[$group]['label'] ?? ($group === 'text' ? 'In text' : $group);
?>
<div class="cmslightbox cms cmsNestedDialog mediaGroupHelp" data-transient-info data-size="1"><section>
<header><h1><?= $e($label) ?> · File types</h1><ul class="barmenu"><li><a class="ajax" data-focus-id="media-group-info-close" data-history="false" href="<?= $e($pages->currentViewHref()) ?>" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a></li></ul></header>
<main><p class="tagsHint">These file types are allowed for uploads, replacements and moving files into this group. Existing files stay in the library when the rules change.</p>
<section class="widget is-list"><div class="tableScroll"><table><thead><tr><th>File type</th><th>Support</th></tr></thead><tbody>
<?php foreach (GroupPolicy::allowed($pages->mediaGroups, $group) as $ext) { [$name, $capability] = GroupPolicy::TYPES[$ext]; ?>
<tr><td><?= $e($name) ?></td><td><?= $e($capability) ?></td></tr>
<?php } ?>
</tbody></table></div></section><p class="tagsHint">Playback depends on the browser and codec. These rules do not make files private. Allowed types can be changed in Media Settings.</p>
</main></section></div>
