<?php
/** @var \Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Image\Panel $this */
$set = \Systopic\System\Queries\MediaCrops\MediaCrops::one($this->pages()->scope, (int) $this->mediaRow->id);
$list = $set === null ? null : \Systopic\System\Media\VariantList::of($set);
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$bytes = static fn(int $size): string => $size >= 1048576 ? number_format($size / 1048576, 1) . ' MB' : number_format($size / 1024, 0) . ' KB';
$pixels = static fn(array $file): string => $file['width'] > 0 && $file['height'] > 0 ? $file['width'] . ' × ' . $file['height'] : '';
$fileLink = static function (array $file, bool $limited = false) use ($e, $bytes): string {
    $state = $file['made'] ? 'Generated' : 'Generated on first open';
    $details = [$file['file'], $state];
    if ($file['made']) $details[] = $bytes($file['bytes']);
    if ($limited) $details[] = 'Limited to source dimensions';
    $label = $e(implode(' · ', $details));
    $style = ($file['made'] ? ' generated' : '') . ($limited ? ' limited' : '');
    return '<a class="variantLink" href="' . $e($file['url']) . '" target="_blank" rel="noopener" title="' . $label . '" aria-label="' . $e($file['name']) . ' · ' . $label . '"><i class="variantMark' . $style . '" aria-hidden="true"></i>' . $e($file['name']) . '</a>';
};
?>
<section class="widget is-list">
    <header>
        <h2>Original</h2>
        <a class="button" href="<?= $e($original->src) ?>" target="_blank" rel="noopener"><i class="cmsIcon cmsIcon-eye" aria-hidden="true"></i> Open</a>
        <a class="button" href="<?= $e($original->src) ?>" target="_self" download><i class="cmsIcon cmsIcon-arrow-down-to-line" aria-hidden="true"></i> Download</a>
    </header>
    <main><strong><?= $e($media->filename) ?></strong><?php if ($list !== null) {
        $source = $list['original'];
        $details = array_filter([$pixels($source), $source['bytes'] > 0 ? $bytes($source['bytes']) : '']);
        if ($details) { ?><p class="tagsHint"><?= $e(implode(' · ', $details)) ?></p><?php }
    } ?></main>
</section>
<?php if ($list === null) { ?>
    <p class="tagsHint">This image has no variants of its own.</p>
<?php return; } ?>
<section class="widget is-list">
    <header><h2>Image variants</h2><small><?= (int) $list['made'] ?> of <?= (int) $list['all'] ?> generated in total</small></header>
    <div class="variantLegend">
        <span><i class="variantMark generated"></i> Generated</span>
        <span><i class="variantMark"></i> Generated on first open</span>
        <span><i class="variantMark limited"></i> Limited to source dimensions</span>
    </div>
    <div class="tableScroll">
        <table>
            <thead><tr><th scope="col">Crop</th>
                <?php foreach ($list['sizes'] as $size) { ?><th scope="col"><?= $e($size) ?> MP</th><?php } ?>
                <th scope="col">Native size</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list['grid'] as $row) { ?>
                <tr><td><?= $e($row['title']) ?></td>
                    <?php foreach ($row['cells'] as $cell) { ?>
                        <td><?php if ($cell !== null) { ?>
                            <div><?= $fileLink($cell, $cell['note'] !== '') ?><small><?= $e($pixels($cell)) ?></small></div>
                        <?php } ?></td>
                    <?php } ?>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</section>
<?php foreach (['fixed' => 'Fixed formats', 'others' => 'Other files in the folder'] as $key => $heading) {
    if (!$list[$key]) continue; ?>
    <section class="widget is-list">
        <header><h2><?= $heading ?></h2></header>
        <div class="tableScroll"><table>
            <thead><tr><th scope="col">File</th><?php if ($key === 'fixed') { ?><th scope="col">Dimensions</th><?php } ?><th scope="col">Details</th><th scope="col">File size</th></tr></thead>
            <tbody><?php foreach ($list[$key] as $file) { ?>
                <tr><td><?= $fileLink($file) ?></td><?php if ($key === 'fixed') { ?><td><?= $e($pixels($file)) ?></td><?php } ?>
                    <td><?= $e($file['note']) ?></td><td><?= $file['made'] ? $bytes($file['bytes']) : 'On first open' ?></td></tr>
            <?php } ?></tbody>
        </table></div>
    </section>
<?php } ?>
