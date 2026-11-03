<?php
/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Cleanup\Trash\Panel $this */

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
<?= \message::flush() ?>
<section class="widget is-list">
	<header>
		<h2>Pages</h2>
		<?php if ($entries !== []) { ?>
			<input class="listSearch" type="search" placeholder="Search" data-on_input="filterTrash" autocomplete="off">
		<?php } ?>
	</header>
	<p class="tagsHint">Restoring includes child pages. Permanent deletion removes the page branch in every language and its media files. An alias or redirect elsewhere that points into the branch must be resolved first.</p>
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
							<td><span><?= $e($title) ?></span><?php foreach ($pages as $page) {
                                $iso = (string) $page->languageIso;
                                if ($iso !== '' && $iso !== '*') {
                                    ?><span class="logPill" data-table-mobile-only><?= $e(strtoupper($iso)) ?></span><?php
                                }
                            } ?></td>
							<td class="muted" data-table-desktop-only>
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
											<a class="button icononly" target="_blank" rel="noopener" href="<?= $e($preview) ?>" title="Preview"><i class="cmsIcon cmsIcon-eye"></i></a>
										</li>
									<?php } ?>
									<li>
										<button type="button" data-cms-action class="button icononly" title="Restore" data-on_click="userConfirm" data-action="restoreNode" data-node_id="<?= (int) $nodeId ?>" data-confirm="<?= $e('Restore “' . $title . '”?') ?>"><i class="cmsIcon cmsIcon-arrow-rotate-left"></i></button>
									</li>
									<li>
										<button type="button" data-cms-action class="button icononly" title="Delete for good" data-on_click="userConfirm" data-action="deleteNode" data-node_id="<?= (int) $nodeId ?>" data-confirm="<?= $e('Delete “' . $title . '” for good? Its media and the pages under it go too. This cannot be undone.') ?>"><i class="cmsIcon cmsIcon-trash-xmark"></i></button>
									</li>
								</ul>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<footer><button type="button" data-cms-action class="button" data-on_click="userConfirm" data-action="emptyTrash" data-confirm="<?= $e('Delete all ' . count($entries) . ' pages in the trash for good, with their media? This cannot be undone.') ?>"><i class="cmsIcon cmsIcon-trash-xmark"></i><span>Empty trash</span></button></footer>
	<?php } ?>
</section>
