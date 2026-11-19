<?php
// The image editor of master and aspect ratios (sys.cropEditor) takes the place of the file's stage.
// The old editor of the named formats is gone: the names are aliases of a ratio and a size now.
?>
<div class="fileFrame" data-crop-stage="<?= (int) $this->targetId ?>"></div>
<script>
(function(){
 const marker=document.querySelector('[data-crop-stage="<?= (int) $this->targetId ?>"]'),stage=marker?.closest('.fileStage');
 if(!stage || stage.querySelector('.cropEditor') || !window.sys || !sys.cropEditor)return;
 sys.cropEditor.open({stage:stage,nodeId:<?= (int) $this->targetId ?>,url:<?= json_encode(rtrim((string) http::$root, '/') . '/' . ltrim($this->getPath(), '/')) ?>});
})();
</script>
