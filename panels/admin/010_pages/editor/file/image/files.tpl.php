<?php
/**
 * Every file of the picture (Media\VariantList), in place of the editor:
 * across the sizes, down the master and the ratios; below them the fixed
 * formats and the format names. Made ones are marked; each opens in a new
 * window - one not made yet is made then.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Image\Panel $this
 */
$set = \Systopic\System\Queries\MediaCrops\MediaCrops::one($this->pages()->scope, (int) $this->mediaRow->id);
$list = $set === NULL ? NULL : \Systopic\System\Media\VariantList::of($set);
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$bytes = static fn(int $n): string => $n <= 0 ? '' : ($n >= 1048576 ? number_format($n / 1048576, 1) . ' MB' : number_format($n / 1024, 0) . ' KB');
$pixels = static fn(array $file): string => $file['width'] > 0 ? $file['width'] . ' × ' . $file['height'] : '';
$title = static fn(array $file): string => trim($file['file'] . ' · ' . $pixels($file) . ($file['note'] !== '' ? ' (' . $file['note'] . ')' : '') . ' · ' . ($file['made'] ? 'made, ' . $bytes($file['bytes']) : 'made on its first request'), ' ·');
// One file: its name, the link; made ones marked.
$link = static fn(array $file): string => '<a href="' . $e($file['url']) . '" target="_blank" rel="noopener" title="' . $e($title($file)) . '">' . $e($file['name']) . '</a>';
$section = static function (string $heading, array $files) use ($e, $link, $pixels, $bytes): void {
	if ($files === []) {
		return;
	}
	echo '<h3>' . $e($heading) . '</h3><table class="fileVariantsList"><tbody>';
	foreach ($files as $file) {
		echo '<tr class="' . ($file['made'] ? 'is-made' : 'is-missing') . '"><td>' . $link($file) . '</td><td>' . $e($pixels($file)) . '</td><td>' . $e($file['note']) . '</td>'
			. '<td>' . ($file['made'] ? $e($bytes($file['bytes'])) : 'not yet') . '</td></tr>';
	}
	echo '</tbody></table>';
};
?>
<div class="fileFrame fileVariants">
	<?php if ($list === NULL) { ?>
		<p class="fileHint">This picture has no files of its own.</p>
	<?php } else { ?>
		<p class="fileHint">Original: <a href="<?= $e($list['original']['url']) ?>" target="_blank" rel="noopener"><?= $e($list['original']['name']) ?></a>
			<?= $list['original']['width'] > 0 ? $e($list['original']['width'] . ' × ' . $list['original']['height']) : '' ?><?= $list['original']['bytes'] > 0 ? ' · ' . $e($bytes($list['original']['bytes'])) : '' ?>
			- every file below is made from it. <?= $list['made'] ?> of <?= $list['all'] ?> made, marked green; the others are made on their first request, opening one makes it.</p>
		<table class="fileVariantsGrid">
			<thead>
				<tr>
					<th></th>
					<?php foreach ($list['sizes'] as $size) { ?><th><?= $e($size) ?> MP</th><?php } ?>
					<th>own pixels</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($list['grid'] as $row) { ?>
					<tr>
						<th><?= $e($row['title']) ?></th>
						<?php foreach ($row['cells'] as $cell) { ?>
							<?php if ($cell === NULL) { ?>
								<td></td>
							<?php } else { ?>
								<td class="<?= $cell['made'] ? 'is-made' : 'is-missing' ?><?= $cell['note'] !== '' ? ' is-capped' : '' ?>"><?= $link($cell) ?><small><?= $e($pixels($cell)) ?><?= $cell['note'] !== '' ? ' · own pixels' : '' ?></small></td>
							<?php } ?>
						<?php } ?>
					</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php
		$section('Fixed formats', $list['fixed']);
		$section('Format names', $list['names']);
		$section('Other files in the folder', $list['others']);
		?>
	<?php } ?>
</div>
