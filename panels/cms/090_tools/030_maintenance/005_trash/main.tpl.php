<?php
/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Trash\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<?= \message::flush() ?>
<h2>Trashed Pages</h2>
<table class="keycolumn">
	<tr><th>id</th><th>labels</th></tr>
	<?php foreach ($this->trashed as $nodeId => $entry) { ?>
		<tr>
			<td><a title="restore" data-on_click="restoreNode" data-node_id="<?= $nodeId ?>"><i class="fas fa-trash-restore-alt"></i></a> <?= $nodeId ?></td>
			<td>
				<?php foreach ($entry['pages'] as $i => $page) { ?>
					<?= $i ? ', ' : '' ?><?= $e($page->languageIso) ?>:<?= $e($page->label) ?>
					<a title="preview" href="../../../../?id=<?= $nodeId ?>&lang=<?= $e($page->languageIso) ?>&showtrashed" target="_blank"><i class="fas fa-eye"></i></a>
				<?php } ?>
			</td>
		</tr>
	<?php } ?>
</table>
