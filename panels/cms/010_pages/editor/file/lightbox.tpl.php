<?php
/**
 * The file editor — the medium of `?target_id=<media node>` (Panel::$media).
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Editor\File\Panel $this
 */
$media = $this->media;
if ($media === NULL) {
	message::error("Media node {$this->targetId} has no file");
	http::redirect('../../');
	return;
}
$original = $media->setFormat('original');
$embed = $this->mediaRow ? \Systopic\System\Tables\Medias\External::embedOf($this->mediaRow) : null;
$split = $embed !== null || in_array($media->mimetype, ['image/svg+xml', 'image/svg', 'video/mp4'], true);
?>
<div class="cmslightbox cms fileEditor" data-size="3">
    <section>
		<header>
			<h1><?= htmlspecialchars($this->lightboxTitle, ENT_QUOTES, 'UTF-8') ?></h1>
			<ul class="barmenu">
				<li>
					<a href="<?= htmlspecialchars($original->src, ENT_QUOTES, 'UTF-8') ?>" target="_self" download title="Download original"><i class="fa-regular fa-arrow-down-to-line"></i></a>
				</li>
				<li>
					<a href="<?= htmlspecialchars($original->src, ENT_QUOTES, 'UTF-8') ?>" target="_blank" title="Open original"><i class="fa-regular fa-eye"></i></a>
				</li>
				<li>
					<a class="put" data-confirm="Do you really want to delete this File?" data-on_click="userConfirm" data-action="deleteMedia" title="Delete"><i class="fa-regular fa-trash"></i></a>
				</li>
				<li>
					<a class="ajax" href="../../" aria-label="Close"><i class="fa-regular fa-xmark"></i></a>
				</li>
			</ul>
		</header>
		<div class="layout">
			<main>
				<div class="fileStage<?= $split ? ' split' : '' ?>">
					<?php
					$isImage = in_array($media->mimetype, ['image/png', 'image/jpg', 'image/jpeg', 'image/gif', 'image/webp'], true);
					if ($embed !== null) { ?>
						<div class="filePane">
							<div class="fileFrame embedFrame">
								<iframe src="<?= htmlspecialchars($embed['embedSrc'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(\Systopic\System\Tables\Medias\External::label($embed['provider']), ENT_QUOTES, 'UTF-8') ?>" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
							</div>
						</div>
					<?php }
					if ($embed !== null && $isImage) {
						echo '<div class="filePane">';
						include 'image.tpl.php';
						echo '</div>';
					} else {
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
					}
					?>
				</div>
			</main>
			<?php include 'meta.tpl.php'; ?>
		</div>
    </section>
</div>
