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
	'brokenlinks' => ['fix', 'cmsIcon-link-slash', 'Broken links', 'These references can no longer reach their intended destination.', 'Deleted or moved pages and files are common causes. The affected pages are listed below.'],
	'deadlinks' => ['fix', 'cmsIcon-globe', 'Links to other websites not answering', 'The other website may be gone or moved.', ''],
	'nodescription' => ['improve', 'cmsIcon-magnifying-glass', 'Pages without a description', 'A concise description helps explain a page in search results.', 'Use a short summary of the page’s purpose. Search engines may use it as a snippet, but can choose other text.'],
	'noalt' => ['improve', 'cmsIcon-image', 'Media without a label', 'Useful labels make media easier to identify and can supply text alternatives, depending on the template.', 'Describe the meaningful content in context, rather than repeating the file name.'],
	'hidden' => ['info', 'cmsIcon-eye-slash', 'Hidden pages', 'Visitors can’t see them yet.', 'Hidden pages remain unpublished until publication is enabled.'],
	'planned' => ['info', 'cmsIcon-calendar', 'Planned pages', 'They go online by themselves on their date.', 'These pages have a future publishing start. The configured server time determines when they become public.'],
	'trashed' => ['info', 'cmsIcon-trash', 'Pages in the trash', 'Trashed content is retained for recovery until it is permanently deleted.', ''],
	'unusedmedia' => ['info', 'cmsIcon-photo-film', 'Unused media', 'No page uses these files.', ''],
	'misses' => ['fix', 'cmsIcon-diamond-turn-right', 'Unknown addresses', 'Visitors opened an address that belongs to no page.', ''],
], static fn(string $key): bool => $figures[$key] > 0, ARRAY_FILTER_USE_KEY);
// the first ones concerned, each [href, text, small]: media open by themselves, the rest on their page
$items = static fn(string $key): array => match ($key) {
	'noalt' => array_map(static fn(array $media): array => [\http::$root . 'cms/pages/editor/file/?target_id=' . $media['node'], $media['filename'], $media['type']], Dashboard::unlabelled($scope)),
	'brokenlinks' => array_map(static fn(array $page): array => [$href . $page['id'], $page['label'], isset($page['target']) && str_starts_with($page['target'], \http::$root) ? substr($page['target'], strlen(\http::$root)) : ($page['target'] ?? null)], array_slice($broken, 0, 5)),
	default => array_map(static fn(array $page): array => [$href . $page['id'] . '&lang=' . rawurlencode($page['language']), $page['label'], $page['language']], Dashboard::pages($scope, $key, 5)),
};
?>
<header><h2><i class="cmsIcon cmsIcon-list-check"></i>To do</h2></header>
<?php if ($tasks === []) { ?>
	<main><p class="hint"><i class="cmsIcon cmsIcon-circle-check is-success"></i> No open items in these checks.</p></main>
<?php } else { ?>
	<ul class="dashList dashTasks">
		<?php foreach ($tasks as $key => [$level, $icon, $label, $why, $hint]) {
			$count = '<i class="cmsIcon ' . $icon . '"></i><b class="dashCount">' . number_format($figures[$key], 0, ',', '.') . '</b><span>' . $label . '<small>' . $why . '</small></span>';
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
					<a class="ajax" href="<?= $e(\http::$root . 'cms/tools/maintenance/' . $tool) ?>"><?= $count ?><i class="cmsIcon cmsIcon-chevron-right"></i></a>
				<?php } else { ?>
					<details>
						<summary class="button transparent"><?= $count ?><i class="cmsIcon cmsIcon-chevron-down" aria-hidden="true"></i></summary>
						<?php if ($hint !== '') { ?><p><?= $e($hint) ?></p><?php } ?>
						<ul>
							<?php foreach ($items($key) as [$link, $text, $small]) { ?>
								<li><a class="ajax" href="<?= $e($link) ?>"><?= $e($text !== '' ? $text : 'Untitled') ?><?php if ($small !== null) { ?><small><?= $e($small) ?></small><?php } ?></a></li>
							<?php } ?>
						</ul>
						<?php if ($key === 'brokenlinks') { ?>
							<p><a class="ajax" href="<?= $e(\http::$root . 'cms/tools/website/links/') ?>">All links <i class="cmsIcon cmsIcon-chevron-right"></i></a></p>
						<?php } ?>
					</details>
				<?php } ?>
			</li>
		<?php } ?>
	</ul>
<?php } ?>
