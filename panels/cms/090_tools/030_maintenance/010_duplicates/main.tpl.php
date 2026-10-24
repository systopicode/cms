<?php
/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Duplicates\Panel $this */

use Systopic\Cms\Tables\Pages\Languages;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$langs = Languages::all();
$default = defined('DEFAULT_LANG') && DEFAULT_LANG !== '' ? (string) DEFAULT_LANG : Languages::default();
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
<p class="tagsHint">Pages and images that exist twice.</p>
<?= \message::flush() ?>
<section class="widget is-list">
	<header>
		<h2>Pages</h2>
		<?php if ($pages !== []) { ?>
			<input class="listSearch" type="search" placeholder="Search" data-on_input="filterList" autocomplete="off">
		<?php } ?>
	</header>
	<p class="tagsHint">The same page stored twice. Delete removes that copy and leaves the other.</p>
	<?php if ($pages === []) { ?>
		<main><p>No duplicate pages.</p></main>
	<?php } else { ?>
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
									<a class="button icononly" title="Delete" data-on_click="userConfirm" data-action="deletePage" data-page_id="<?= (int) $page->id ?>" data-confirm="<?= $e('Delete this copy of “' . $title . '”?') ?>"><i class="fa-regular fa-trash"></i></a>
								</li>
							</ul>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	<?php } ?>
</section>
<section class="widget is-list">
	<header>
		<h2>Images</h2>
		<?php if ($crops !== []) { ?>
			<input class="listSearch" type="search" placeholder="Search" data-on_input="filterList" autocomplete="off">
		<?php } ?>
	</header>
	<p class="tagsHint">The same image in the same format, stored twice. Delete removes that copy.</p>
	<?php if ($crops === []) { ?>
		<main><p>No duplicate images.</p></main>
	<?php } else { ?>
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
									<a class="button icononly" title="Delete" data-on_click="userConfirm" data-action="deleteCrop" data-crop_id="<?= (int) $crop->id ?>" data-confirm="<?= $e('Delete this copy of “' . $title . '”?') ?>"><i class="fa-regular fa-trash"></i></a>
								</li>
							</ul>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	<?php } ?>
</section>
