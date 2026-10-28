<?php
/**
 * @var \Systopic\System\Tables\Medias\Media $original
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$audioPath = \fs::toNative($original->path);
?>
<?php if (!is_file($audioPath)) { ?>
	<p class="fileHint">The original file is not on disk.</p>
<?php } else { ?>
	<div class="fileFrame">
		<audio controls preload="metadata" src="<?= $e($original->src) ?>"></audio>
	</div>
<?php } ?>
