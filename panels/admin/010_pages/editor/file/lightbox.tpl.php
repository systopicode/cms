<?php
/**
 * The file editor's frame — the medium of `?target_id=<media node>`
 * (Panel::$media): shared tabs, the stage of its kind and file details. Rendered by
 * whichever panel the address selects - the file editor, a kind below it
 * or a tab of the image editor (their lightbox.tpl.php include this one).
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Panel $this
 */
$media = $this->media;
if ($media === NULL) {
	message::error("Media node {$this->targetId} has no file");
	http::redirect($this->pages()->href);
	return;
}
if (!$media->readable) {
	message::error('This file is not available.');
	http::redirect($this->pages()->href);
	return;
}
$original = $media->setFormat('original');
$embed = $this->mediaRow ? \Systopic\System\Tables\Medias\External::embedOf($this->mediaRow) : null;
if ($embed !== null) echo \fsLoader::includeScriptFiles(\fs::toInternal(dirname(__DIR__, 5) . '/public/site/embedConsent.js'));
$kind = $this->kind;
$kindPanel = $this->kindPanel();
$isImageEditor = $kind === 'image' && $kindPanel !== null;
$split = !$isImageEditor && ($embed !== null || $kind === 'svg');
?>
<div class="cmslightbox cms fileEditor" data-size="3" data-mobile-dialog="sheet" data-dialog-key="media-<?= (int) $this->targetId ?>">
    <section>
		<header class="imageEditorHeader">
			<h1><?= htmlspecialchars($this->lightboxTitle, ENT_QUOTES, 'UTF-8') ?></h1>
			<?= $isImageEditor ? $kindPanel->tabs() : $this->tabs() ?>
			<ul class="barmenu">
				<li>
					<a class="ajax" href="<?= htmlspecialchars($this->pages()->href, ENT_QUOTES, 'UTF-8') ?>" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a>
				</li>
			</ul>
		</header>
		<?php if (($isImageEditor && $kindPanel->tab === 'details') || (!$isImageEditor && $this->fileTab === 'details')) { ?>
		<?php include __DIR__ . '/details.tpl.php'; ?>
		<?php } elseif ($isImageEditor && $kindPanel->tab === 'files') { ?>
		<main class="mediaFiles"><?php $kindPanel->stage($media, $original); ?></main>
		<?php } else { ?>
		<div class="layout">
			<main>
				<div class="fileStage<?= $split ? ' split' : '' ?>">
					<?php
					if ($embed !== null) { ?>
						<div class="filePane">
							<div class="fileFrame embedFrame">
								<?= \Systopic\Cms\Media\EmbedConsent::html($this->mediaRow, ['scope' => 'view']) ?>
							</div>
						</div>
					<?php }
					if ($kindPanel !== NULL) {
						// image, video, svg: the stage of their panel
						echo $embed !== null ? '<div class="filePane">' : '';
						$kindPanel->stage($media, $original);
						echo $embed !== null ? '</div>' : '';
					} elseif ($embed === null || $kind !== 'file') {
						switch ($kind) {
							case 'gif':
								echo '<div class="fileFrame"><img src="' . htmlspecialchars($original->src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars((string) $media->label, ENT_QUOTES, 'UTF-8') . '"></div>';
								break;
							case 'audio':
								include __DIR__ . '/audio.tpl.php';
								break;
							case 'sketchup':
								include __DIR__ . '/skp.tpl.php';
								break;
							case 'pdf':
								include __DIR__ . '/pdf.tpl.php';
								break;
							case 'webm':
								echo '<div class="filePane"><video controls playsinline style="width:100%;max-height:70vh" src="' . htmlspecialchars($original->src, ENT_QUOTES, 'UTF-8') . '"></video></div>';
								break;
							default:
								echo '<div class="filePreviewFallback"><i class="cmsIcon cmsIcon-' . htmlspecialchars(\Systopic\System\Media\Presentation::icon((string) $media->filename), ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i><strong>' . htmlspecialchars((string) $media->filename, ENT_QUOTES, 'UTF-8') . '</strong><p>A browser preview is not available for this file type.</p><a href="' . htmlspecialchars($original->src, ENT_QUOTES, 'UTF-8') . '" download>Download file</a></div>';
								break;
						}
					}
					?>
				</div>
			</main>
		</div>
		<?php } ?>
    </section>
</div>
