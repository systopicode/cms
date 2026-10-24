<?php
/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Migration\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$report = $this->htmlReport;
$open = array_sum(array_map(static fn(array $entry): int => count($entry['open']), $report['entries']));
?>
<?= \message::flush() ?>
<h2>Convert html links and images</h2>
<p>
	Images, videos and links in the text fields become references:
	<code>src="media:293?format=collection"</code>, <code>href="page:43"</code> —
	no paths in the content any more. Content made with the current editor is
	already in this form; this is for content from before.
</p>
<p>
	<?= $report['rows'] ?> page rows, <b><?= $report['changed'] ?> to convert</b>,
	<?= $open ?> place(s) that look like this site but cannot be resolved (they stay as they are).
	Hosts taken as this site: <?= $e(implode(', ', $this->ownHosts)) ?>.
</p>
<?php if ($report['changed'] > 0) { ?>
	<?php // userConfirm, not confirmRequired(): the CMS has no confirmbox view to show a server-side question ?>
	<p><a class="button submit" data-on_click="userConfirm" data-action="convertHtmlRefs"
	      data-confirm="Rewrite <?= $report['changed'] ?> of <?= $report['rows'] ?> page rows? Images, videos and links become references (media:…, page:…).">convert html links and images</a></p>
<?php } ?>
<?php if ($report['entries'] !== []) { ?>
	<table class="keycolumn">
		<tr><th>page</th><th>field</th><th>converts</th><th>open</th></tr>
		<?php foreach ($report['entries'] as $entry) { ?>
			<tr>
				<td><a href="../../pages/?id=<?= $entry['node'] ?>&lang=<?= $e($entry['lang']) ?>"><?= $entry['node'] ?></a> <?= $e($entry['lang']) ?></td>
				<td><?= $e($entry['field']) ?></td>
				<td><?= $e(implode(', ', $entry['done'])) ?></td>
				<td><?= $e(implode(', ', $entry['open'])) ?></td>
			</tr>
		<?php } ?>
	</table>
<?php } ?>
