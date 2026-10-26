<?php
/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Migration\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$report = $this->htmlReport;
$open = array_sum(array_map(static fn(array $entry): int => count($entry['open']), $report['entries']));
?>
<h1>Migration</h1>
<p class="tagsHint">Older page texts still store paths. This turns them into references.</p>
<?= \message::flush() ?>
<section class="widget is-list">
	<header>
		<h2>Convert html links and images</h2>
	</header>
	<main>
		<p>Images, videos and links in the text fields become references: <code>src="media:293?format=collection"</code>, <code>href="page:43"</code>. Content made with the current editor is already in this form.</p>
		<p><?= $report['rows'] ?> page rows, <b><?= $report['changed'] ?> to convert</b>, <?= $open ?> place(s) that look like this site but cannot be resolved (they stay as they are). Hosts taken as this site: <?= $e(implode(', ', $this->ownHosts)) ?>.</p>
	</main>
	<?php if ($report['entries'] !== []) { ?>
		<div class="tableScroll reportRows">
			<table>
				<thead><tr><th>Page</th><th>Field</th><th>Converts</th><th>Open</th></tr></thead>
				<tbody>
				<?php foreach ($report['entries'] as $entry) { ?>
					<tr>
						<td><a href="../../pages/?id=<?= $entry['node'] ?>&lang=<?= $e($entry['lang']) ?>"><?= $entry['node'] ?></a> <?= $e($entry['lang']) ?></td>
						<td><?= $e($entry['field']) ?></td>
						<td><?= $e(implode(', ', $entry['done'])) ?></td>
						<td><?= $e(implode(', ', $entry['open'])) ?></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
	<?php if ($report['changed'] > 0) { ?>
		<footer>
			<a class="button submit" data-on_click="userConfirm" data-action="convertHtmlRefs"
			   data-confirm="Rewrite <?= $report['changed'] ?> of <?= $report['rows'] ?> page rows? Images, videos and links become references (media:…, page:…).">Convert</a>
		</footer>
	<?php } ?>
</section>
