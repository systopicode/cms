<?php
/**
 * Dashboard: what is left to do on the pages, one line each with its count
 * and why it matters. A line opens to how to fix it and the first pages concerned.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this
 */

use Systopic\Cms\Queries\Dashboard\Dashboard;
use Systopic\Cms\Queries\LinkCheck\LinkCheck;
use Systopic\Cms\Queries\Misses\Misses;
use Systopic\Cms\Queries\UnusedMedia\UnusedMedia;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$scope = \Systopic\Db\Scope::default();
$figures = Dashboard::figures($scope);
$broken = LinkCheck::brokenPages($scope, PHP_INT_MAX);
$figures['brokenlinks'] = count($broken);
$figures['deadlinks'] = count(array_intersect_key(LinkCheck::lastCheck()['failed'], LinkCheck::external($scope)));
$figures['unusedmedia'] = count(UnusedMedia::of($scope));
$figures['misses'] = Misses::count($scope);
$href = \http::$root . 'cms/pages/?id=';
// level: fix - visitors run into it; improve - better for search and accessibility; info - only to know
$tasks = array_filter([
	'brokenlinks' => ['fix', 'fa-link-slash', 'Broken links', 'Visitors land on an error page.', 'Open the page and correct or remove the link. Pages and files that were deleted are the usual cause.'],
	'deadlinks' => ['fix', 'fa-globe', 'Links to other websites not answering', 'The other website may be gone or moved.', ''],
	'nodescription' => ['improve', 'fa-magnifying-glass', 'Pages without a description', 'Search engines show it under the title in their results.', 'Open the page, in the sidebar under Meta write one or two sentences about it (up to 160 letters). Without it, search engines pick any text of the page.'],
	'noalt' => ['improve', 'fa-image', 'Media without a label', 'Read aloud to blind visitors, used by image search and as link text.', 'Open the medium and write in Label what it shows.'],
	'hidden' => ['info', 'fa-eye-slash', 'Hidden pages', 'Visitors can’t see them yet.', 'Fine for drafts. To publish, click the crossed-out eye in the page tree.'],
	'planned' => ['info', 'fa-calendar', 'Planned pages', 'They go online by themselves on their date.', 'Nothing to do, unless the date is wrong.'],
	'trashed' => ['info', 'fa-trash', 'Pages in the trash', 'Restore them or delete them for good.', ''],
	'unusedmedia' => ['info', 'fa-photo-film', 'Unused media', 'No page uses these files.', ''],
	'misses' => ['fix', 'fa-diamond-turn-right', 'Unknown addresses', 'Visitors opened an address that belongs to no page.', ''],
], static fn(string $key): bool => $figures[$key] > 0, ARRAY_FILTER_USE_KEY);
// the first ones concerned, each [href, text, small]: media open by themselves, the rest on their page
$items = static fn(string $key): array => match ($key) {
	'noalt' => array_map(static fn(array $media): array => [\http::$root . 'cms/pages/editor/file/?target_id=' . $media['node'], $media['filename'], $media['type']], Dashboard::unlabelled($scope)),
	'brokenlinks' => array_map(static fn(array $page): array => [$href . $page['id'], $page['label'], isset($page['target']) && str_starts_with($page['target'], \http::$root) ? substr($page['target'], strlen(\http::$root)) : ($page['target'] ?? null)], array_slice($broken, 0, 5)),
	default => array_map(static fn(array $page): array => [$href . $page['id'], $page['label'], null], Dashboard::pages($scope, $key, 5)),
};
?>
<header><h2><i class="fa-regular fa-list-check"></i>To do</h2></header>
<?php if ($tasks === []) { ?>
	<main><p class="hint"><i class="fa-regular fa-circle-check"></i> All done: no broken links, every page has a description, every image a label.</p></main>
<?php } else { ?>
	<ul class="dashList dashTasks">
		<?php foreach ($tasks as $key => [$level, $icon, $label, $why, $hint]) {
			$count = '<i class="fa-regular ' . $icon . '"></i><b class="dashCount">' . number_format($figures[$key], 0, ',', '.') . '</b><span>' . $label . '<small>' . $why . '</small></span>';
			?>
			<li class="is-<?= $level ?>">
				<?php if (in_array($key, ['trashed', 'deadlinks', 'unusedmedia', 'misses'], true)) {
					$tool = match ($key) {
						'trashed' => 'trash/',
						'unusedmedia' => 'unused/',
						'misses' => 'misses/',
						default => 'links/',
					};
					?>
					<a class="ajax" href="<?= $e(\http::$root . 'cms/tools/maintenance/' . $tool) ?>"><?= $count ?><i class="fa-regular fa-chevron-right"></i></a>
				<?php } else { ?>
					<details>
						<summary><?= $count ?><i class="fa-regular fa-chevron-down"></i></summary>
						<p><b>How:</b> <?= $e($hint) ?></p>
						<ul>
							<?php foreach ($items($key) as [$link, $text, $small]) { ?>
								<li><a class="ajax" href="<?= $e($link) ?>"><?= $e($text !== '' ? $text : 'Untitled') ?><?php if ($small !== null) { ?><small><?= $e($small) ?></small><?php } ?></a></li>
							<?php } ?>
						</ul>
						<?php if ($key === 'brokenlinks') { ?>
							<p><a class="ajax" href="<?= $e(\http::$root . 'cms/tools/maintenance/links/') ?>">All links <i class="fa-regular fa-chevron-right"></i></a></p>
						<?php } ?>
					</details>
				<?php } ?>
			</li>
		<?php } ?>
	</ul>
<?php } ?>
