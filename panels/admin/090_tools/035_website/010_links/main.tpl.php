<?php
/** @var \Systopic\Cms\Panels\Root\Admin\Tools\Website\Links\Panel $this */

use Systopic\Cms\Queries\LinkCheck\LinkCheck;
use Systopic\System\Sys\LocalTime;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$page = static fn(int $id): string => \http::$root . 'admin/pages/?id=' . $id;
$broken = LinkCheck::broken($this->scope);
$external = LinkCheck::external($this->scope);
$labels = LinkCheck::labels($this->scope);
$last = LinkCheck::lastCheck();
$running = $last['checked'] !== null && !$last['done'];
$failed = array_intersect_key($last['failed'], $external);
?>

<h1>Links</h1>
<p class="tagsHint">Checks outgoing links in page content. Incoming · 404 records visits to addresses that do not exist. Internal links are checked when this page or the dashboard opens; external checks run when you choose Check now. The dashboard shows known problems.</p>
<?= \message::flush() ?>
<section class="widget is-list">
	<header>
		<h2>Internal</h2>
		<?php if ($broken !== []) { ?>
			<input class="listSearch" type="search" placeholder="Search" data-on_input="filterLinks" autocomplete="off">
		<?php } ?>
	</header>
	<p class="tagsHint">Links to pages, files and media of this website. Checked every time this page opens.</p>
	<?php if ($broken === []) { ?>
		<main><p><i class="cmsIcon cmsIcon-circle-check is-success"></i> Every link leads somewhere.</p></main>
	<?php } else { ?>
		<div class="tableScroll">
			<table>
				<thead>
					<tr>
						<th>Page</th>
						<th>Link</th>
						<th>Why</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($broken as $hit) { ?>
						<tr>
							<td><a class="ajax" href="<?= $e($page($hit['node'])) ?>"><?= $e($hit['label'] !== '' ? $hit['label'] : 'Untitled') ?></a> <span class="muted"><?= $e(strtoupper($hit['lang'])) ?></span></td>
							<td class="muted"><?= $e($hit['target']) ?></td>
							<td><?= $e($hit['reason']) ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
</section>

<section class="widget is-list">
	<header>
		<h2>Outgoing</h2>
		<button type="button" class="button" data-loading data-on_click="checkExternal" <?= $external === [] ? 'disabled' : '' ?>><i class="cmsIcon cmsIcon-arrows-rotate"></i><span><?= $running ? 'Continue check' : 'Check now' ?></span></button>
	</header>
	<p class="tagsHint">
		<?= count($external) ?> address<?= count($external) === 1 ? '' : 'es' ?> on other websites.
		<?php if ($running) { ?>
			Checking … <?= min($last['offset'], count($external)) ?> of <?= count($external) ?>.
		<?php } elseif ($last['checked'] !== null) { ?>
			Last checked <?= $e(LocalTime::format($last['checked'], \Systopic\System\Sys\LocalTime::DATETIME)) ?>.
		<?php } else { ?>
			External links have not been checked yet. Checks contact the destination websites.
		<?php } ?>
	</p>
	<?php if ($last['checked'] !== null && !$running && $failed === []) { ?>
		<main><p><i class="cmsIcon cmsIcon-circle-check is-success"></i> Every address answered.</p></main>
	<?php } elseif ($failed !== []) { ?>
		<div class="tableScroll">
			<table>
				<thead>
					<tr>
						<th>Address</th>
						<th>Answer</th>
						<th>Pages</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($failed as $url => $problem) { ?>
						<tr>
							<td><a href="<?= $e($url) ?>" target="_blank" rel="noopener"><?= $e($url) ?></a></td>
							<td class="muted"><?= $e($problem) ?></td>
							<td>
								<?php foreach ($external[$url] as $i => $id) { ?>
									<?= $i > 0 ? ', ' : '' ?><a class="ajax" href="<?= $e($page($id)) ?>"><?= $e($labels[$id] ?? 'Untitled') ?></a>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
</section>
