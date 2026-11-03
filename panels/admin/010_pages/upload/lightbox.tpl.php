<?php
/**
 * Upload, or replace the medium of ?node_id.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Upload\Panel $this
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$nodeId = $this->nodeId;
$row = $nodeId > 1 ? $this->parent->mediaOfNode($nodeId) : null;
$media = $row !== null ? (new \Systopic\System\Tables\Medias\Media($row))->setFormat('original') : null;
$replacement = $media !== null;
$group = $replacement ? (string) $row->group : (string) (http::get('group') ?: 'main');
$accept = \Systopic\System\Media\GroupPolicy::accept($this->parent->mediaGroups, $group);
$currentPath = $replacement ? \fs::toNative($media->path) : '';
$currentSize = ($currentPath !== '' && is_file($currentPath)) ? str::formatbytes($currentPath) : 'not on disk';
$currentFormat = $replacement && (int) $media->width > 0
	? (int) $media->width . ' × ' . (int) $media->height . ' px · ' . $currentSize
	: $currentSize;
?>
<div class="cmslightbox cms upload<?= $replacement ? ' replacement' : '' ?>" data-size="3">
	<section>
		<header>
			<h1><?= $replacement ? 'Replace file' : 'Upload file' ?></h1>
			<ul class="barmenu">
				<li>
					<a class="ajax" href="../" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a>
				</li>
			</ul>
		</header>
		<div class="layout">
			<main class="uploadStage">
        <?php if ($replacement): ?>
        <div class="cms message is-stay uploadNotice"><div class="info"><p class="message">Replacing this file updates its existing references. Review “Used on” before uploading.</p></div></div>
        <?php endif; ?>

				<div class="uploadPane">
                    <?php if ($replacement) { ?>
                        <p class="paneLabel">New file</p>
                    <?php } ?>
					<div data-doc="box" class="idle">
						<div class="uploader">
							<p class="dropHint"><i class="cmsIcon cmsIcon-cloud-arrow-up"></i>Drop a file here, or click to choose</p>
							<input type="file" accept="<?= $e($accept) ?>" data-doc="input">
							<img data-doc="img" alt="">
							<iframe data-doc="pdf" title="Preview"></iframe>
							<video data-doc="video" controls muted playsinline></video>
							<audio data-doc="audio" controls></audio>
							<div data-doc="skp" class="cmsSkpStage" hidden data-skp-module="<?= $e(\Systopic\Cms\Media\SkpViewer::moduleUrl()) ?>"></div>
                            <div class="filePreviewFallback" data-doc="fileFallback" data-icons="<?= $e(json_encode(\Systopic\System\Media\Presentation::ICONS)) ?>" hidden>
                                <i class="cmsIcon cmsIcon-file" data-doc="fileFallbackIcon" aria-hidden="true"></i>
                                <strong data-doc="fileFallbackName"></strong>
                                <p>Ready to upload. A browser preview is not available for this file type.</p>
                            </div>
							<p class="readingHint">Reading the file…</p>
						</div>
					</div>
				</div>
				<?php if ($replacement) { ?>
					<div class="uploadPane">
						<p class="paneLabel">Current file</p>
						<div class="uploader currentFile">
							<?php
							switch ($media->mimetype) {
								case 'image/jpeg':
								case 'image/svg+xml':
								case 'image/png':
								case 'image/webp':
                                case 'image/gif':
									echo '<img alt="" src="' . $e($media->src) . '">';
									break;
								case 'application/pdf':
									echo '<iframe title="Current file" src="' . $e($media->src) . '"></iframe>';
									break;
								case 'video/mp4':
								case 'video/quicktime':
									echo '<video controls src="' . $e($media->src) . '"></video>';
									break;
								case 'audio/mpeg':
								case 'audio/mp3':
                                case 'audio/mp4':
                                case 'audio/x-m4a':
									echo '<audio controls src="' . $e($media->src) . '"></audio>';
									break;
                                default:
                                    if (!str_contains((string) $media->mimetype, 'sketchup') && !str_ends_with(strtolower((string) $media->filename), '.skp')) {
                                        echo '<div class="filePreviewFallback"><i class="cmsIcon cmsIcon-' . $e(\Systopic\System\Media\Presentation::icon((string) $media->filename)) . '" aria-hidden="true"></i><strong>' . $e($media->filename) . '</strong><p>A browser preview is not available for this file type.</p><a href="' . $e($media->src) . '" download>Download file</a></div>';
                                    }
                            }
							$skpNow = str_contains((string) $media->mimetype, 'sketchup') || str_ends_with(strtolower((string) $media->filename), '.skp');
							if ($skpNow) {
								echo '<div class="cmsSkpStage" data-skp-src="' . $e($media->src) . '" data-skp-module="' . $e(\Systopic\Cms\Media\SkpViewer::moduleUrl()) . '"></div>';
							}
							?>
						</div>
					</div>
				<?php } ?>
			</main>
			<aside class="cms">
				<div class="flexContainer">
					<div class="scrollContainer">
						<div class="scrollContainerContent">
							<details class="sidebarSection" data-section="upload-file" open>
								<summary><span class="sidebarLabel">File</span><span class="sidebarTools"><i class="cmsIcon cmsIcon-chevron-down sidebarFold" aria-hidden="true"></i></span></summary>
								<div class="sidebarItem">
									<div class="inputGroup">
										<input data-doc="label" type="text" spellcheck="off" autocomplete="off">
										<span class="bar"></span>
										<label>Label</label>
									</div>
									<div class="inputGroup">
										<input data-doc="name" type="text" readonly spellcheck="off">
										<span class="bar"></span>
										<label>File name</label>
									</div>
									<?php
									$picked = strtolower((string) ($row->type ?? ''));
									$pickedFile = strtolower((string) ($row->filename ?? ''));
									if ($row && ($picked === '' || $picked === 'application')
										&& (str_contains(strtolower((string) ($row->mimetype ?? '')), 'sketchup') || str_ends_with($pickedFile, '.skp'))) {
										$picked = 'sketchup';
									}
									echo \Systopic\System\Tables\Medias\External::fieldsHtml([
										'type' => $picked,
										'url' => (string) ($row->url ?? ''),
									]);
									?>
								</div>
							</details>
							<?php if ($replacement) {
$usageScope = $this->parent->scope;
$usageNode = $nodeId;
$usageMedia = (int) $row->id;
$usageHref = $this->parent->href;
include dirname(__DIR__) . '/media-usage.tpl.php';
?><?php } ?>
<details class="sidebarSection" data-section="upload-details" open>
								<summary><span class="sidebarLabel">Details</span><span class="sidebarTools"><i class="cmsIcon cmsIcon-chevron-down sidebarFold" aria-hidden="true"></i></span></summary>
								<div class="sidebarItem">
									<dl class="cmsFacts" data-doc="info"></dl>
								</div>
							</details>

							<?php if ($replacement) { ?>
                                <details class="sidebarSection" data-section="upload-current" open>
									<summary><span class="sidebarLabel">Current file</span><span class="sidebarTools"><i class="cmsIcon cmsIcon-chevron-down sidebarFold" aria-hidden="true"></i></span></summary>
									<div class="sidebarItem">
										<dl class="cmsFacts">
											<div>
												<dt>Label</dt>
												<dd><?= $e($media->label) ?></dd>
											</div>
											<div>
												<dt>File name</dt>
												<dd><?= $e($media->filename) ?></dd>
											</div>
											<div>
												<dt>Format</dt>
												<dd><?= $e($currentFormat) ?></dd>
											</div>
											<div>
												<dt>Type</dt>
												<dd><?= $e($media->mimetype) ?></dd>
											</div>
										</dl>
									</div>
								</details>
							<?php } ?>
<details class="sidebarSection" data-section="upload-tags" open>
								<summary><span class="sidebarLabel">Tags</span><span class="sidebarTools"><i class="cmsIcon cmsIcon-chevron-down sidebarFold" aria-hidden="true"></i></span></summary>
								<div class="sidebarItem">
									<?php $this->withView('tags'); ?>
								</div>
							</details>
						</div>
					</div>
				</div>
			</aside>
		</div>
		<footer data-doc="footer" class="idle">
			<button class="submit" data-doc="upload"><i class="cmsIcon <?= $replacement ? 'cmsIcon-arrows-rotate' : 'cmsIcon-arrow-up-from-bracket' ?>"></i><?= $replacement ? 'Replace' : 'Upload' ?></button>
			<div class="bar"><b data-doc="barinfo1"></b><div data-doc="bar"><b data-doc="barinfo2"></b></div></div>
		</footer>
	</section>
</div>
