<?php
$replacement = http::get('node_id', FALSE);
if ($replacement) {
	$row = $this->parent->mediaOfNode((int) $replacement);
	$media = $row !== NULL ? (new \Systopic\Cms\Tables\Pages\Media($row))->setFormat('original') : NULL;
	$replacement = $media !== NULL ? $replacement : FALSE;
}
?>
<div class="cmslightbox cms upload <?php
if ($replacement) {
	echo 'replacement';
}
?>" data-size="3">
    <section>
		<header>
			<?php if ($replacement) { ?>
				<h1>Replace File</h1>
			<?php } else {
				?>
				<h1>File upload</h1>
			<?php } ?>

			<ul class="barmenu">
				<li>
					<a class="ajax" href="../"><i class="fa-light fa-xmark-large"></i></a>
				</li>
			</ul>
		</header>
		<div class='layout'>
			<main class='cmslightboxContent'>
				<div data-doc=box class=idle> 
					<?php if ($replacement) { ?>
						<h3>New File</h3>
					<?php } ?>
					<div class=uploader><input type=file data-doc=input /><img data-doc=img><iframe data-doc="pdf" ></iframe><video loop controls='off' muted autoplay="" playsinline data-doc="video" ></video></div>
				</div>
				<?php if ($replacement) { ?>
					<div> 
						<h3>Old File</h3>
						<div class=uploader>
							<?php
							switch ($media->mimetype) {
								case 'image/jpeg':
								case 'image/svg+xml':
								case 'image/png':
								case 'image/webp':
									echo "<img src='{$media->src}' />";
									break;
								case 'application/pdf':
									echo "<iframe src='{$media->src}'></iframe>";
									break;
								case 'video/mp4':
								case 'video/quicktime':
									echo "<video controls src='{$media->src}'></video>";
									break;
							}
							?>
						</div>
					</div>
				<?php } ?>
			</main>
			<aside>
				<div>
					<div>
						<?php if ($replacement) { ?>
							<h3>New Meta Data</h3>
						<?php } else {
							?>
							<h3>Meta Data</h3>
						<?php } ?>
						<div class="inputGroup">
							<input data-doc=label type='text' spellcheck="off">
							<span class="bar"></span>
							<label>Media Label</label>
						</div>
						<br clear='all'>
						<div class="inputGroup">
							<input data-doc=name type='text' readonly spellcheck="off">
							<span class="bar"></span>
							<label>File Name</label>
						</div>
						<br clear='all'>
						<p data-doc=info></p>
					</div>
					<?php if ($replacement) { ?>
						<div>
							<h3>Old Meta Data</h3>
							<div class="inputGroup">
								<input spellcheck=off type=text value="<?= $media->label ?>">
								<span class="bar"></span>
								<label>Media Label</label>
							</div>
							<br clear="all">
							<div class="inputGroup">
								<input readonly spellcheck="off" type=text value="<?= $media->filename ?>">
								<span class="bar"></span>
								<label>File Name</label>
							</div>
							<br>
							<b>Original Format<br></b>
							<?= $media->width ?> x <?= $media->height ?>px<br>(<?= str::formatbytes(\fs::toNative($media->path)); ?>)
							<br><br>
							<b>Mime Type</b><br><?= $media->mimetype ?>
						</div>
					<?php } ?>
				</div>
			</aside>
		</div>

		<footer data-doc='footer' class='idle' >
			<button class=submit data-doc=upload><?php if ($replacement) { ?>Replace<?php } else { ?>Upload<?php } ?></button>
			<div class=bar><b data-doc=barinfo1></b><div data-doc=bar><b data-doc=barinfo2></b></div></div>
        </footer>
	</section>

	<style>

		body .cmslightbox.upload > section > div.layout > main > div{
			overflow: hidden;
			padding-right: 0px;
		}

		body .cmslightbox.upload > section > div.layout > main{
			display: flex;
			flex-direction: row;
		}

		div.cmslightbox.upload.replacement div.uploader{
			height:97%;
		}

		body .cmslightbox.upload > section > div.layout > main h3{
			margin-bottom: 5px;
		}

		div.cmslightbox.upload.replacement div[data-doc="box"] + div img,
		div.cmslightbox.upload.replacement div[data-doc="box"] + div iframe,
		div.cmslightbox.upload.replacement div[data-doc="box"] + div video{
			display: block;
		}

		div.cmslightbox.upload h3[contenteditable="true"]:after{
			content: '';
		}

		div.cmslightbox.upload footer .bar{
			width:100%;
			margin:0;
			height:33px;
		}

		div.cmslightbox.upload footer div.bar b{
			top:3px;
		}

		div.cmslightbox.upload .idle .bar,
		div.cmslightbox.upload .preview .bar,
		div.cmslightbox.upload .idle [data-doc=upload],
		div.cmslightbox.upload .reading [data-doc=upload]{
			opacity:0;
		}

		div.cmslightbox.upload div.uploader {
			position: relative;
			border: 2px dashed gray;
			height: 100%;
			border-radius:8px;
			overflow:hidden;
		}

		div.cmslightbox.upload .idle  div.uploader:before,
		div.cmslightbox.upload .idle  div.uploader:after{
			content:'Click to choose file or drop';
			text-align: center;
			text-transform: uppercase;
			font-weight: 700;
			font-size: 25px;
			padding:20px;
			align-items: center;
			justify-content: center;
			display:flex;
			height:100%;
			position: absolute;
			top:0;
			left:0;
			right:0;
		}

		div.cmslightbox.upload .idle  div.uploader:after{
			content:'\f15b';
			font-size: 100px;
			top:-150px;
			font-family: "CMS Font Awesome 6 Pro";
		}

		div.cmslightbox.upload div.uploader img,
		div.cmslightbox.upload div.uploader iframe,
		div.cmslightbox.upload div.uploader video{
			object-fit: contain;
			width: 100%;
			height: 100%;
			position: absolute;
			border:none;
			outline:0;
			display:none;
		}

		div.cmslightbox.upload div.uploader input{
			position:absolute;
			top:0;
			bottom:0;
			margin:0;
			opacity:0;
			z-index: 1;
			cursor: pointer;
			border:none;
		}

	</style>
</div>
