<?php
$imageSrc = http::post('image', FALSE);
$text = http::post('text', FALSE);
if ($text || $imageSrc) {
	?>
	<div class="cmslightbox cms" data-size="1">
	    <textarea class="olink"><?= http::params('olink') ?></textarea>
	    <section>
			<header>
				<h1>Insert Page Link</h1>
				<ul class="barmenu">
					<li>
						<a class="ajax" onclick='closeLinkLightbox()' href="../../"><i class="fa-light fa-xmark-large"></i></a>
					</li>
				</ul>
			</header>
			<main class="layout">
				<div class="halfColumn">
	<?php if ($text) { ?>
						<div class="inputGroup">
							<input name="linkTitle" required="" type="text" value="<?= $text ?>" placeholder=" ">
							<span class="bar"></span>
							<label>Link Title</label>
						</div>
						<br>
	<?php } ?>
					<?php if ($imageSrc) { ?>
						<img src="<?= $imageSrc ?>" style="max-width:100%;">
					<?php } ?>

					<div class="inputGroup">
						<input name="linkUrl" value='https://' required="" type="url" placeholder=" ">
						<span class="bar"></span>
						<label>URL</label>
					</div>
					<br>
					<div class="inputGroup">
						<input name="linkClass" type="text" placeholder=" ">
						<label>Link Class Names</label>
					</div>
				</div>
				<div class="halfColumn">
					<ul class="checkBoxList">
						<li>
							<label class="checkbox"><span>Download File</span><input type="checkbox" value='0'
																					 name='download'
																					 class="switch tinyswitch"/>
								<div>
									<div></div>
								</div>
							</label>
						</li>
						<li>
							<label class="checkbox"><span>Open in New Tab / Window</span><input type="checkbox"
																								value='0' name='newTab'
																								class=" switch tinyswitch"/>
								<div>
									<div></div>
								</div>
							</label>
						</li>
					</ul>
				</div>
			</main>
			<footer>
				<a class="submit button"
	<?php if ($text) { ?>onclick="insertHTMLeditorLink(), this.classList.add('loading')" <?php } ?> <?php if ($imageSrc) { ?> onclick="insertHTMLeditorImageLink()"<?php } ?>>Insert Link</a>
			</footer>
	</div>
	<?php
} else {
	http::redirect('../../');
}