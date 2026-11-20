<?php
/**
 * The file editor's frame — the medium of `?target_id=<media node>`
 * (Panel::$media): header, the stage of its kind, the sidebar. Rendered by
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
$original = $media->setFormat('original');
$embed = $this->mediaRow ? \Systopic\System\Tables\Medias\External::embedOf($this->mediaRow) : null;
if ($embed !== null) echo \fsLoader::includeScriptFiles(\fs::toInternal(dirname(__DIR__, 5) . '/public/site/embedConsent.js'));
$kind = $this->kind;
$kindPanel = $this->kindPanel();
$split = $embed !== null || in_array($kind, ['svg', 'video'], true);
?>
<div class="cmslightbox cms cmsNestedDialog fileEditor" data-size="3">
    <section>
		<header>
			<h1><?= htmlspecialchars($this->lightboxTitle, ENT_QUOTES, 'UTF-8') ?></h1>
			<?= $kindPanel?->tabs() ?? '' ?>
			<ul class="barmenu">
				<li>
					<a href="<?= htmlspecialchars($original->src, ENT_QUOTES, 'UTF-8') ?>" target="_self" download title="Download original"><i class="cmsIcon cmsIcon-arrow-down-to-line"></i></a>
				</li>
				<li>
					<a href="<?= htmlspecialchars($original->src, ENT_QUOTES, 'UTF-8') ?>" target="_blank" title="Open original"><i class="cmsIcon cmsIcon-eye"></i></a>
				</li>
				<li>
					<button type="button" data-cms-action class="put" data-confirm="<?= htmlspecialchars('Delete this file? ' . count(\Systopic\Cms\Queries\MediaUsage\MediaUsage::of($this->pages()->scope, (int) $this->targetId, (int) $this->mediaRow->id)) . ' page references found. Deleting may break these references.', ENT_QUOTES) ?>" data-on_click="userConfirm" data-action="deleteMedia" title="Delete"><i class="cmsIcon cmsIcon-trash"></i></button>
				</li>
				<li>
					<a class="ajax" href="<?= htmlspecialchars($this->pages()->href, ENT_QUOTES, 'UTF-8') ?>" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a>
				</li>
			</ul>
		</header>
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
			<?php include __DIR__ . '/meta.tpl.php'; ?>
		</div>
    </section>
</div>
