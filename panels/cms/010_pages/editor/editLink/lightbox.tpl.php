<?php
// the image a link is set on (Tables\Pages\Media), FALSE for a text link
$imageRow = http::post('image_node_id', FALSE) ? $this->parent->parent->mediaOfNode((int) http::post('image_node_id')) : NULL;
$media = $imageRow !== NULL ? new \Systopic\Cms\Tables\Pages\Media($imageRow) : FALSE;
$text = http::post('text', FALSE);
if ($text || $media || $this->action) {
	$classNames = http::post('classNames') != 'undefined' ? http::post('classNames') : '';
	$href = http::post('href', '');
	$target = http::post('target', '') === '_blank' ? 'checked' : '';
	$download = http::post('download') != 'undefined' ? 'checked' : '';
} else {
	http::redirect('../../');
}
?>
<div class="cmslightbox cms" data-size="1">
    <textarea class="olink"><?= http::values('olink') ?></textarea>
    <section>
		<header>
			<?php if ($media) { ?>
				<h1>Edit Image Link</h1>
			<?php } else { ?>
				<h1>Edit Page Link</h1>
			<?php } ?>
			<ul class="barmenu">
				<li>
					<a class="ajax button transparent" onclick='closeLinkLightbox()' href="../../"><i class="fa-light fa-xmark-large"></i></a>
				</li>
			</ul>
		</header>
		<main class="layout">
			<div class="halfColumn">
				<?php if ($text) { ?>
					<div class="inputGroup">
						<input name="linkTitle" required="" value="<?= $text ?>" type="text" placeholder=" ">
						<span class="bar"></span>
						<label>Link Title</label>
					</div>
				<?php } ?>
				<?php if ($media) { ?>
					<div class="inputGroup">
						<select name="imageFormat" data-on_change="selectImageFormat">
							<option value=""></option>
							<?php
							// global from settings.php - out of scope inside PanelNode::render()
							$image_formats = mediaFormats::get();
							$i = 0;
							foreach (array_reverse($image_formats) as $key => $image_format) {
								if ($image_format['cms_display'] != 'none') {
									$value = http::$hostUrl.$media->setFormat($key)->src;
									$i++;
									if ($i == 1 && empty($selectedFormat)) {
										$selectedFormat = $key;
									}
									if (isset($image_format['width'])) {
										$format_info = $image_format['width'] . ' x ' . $image_format['height'] . 'px';
									} elseif (isset($image_format['autocrop'])) {
										$autocrop = $image_format["autocrop_limit"];
										$format_info = 'Orientation: ' . $image_format['autocrop'] . '&nbsp&nbsp;&mdash;&nbsp;&nbsp;Aspect Ratio: ' . $autocrop;
									} else {
										$format_info = 'Megapixels: ' . settype($image_format['megapixels'], "string");
									}
									?>
									<option value="<?= $value ?>"><?= $key ?> (<?= $format_info ?>)</option>
									<?php
								}
							}
							?>
						</select>
						<span class="bar"></span>
						<label>Link to Image Format</label>
					</div>
				<?php } ?>
				<br>
				<div class="inputGroup">
					<input name="linkUrl" required="" value="<?= $href ?>" type="url" placeholder=" ">
					<span class="bar"></span>
					<label>URL</label>
				</div>
				<br>
				<div class="inputGroup">
					<input name="linkClass" type="text" value="<?= $classNames ?>" placeholder=" ">
					<label>Link Class Names</label>
				</div>
			</div>
			<div class="halfColumn">
				<ul class="checkBoxList">
					<li>
						<label class="checkbox"><span>Download File</span><input type="checkbox"  value='0' <?= $download ?> name='download' class="switch tinyswitch"  /><div><div></div></div></label>
					</li>
					<li>
						<label class="checkbox"><span>Open in New Tab / Window</span><input type="checkbox"  <?= $target ?> value='0' name='newTab' class=" switch tinyswitch" /><div><div></div></div></label>
					</li>
				</ul>
			</div>
		</main>
		<footer>
			<a class="submit button" <?php if ($text) { ?>onclick="insertHTMLeditorLink(), this.classList.add('loading')" <?php } ?> <?php if ($media) { ?> onclick="insertHTMLeditorImageLink(),this.classList.add('loading')"<?php } ?>>Update Link</a>
		</footer>
    </section>
</div>