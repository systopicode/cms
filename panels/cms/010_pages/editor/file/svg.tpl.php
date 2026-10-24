<?php
/**
 * An svg: its preview and its source to edit (updateSVG).
 *
 * @var \Systopic\System\Tables\Medias\Media $original
 */
$path = \fs::toNative($original->path);
?>
<div class="fileFrame">
	<img id="svgPreviewImage" alt="" src="<?= htmlspecialchars($original->src, ENT_QUOTES, 'UTF-8') ?>">
</div>
<textarea class="fileSource ajax" name="svgContent" data-on_input="updateSVG" spellcheck="off"><?= htmlspecialchars(is_file($path) ? (string) file_get_contents($path) : '') ?></textarea>
