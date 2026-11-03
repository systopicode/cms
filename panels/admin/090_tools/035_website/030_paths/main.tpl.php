<?php
/** @var \Systopic\Cms\Panels\Root\Admin\Tools\Website\Paths\Panel $this */

use Systopic\Cms\Tables\Pages\Languages;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$langs = Languages::all();
$langName = static function (?string $iso) use ($langs): string {
	$iso = (string) $iso;
	if ($iso === '' || $iso === '*') {
		return '';
	}
	return (string) ($langs[$iso]['label'] ?? strtoupper($iso));
};

$entries = $this->paths;
usort($entries, static function (array $a, array $b): int {
	$title = static function (array $entry): string {
		$label = trim((string) ($entry['page']?->label ?? ''));
		return $label !== '' ? $label : 'Untitled';
	};
	$by = strcasecmp($title($a), $title($b));
	if ($by !== 0) {
		return $by;
	}
	return strcasecmp((string) $a['path']->string, (string) $b['path']->string);
});
?>

<h1>URL paths</h1>
<p class="tagsHint">Every address the site answers on.</p>
<?= \message::flush() ?>
<section class="widget is-list">
	<header>
		<h2>Paths</h2>
		<?php if ($entries !== []) { ?>
			<input class="listSearch" type="search" placeholder="Search" data-on_input="filterList" autocomplete="off">
		<?php } ?>
		<button type="button" data-cms-action class="button" data-on_click="assignSites"><i class="cmsIcon cmsIcon-sitemap"></i><span>Reassign sites</span></button>
		<button type="button" data-cms-action class="button" data-on_click="buildAllAutoRewritepaths"><i class="cmsIcon cmsIcon-arrows-rotate"></i><span>Create paths</span></button>
	</header>
	<p class="tagsHint">The primary path is the preferred address for a page. Other paths can keep older links working. Removing a path can make that address unavailable.</p>
	<?php if ($entries === []) { ?>
		<main><p>No addresses.</p></main>
	<?php } else { ?>
		<div class="tableScroll">
			<table>
				<thead>
					<tr>
						<th>Page</th>
						<th>Language</th>
						<th>Path</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($entries as ['path' => $path, 'page' => $page, 'node' => $node]) {
						$title = trim((string) ($page?->label ?? '')) ?: 'Untitled';
						$raw = trim((string) $path->string);
						$shown = $raw !== '' ? $raw : ($path->isPrimary ? '/' : '—');
						$href = ($raw !== '' || $path->isPrimary)
							? rtrim((string) \http::$siteRoot, '/') . '/' . ltrim($raw, '/')
							: '';
						?>
						<tr>
							<td>
								<?= $e($title) ?>
								<?php if ($node?->isTrashed) { ?><span class="logPill is-bad">Trash</span><?php } ?>
							</td>
							<td class="muted"><?= $e($langName($page?->languageIso)) ?></td>
							<td>
								<?= $e($shown) ?>
								<?php if ($path->isPrimary) { ?><span class="logPill">Primary</span><?php } ?>
								<?php if ($page !== null && !$page->isPublished) { ?><span class="logPill">Unpublished</span><?php } ?>
							</td>
							<td class="icons">
								<ul>
									<?php if ($href !== '') { ?>
										<li>
											<a class="button icononly" target="_blank" rel="noopener" href="<?= $e($href) ?>" title="Preview"><i class="cmsIcon cmsIcon-eye"></i></a>
										</li>
									<?php } ?>
									<li>
										<button type="button" data-cms-action class="button icononly" title="Delete" data-on_click="userConfirm" data-action="deletePath" data-rewritepath_id="<?= (int) $path->id ?>" data-confirm="<?= $e('Delete “' . $shown . '”?') ?>"><i class="cmsIcon cmsIcon-trash"></i></button>
									</li>
								</ul>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
	<footer>
		<button type="button" data-cms-action class="button" data-on_click="userConfirm" data-action="deleteTrashedPaths" data-confirm="Delete the addresses of trashed pages?"><i class="cmsIcon cmsIcon-trash"></i><span>Delete trashed</span></button>
		<button type="button" data-cms-action class="button" data-on_click="userConfirm" data-action="removeAllRewritepaths" data-confirm="Delete every address?"><i class="cmsIcon cmsIcon-trash"></i><span>Delete all</span></button>
	</footer>
</section>
