<?php
/**
 * SketchUp model (.skp). The same viewer the project documents use:
 * it reads the file in the browser and turns, zooms and pans it.
 *
 * @var \Systopic\System\Tables\Medias\Media $original
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$skpPath = \fs::toNative($original->path);
$module = \Systopic\Cms\Media\SkpViewer::moduleUrl();
?>
<?php if (!is_file($skpPath)) { ?>
	<p class="fileHint">The original file is not on disk.</p>
<?php } else { ?>
	<div class="fileFrame cmsSkpStage" data-skp-src="<?= $e($original->src) ?>" data-skp-module="<?= $e($module) ?>"></div>
<?php } ?>
