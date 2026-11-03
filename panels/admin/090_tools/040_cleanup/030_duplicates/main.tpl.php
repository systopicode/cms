<?php
/** @var \Systopic\Cms\Panels\Root\Admin\Tools\Cleanup\Duplicates\Panel $this */

use Systopic\Cms\Tables\Pages\Languages;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$langs = Languages::all();
$default = Languages::default();
$langName = static function (?string $iso) use ($langs): string {
	$iso = (string) $iso;
	if ($iso === '' || $iso === '*') {
		return '';
	}
	return (string) ($langs[$iso]['label'] ?? strtoupper($iso));
};

$pages = $this->pages;
usort($pages, static function ($a, $b) use ($default): int {
	$by = strcasecmp(trim((string) $a->label), trim((string) $b->label));
	if ($by !== 0) {
		return $by;
	}
	$rank = static fn($page): int => $page->languageIso === $default ? 0 : 1;
	return $rank($a) <=> $rank($b) ?: ((int) $a->id <=> (int) $b->id);
});

$crops = $this->crops;
usort($crops, static function ($a, $b): int {
	$by = strcasecmp(trim((string) ($a->media?->label ?? '')), trim((string) ($b->media?->label ?? '')));
	return $by !== 0 ? $by : strcasecmp((string) $a->format, (string) $b->format);
});
?>
<h1>Duplicates</h1>
<p class="tagsHint">Duplicate database records and identical original files.</p>
<?= \message::flush() ?>
<section class="widget is-list">
	<header>
		<h2>Pages</h2>
		<?php if ($pages !== []) { ?>
			<input class="listSearch" type="search" placeholder="Search" data-on_input="filterList" autocomplete="off">
		<?php } ?>
	</header>
	<p class="tagsHint">Duplicate page records. Removing a listed duplicate keeps the other record.</p>
	<?php if ($pages === []) { ?>
		<main><p>No duplicate pages.</p></main>
	<?php } else { ?>
		<div class="tableScroll">
			<table>
				<thead>
					<tr>
						<th>Page</th>
						<th>Language</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($pages as $page) {
						$title = trim((string) $page->label) ?: 'Untitled';
						?>
						<tr>
							<td><?= $e($title) ?></td>
							<td class="muted"><?= $e($langName($page->languageIso)) ?></td>
							<td class="icons">
								<ul>
									<li>
										<button type="button" data-cms-action class="button icononly" title="Delete" data-on_click="userConfirm" data-action="deletePage" data-page_id="<?= (int) $page->id ?>" data-confirm="<?= $e('Delete this copy of “' . $title . '”?') ?>"><i class="cmsIcon cmsIcon-trash"></i></button>
									</li>
								</ul>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
</section>
<section class="widget is-list">
	<header>
		<h2>Stored image crops</h2>
		<?php if ($crops !== []) { ?>
			<input class="listSearch" type="search" placeholder="Search" data-on_input="filterList" autocomplete="off">
		<?php } ?>
	</header>
	<p class="tagsHint">Duplicate crop records for the same image and format. This does not compare uploaded file contents. Delete removes only that crop record.</p>
	<?php if ($crops === []) { ?>
		<main><p>No duplicate images.</p></main>
	<?php } else { ?>
		<div class="tableScroll">
			<table>
				<thead>
					<tr>
						<th>Image</th>
						<th>Format</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($crops as $crop) {
						$title = trim((string) ($crop->media?->label ?? '')) ?: 'Untitled';
						$format = trim((string) $crop->format) ?: 'original';
						?>
						<tr>
							<td><?= $e($title) ?></td>
							<td class="muted"><?= $e($format) ?></td>
							<td class="icons">
								<ul>
									<li>
										<button type="button" data-cms-action class="button icononly" title="Delete" data-on_click="userConfirm" data-action="deleteCrop" data-crop_id="<?= (int) $crop->id ?>" data-confirm="<?= $e('Delete this copy of “' . $title . '”?') ?>"><i class="cmsIcon cmsIcon-trash"></i></button>
									</li>
								</ul>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
</section>

<section class="widget is-list">
    <header><h2>Identical media files</h2><button type="button" class="button" data-on_click="scanFiles"><i class="cmsIcon cmsIcon-magnifying-glass"></i>Check files</button></header>
    <main><p class="tagsHint">Compares local originals of all media types by size and SHA-256. Nothing is deleted. Generated formats and external media are excluded. Each check has a five-second time budget.</p>
    <?php if ($result = $this->fileDuplicates) { ?>
        <?php if ($result['remaining'] || $result['unreadable']) { ?><p><i class="cmsIcon cmsIcon-triangle-exclamation is-warning"></i> Partial result: <?= (int) $result['remaining'] ?> candidates were not fully checked within the time limit; <?= (int) $result['unreadable'] ?> files could not be read. This is not a complete all-clear.</p><?php } ?>
        <?php if (!$result['groups']) { ?><p>No identical files found among the checked originals.</p><?php } ?>
    <?php } ?>
    </main>
    <?php if (!empty($result['groups'])) { ?>
    <div class="tableScroll"><table><thead><tr><th>File</th><th>Media ID</th><th>Match group</th></tr></thead><tbody>
        <?php foreach ($result['groups'] as $index => $group) { foreach ($group as $file) { ?>
        <tr><td><?= $e(trim((string) $file['label']) ?: 'Untitled') ?></td><td><?= (int) $file['id'] ?></td><td><?= (int) $index + 1 ?></td></tr>
        <?php } } ?>
    </tbody></table></div>
    <?php } ?>
</section>
