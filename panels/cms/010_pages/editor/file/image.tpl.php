<div class="inputGroup" style="width:calc(100% - 53px); display:inline-block; margin-bottom: 10px; padding-top:0;">	
	<select data-node_id="<?= $this->targetId ?>" data-on_change="changeImageFormat" name="imageEditorFormat" class="put">
		<?php
		// settings.php sets $image_formats at global scope, but panel files are
		// included inside PanelNode::render()/runActions() - a method scope, where
		// the global does not reach. Same source, read locally.
		$image_formats = mediaFormats::get();
		// What the select offers: reversed, minus the formats the cms hides.
		$visibleFormats = array_filter(
			array_reverse($image_formats),
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
			if (isset($image_format['width'])) {
				$format_info = $image_format['width'] . ' x ' . $image_format['height'] . 'px';
			} elseif (isset($image_format['autocrop'])) {
				$autocrop = $image_format["autocrop_limit"];
				$format_info = 'Orientation: ' . $image_format['autocrop'] . '&nbsp&nbsp;&mdash;&nbsp;&nbsp;Aspect Ratio: ' . $autocrop;
			} else {
				// settype() returns TRUE, not the value - this read 'Megapixels: 1'
				// for every format that is sized by megapixels.
				$format_info = 'Megapixels: ' . $image_format['megapixels'];
			}
			?>
			<option value="<?= $key ?>"<?= $selectedFormat === $key ? ' selected' : '' ?>><?= $key ?> (<?= $format_info ?>)</option>
			<?php
		}
		?>
	</select>
	<span class="bar"></span>
	<label></label>
</div>
<a style="margin:10px 0;
   transform: translatey(-1px);
   height: 37px;" class="button submit icononly startImageCroping" data-on_click="startCroping" title="Crop Image"><i class="fa-light fa-crop-simple"></i></a>
<div class='imageCropAlignContainer'>
	<div>
		<br>
		<img class="srcToChange" src="<?= $media->setFormat($selectedFormat)->src ?>">
	</div>
</div>
