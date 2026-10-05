<?php
/**
 * The file editor — the medium of `?target_id=<media node>` (Panel::$media).
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Editor\File\Panel $this
 */
$media = $this->media;
if ($media === NULL) {
	http::redirect('../../');
	return;
}
$original = $media->setFormat('original');
?>
<div class="cmslightbox cms fileEditor" data-size="3">
    <section>
		<header>
			<h1><?= $this->lightboxTitle ?>-Editor</h1>
			<ul class='barmenu'>
				<li>
					<a href="<?= $original->src ?>" target='_self' download title="Download Original"
					   ><i class="fa-light fa-arrow-down-to-line"></i></a>
				</li>
				<li>
					<a href="<?= $original->src ?>" target="_blank" title="Show Original"
					   ><i class="fa-light fa-eye"></i></a>
				</li>
				<li>
					<a class="ajax" href="../../"><i class="fa-light fa-xmark-large"></i></a>
				</li>
			</ul>
		</header>
		<div class='layout'>
			<main>
				<div>
					<?php
					switch ($media->mimetype) {
						case 'image/png':
						case 'image/jpg':
						case 'image/jpeg':
						case 'image/gif':
						case 'image/webp':
							include 'image.tpl.php';
							break;
						case 'image/svg':
						case 'image/svg+xml':
							include 'svg.tpl.php';
							break;
						case 'application/pdf':
							include 'pdf.tpl.php';
							break;
						case 'video/mp4':
							include 'video.tpl.php';
							break;
					}
					?>
				</div>
			</main>
			<aside>
				<div>
					<?php
					include 'meta.tpl.php';
					include 'permissions.php';
					?>
				</div>
			</aside>
		</div>
    </section>
</div>
