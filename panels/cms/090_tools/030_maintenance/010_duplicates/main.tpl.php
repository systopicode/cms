<?php
/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Duplicates\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$groups = static function (array $rows, callable $key): array {
	$by = [];
	foreach ($rows as $row) {
		$by[$key($row)][] = $row;
	}
	return $by;
};
?>
<?= \message::flush() ?>
<h2>Duplicate Pages</h2>
<table class="keycolumn">
	<tr><th>page</th><th>count</th><th>labels</th></tr>
	<?php foreach ($groups($this->pages, static fn($p): string => $p->relationId('node') . ':' . ($p->languageIso ?? 'NULL')) as $key => $copies) { ?>
		<tr>
			<td><?= $e($key) ?></td>
			<td><?= count($copies) ?></td>
			<td>
				<?php foreach ($copies as $page) { ?>
					<?= $e($page->label) ?><a data-on_click="deletePage" data-page_id="<?= $page->id ?>" title="page:<?= $page->id ?>"> <i class="far fa-trash"></i></a>
				<?php } ?>
			</td>
		</tr>
	<?php } ?>
</table>
<h2>Duplicate Crops</h2>
<table class="keycolumn">
	<tr><th>crop</th><th>count</th><th>labels</th></tr>
	<?php foreach ($groups($this->crops, static fn($c): string => $c->relationId('media') . ':' . ($c->format ?? 'NULL')) as $key => $copies) { ?>
		<tr>
			<td><?= $e($key) ?></td>
			<td><?= count($copies) ?></td>
			<td>
				<?php foreach ($copies as $crop) { ?>
					<?= $e($crop->media?->label) ?><a data-on_click="deleteCrop" data-crop_id="<?= $crop->id ?>" title="crop:<?= $crop->id ?>"> <i class="far fa-trash"></i></a>
				<?php } ?>
			</td>
		</tr>
	<?php } ?>
</table>
