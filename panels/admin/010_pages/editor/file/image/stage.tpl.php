<?php
/**
 * The image editor of master and aspect ratios (sys.cropEditor) takes the
 * place of the file's stage - in the tab the address chose (Panel::$tab).
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Image\Panel $this
 */
if ($this->tab === 'files') {
	include __DIR__ . '/files.tpl.php';
	return;
}
$options = [
	'nodeId' => $this->targetId,
	'url' => $this->image()->href,
	'mode' => $this->tab,
	'ratio' => $this->shownFirst,
];
?>
<div class="fileFrame" data-crop-stage="<?= (int) $this->targetId ?>"></div>
<script>
(function(){
 const marker=document.querySelector('[data-crop-stage="<?= (int) $this->targetId ?>"]'),stage=marker?.closest('.fileStage');
 if(!stage || stage.querySelector('.cropEditor') || !window.sys || !sys.cropEditor)return;
 sys.cropEditor.open(Object.assign({stage:stage},<?= json_encode($options, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>));
})();
</script>
