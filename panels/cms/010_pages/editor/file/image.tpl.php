<div class="fileToolbar fileImageTools">
	<select aria-label="Image format" data-node_id="<?= $this->targetId ?>" data-on_change="changeImageFormat" data-feedback="none" name="imageEditorFormat" class="put">
		<?php
		// settings.php sets $image_formats at global scope, but panel files are
		// included inside PanelNode::render()/runActions() - a method scope, where
		// the global does not reach. Same source, read locally.
		$image_formats = mediaFormats::get();
		// Configured order, minus the formats the system hides.
		$visibleFormats = array_filter(
			$image_formats,
			fn($format) => ($format['cms_display'] ?? '') !== 'none'
		);
		// Which one is marked, decided BEFORE the options are printed: an explicit
		// ?format= wins, then the choice the last change stored on this panel,
		// then the first of the list. Deciding inside the loop also marked the
		// first entry, and knew no choice that did not come from the url - so
		// every re-render showed the first format while the image kept the one
		// the user picked.
		$selectedFormat = (string) http::get('format', $this->state->format ?? '');
		if (!isset($visibleFormats[$selectedFormat])) {
			$selectedFormat = (string) array_key_first($visibleFormats);
		}
		foreach ($visibleFormats as $key => $image_format) {
			$format_info = mediaFormats::sizeLabel($image_format);
			?>
			<option value="<?= $key ?>"<?= $selectedFormat === $key ? ' selected' : '' ?>><?= htmlspecialchars(mediaFormats::label((string) $key, $image_format), ENT_QUOTES, 'UTF-8') ?> (<?= $format_info ?>)</option>
			<?php
		}
		?>
	</select>
<button type="button" class="button submit" data-edit-image aria-label="Edit image"><i class="cmsIcon cmsIcon-pen"></i>Edit</button>
</div>
<div class="fileFrame imageCropAlignContainer">
	<div class="frameView" hidden>
		<img class="srcToChange" alt="" src="<?= htmlspecialchars($media->setFormat($selectedFormat)->src, ENT_QUOTES, 'UTF-8') ?>">
	</div>
<div class="frameZoom" aria-label="Preview zoom">
 <button type="button" class="button" data-preview-zoom="out" aria-label="Zoom out"><i class="cmsIcon cmsIcon-minus"></i></button>
 <button type="button" class="button" data-preview-zoom="fit" aria-label="Fit image"><i class="cmsIcon cmsIcon-expand"></i></button>
 <button type="button" class="button" data-preview-zoom="in" aria-label="Zoom in"><i class="cmsIcon cmsIcon-plus"></i></button>
</div>
</div>
<script>
(function(){
 const img=document.querySelector('img.srcToChange'),view=img?.closest('.frameView'),stage=img?.closest('.fileStage');
 if(!img || !view || !stage)return;
 const formats=stage.querySelector('select[name="imageEditorFormat"]');
 let zoom=1,pan={x:0,y:0},drag=null,pinch=null;
 const frame=view.parentElement;
 function fit(){
  if(!img.naturalWidth || !img.naturalHeight){view.hidden=true;return;}
  view.hidden=false;
  const scale=Math.min((frame.clientWidth-24)/img.naturalWidth,(frame.clientHeight-24)/img.naturalHeight,1);
  view.style.width=Math.max(1,Math.round(img.naturalWidth*scale))+'px';view.style.height=Math.max(1,Math.round(img.naturalHeight*scale))+'px';
  view.style.transform=`translate(${pan.x}px,${pan.y}px) scale(${zoom})`;
 }
 const reset=()=>{zoom=1;pan={x:0,y:0};fit();};
 img.addEventListener('load',reset);img.addEventListener('cropend',reset);img.addEventListener('error',()=>view.hidden=true);
 if(img.complete)fit();new ResizeObserver(fit).observe(view.parentElement);
 function edit(){
  const r=img.getBoundingClientRect();
  runImageEditor({unified:true,domObj:$(img),src:img.src,display_width:r.width,display_height:r.height,offset_left:r.left+scrollX,offset_top:r.top+scrollY,
   targetId:<?= (int) $this->targetId ?>,saveUrl:<?= json_encode(rtrim((string) http::$root, '/') . '/' . ltrim($this->getPath(), '/')) ?>,
   formats:[...formats.options].map(o=>({key:o.value,label:o.textContent}))});
 }
 stage.querySelector('[data-edit-image]').addEventListener('click',edit);
 frame.querySelectorAll('[data-preview-zoom]').forEach(button=>button.addEventListener('click',()=>{if(button.dataset.previewZoom==='fit')reset();else{zoom=Math.max(.25,Math.min(8,zoom*(button.dataset.previewZoom==='in'?1.25:.8)));fit();}}));
 frame.addEventListener('wheel',event=>{event.preventDefault();zoom=Math.max(.25,Math.min(8,zoom*Math.exp(-Math.max(-100,Math.min(100,event.deltaY))*.002)));fit();},{passive:false});
 view.addEventListener('pointerdown',event=>{if(event.button!==0 || (view.offsetWidth*zoom<=frame.clientWidth && view.offsetHeight*zoom<=frame.clientHeight))return;event.preventDefault();view.setPointerCapture(event.pointerId);drag={id:event.pointerId,x:event.clientX,y:event.clientY,pan:{...pan}};});
 view.addEventListener('pointermove',event=>{if(!drag||pinch||drag.id!==event.pointerId)return;pan={x:drag.pan.x+event.clientX-drag.x,y:drag.pan.y+event.clientY-drag.y};fit();});
 const end=()=>{drag=null;};view.addEventListener('pointerup',end);view.addEventListener('pointercancel',end);
 frame.addEventListener('touchstart',event=>{if(event.touches.length!==2)return;event.preventDefault();drag=null;const [a,b]=event.touches;pinch={distance:Math.hypot(a.clientX-b.clientX,a.clientY-b.clientY),zoom};},{passive:false});
 frame.addEventListener('touchmove',event=>{if(!pinch||event.touches.length!==2)return;event.preventDefault();const [a,b]=event.touches;zoom=Math.max(.25,Math.min(8,pinch.zoom*Math.hypot(a.clientX-b.clientX,a.clientY-b.clientY)/Math.max(1,pinch.distance)));fit();},{passive:false});
 frame.addEventListener('touchend',()=>pinch=null);frame.addEventListener('touchcancel',()=>pinch=null);

})();
</script>
