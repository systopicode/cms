<?php
/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Trash\Panel $this */

use Systopic\Cms\Tables\Pages\Languages;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$langs = Languages::all();
$default = $this->siteLanguage();

$entries = $this->trashed;
uasort($entries, function (array $a, array $b): int {
	return strcasecmp($this->pageTitle($a['pages']), $this->pageTitle($b['pages']));
});
?>
<h1>Trash</h1>
<p class="tagsHint">Deleted pages.</p>
<?= \message::flush() ?>
<section class="widget is-list">
	<header>
		<h2>Pages</h2>
		<?php if ($entries !== []) { ?>
			<input class="listSearch" type="search" placeholder="Search" data-on_input="filterTrash" autocomplete="off">
		<?php } ?>
	</header>
	<p class="tagsHint">The eye opens the page. Restore puts it back, including the pages that were under it. Delete removes it for good, with its media and the pages under it.</p>
	<?php if ($entries === []) { ?>
		<main><p>The trash is empty.</p></main>
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
					<?php foreach ($entries as $nodeId => $entry) {
						$pages = $entry['pages'];
						usort($pages, static function ($a, $b) use ($default): int {
							$rank = static fn($page): int => $page->languageIso === $default ? 0 : 1;
							return $rank($a) <=> $rank($b)
								?: strcasecmp((string) $a->languageIso, (string) $b->languageIso);
						});
						$title = $this->pageTitle($pages);
						$primary = $pages[0] ?? null;
						$preview = $primary === null
							? ''
							: \http::$siteRoot . '?id=' . (int) $nodeId . '&lang=' . rawurlencode((string) $primary->languageIso) . '&showtrashed';
						?>
						<tr>
							<td><?= $e($title) ?></td>
							<td class="muted">
								<?php foreach ($pages as $page) {
									$iso = (string) $page->languageIso;
									$langName = (string) ($langs[$iso]['label'] ?? ($iso === '*' ? '' : strtoupper($iso)));
									if ($langName === '') {
										continue;
									}
									?>
									<span><?= $e($langName) ?></span>
								<?php } ?>
							</td>
							<td class="icons">
								<ul>
									<?php if ($preview !== '') { ?>
										<li>
											<a class="button icononly" target="_blank" rel="noopener" href="<?= $e($preview) ?>" title="Preview"><i class="fa-regular fa-eye"></i></a>
										</li>
									<?php } ?>
									<li>
										<a class="button icononly" title="Restore" data-on_click="userConfirm" data-action="restoreNode" data-node_id="<?= (int) $nodeId ?>" data-confirm="<?= $e('Restore “' . $title . '”?') ?>"><i class="fa-regular fa-arrow-rotate-left"></i></a>
									</li>
									<li>
										<a class="button icononly" title="Delete for good" data-on_click="userConfirm" data-action="deleteNode" data-node_id="<?= (int) $nodeId ?>" data-confirm="<?= $e('Delete “' . $title . '” for good? Its media and the pages under it go too. This cannot be undone.') ?>"><i class="fa-regular fa-trash-xmark"></i></a>
									</li>
								</ul>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<footer><a class="button" data-on_click="userConfirm" data-action="emptyTrash" data-confirm="<?= $e('Delete all ' . count($entries) . ' pages in the trash for good, with their media? This cannot be undone.') ?>"><i class="fa-regular fa-trash-xmark"></i><span>Empty trash</span></a></footer>
	<?php } ?>
</section>
