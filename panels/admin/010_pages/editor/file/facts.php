<?php
$when = static function (?DateTimeImmutable $d, ?string $who) use ($e): string {
	$stamp = \Systopic\System\Sys\LocalTime::format($d, \Systopic\System\Sys\LocalTime::DATETIME);
	$who = trim((string) $who);
	if ($stamp === '') {
		return '';
	}
	return $who !== '' ? $stamp . ' · ' . $e($who) : $e($stamp);
};
if (!$isImageEditor) {
	$path = \fs::toNative($original->path);
	$size = is_file($path) ? str::formatbytes($path) : 'not on disk';
	$format = ((int) $row->width > 0 && (int) $row->height > 0)
		? (int) $row->width . ' × ' . (int) $row->height . ' px · ' . $size
		: $size;
}
?>
<dl class="cmsFacts">
    <div><dt>Media ID</dt><dd><?= (int) $row->id ?></dd></div>
    <?php if (!$isImageEditor) { ?>
        <div><dt>File details</dt><dd>Dimensions and size below describe the original file. Generated image formats can have different dimensions and crops.</dd></div>
    <?php } ?>
    <?php if ($created = $when($row->createdDate, $row->createdUser?->fullname)) { ?>
        <div><dt>Created</dt><dd><?= $created ?></dd></div>
    <?php } ?>
    <?php if ($modified = $when($row->modifiedDate, $row->modifiedUser?->fullname)) { ?>
        <div><dt>Modified</dt><dd><?= $modified ?></dd></div>
    <?php } ?>
    <?php if (!$isImageEditor) { ?>
        <div><dt>Format</dt><dd><?= $e($format) ?></dd></div>
    <?php } ?>
    <div><dt>MIME-Type</dt><dd><?= $e($row->mimetype) ?></dd></div>
</dl>
