<div class="inputGroup">
	<input aria-label="Label" data-on_blur="updateMediaLabel" spellcheck="off" type="text" name="label" value="<?= $e($row->label) ?>">
	<span class="bar"></span>
	<label>Label</label>
</div>
<div class="inputGroup">
	<input aria-label="File name, the ending stays" data-on_blur="updateFilename" spellcheck="off" type="text" name="filename" value="<?= $e($row->filename) ?>" autocomplete="off" enterkeyhint="done">
	<span class="bar"></span>
	<label>File name, the ending stays</label>
</div>
<?php
$type = strtolower((string) $row->type);
$file = strtolower((string) $row->filename);
if (($type === '' || $type === 'application')
	&& (str_contains(strtolower((string) $row->mimetype), 'sketchup') || str_ends_with($file, '.skp'))) {
	$type = 'sketchup';
}
echo \Systopic\System\Tables\Medias\External::fieldsHtml([
	'type' => $type,
	'url' => (string) $row->url,
	'autosave' => true,
]);
?>
