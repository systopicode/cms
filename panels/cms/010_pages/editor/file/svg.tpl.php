<?php
/**
 * An svg: its preview and its source to edit (updateSVG).
 *
 * @var \Systopic\System\Tables\Medias\Media $original
 */
$path = \fs::toNative($original->path);
?>
<div class='imageCropAlignContainer'>
	<div>
		<img id='svgPreviewImage' src="<?= $original->src ?>">
		<textarea style='margin:20px 0' name='svgContent' class='ajax' data-on_input='updateSVG'><?= htmlspecialchars(is_file($path) ? (string) file_get_contents($path) : '') ?></textarea>
	</div>
</div>
