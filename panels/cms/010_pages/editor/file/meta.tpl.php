<?php
/**
 * Label, file name, dates, size, mime — the aside of the file editor.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Editor\File\Panel $this
 * @var \Systopic\System\Tables\Medias\Media                      $original
 */
$row = $this->mediaRow;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$when = static fn(?DateTimeImmutable $d): string => $d?->format('d.m.Y H:i:s') ?? '';
$path = \fs::toNative($original->path);
?>
<div data-node_id=<?= $this->targetId ?>>
	<h3>Meta Data</h3>
	<div class="inputGroup">
		<input data-on_input=updateMediaLabel spellcheck=off type=text name=label value="<?= $e($row->label) ?>">
		<span class="bar"></span>
		<label>Media Label</label>
	</div>
	<br clear="all">
	<div class="inputGroup">
		<input data-on_input=updateFilename spellcheck="off" type=text name='filename' value="<?= $e($row->filename) ?>">
		<span class="bar"></span>
		<label>File Name</label>
	</div>
	<br><br>
	<b>Uploaded&nbsp;at&nbsp;</b><br><?= $when($row->createdDate) ?>
	<br><b>by&nbsp;</b><?= $e($row->createdUser?->fullname) ?>
	<br> <br>
	<b>Modified&nbsp;at&nbsp;</b><br><?= $when($row->modifiedDate) ?>
	<br><b>by&nbsp;</b><?= $e($row->modifiedUser?->fullname) ?>
	<br><br>
	<b>Original Format<br></b>
	<?= (int) $row->width ?> x <?= (int) $row->height ?>px<br>(<?= is_file($path) ? str::formatbytes($path) : '–' ?>)
	<br><br>
	<b>Mime Type</b><br><?= $e($row->mimetype) ?>
</div>
