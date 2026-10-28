<?php
/**
 * Upload, or replace the medium of ?node_id.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Upload\Panel $this
 */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$nodeId = $this->nodeId;
$row = $nodeId > 1 ? $this->parent->mediaOfNode($nodeId) : null;
$media = $row !== null ? (new \Systopic\System\Tables\Medias\Media($row))->setFormat('original') : null;
$replacement = $media !== null;
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
					<a class="ajax" href="../" aria-label="Close"><i class="fa-regular fa-xmark"></i></a>
				</li>
			</ul>
		</header>
		<div class="layout">
			<main class="uploadStage">
				<div class="uploadPane">
					<?php if ($replacement) { ?><p class="paneLabel">New file</p><?php } ?>
					<div data-doc="box" class="idle">
						<div class="uploader">
							<p class="dropHint"><i class="fa-regular fa-cloud-arrow-up"></i>Drop a file here, or click to choose</p>
							<input type="file" data-doc="input">
							<img data-doc="img" alt="">
							<iframe data-doc="pdf" title="Preview"></iframe>
							<video data-doc="video" controls muted playsinline></video>
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
									echo '<img alt="" src="' . $e($media->src) . '">';
									break;
								case 'application/pdf':
									echo '<iframe title="Current file" src="' . $e($media->src) . '"></iframe>';
									break;
								case 'video/mp4':
								case 'video/quicktime':
									echo '<video controls src="' . $e($media->src) . '"></video>';
									break;
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
								<summary>File</summary>
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
									echo \Systopic\System\Tables\Medias\External::fieldsHtml([
										'type' => (string) ($row->type ?? ''),
										'url' => (string) ($row->url ?? ''),
									]);
									?>
								</div>
							</details>
							<details class="sidebarSection" data-section="upload-details" open>
								<summary>Details</summary>
								<div class="sidebarItem">
									<dl class="cmsFacts" data-doc="info"></dl>
								</div>
							</details>
							<?php if ($replacement) { ?>
								<details class="sidebarSection" data-section="upload-current" open>
									<summary>Current file</summary>
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
								<details class="sidebarSection" data-section="upload-tags" open>
									<summary>Tags</summary>
									<div class="sidebarItem">
										<?php $this->withView('tags'); ?>
									</div>
								</details>
							<?php } ?>
						</div>
					</div>
				</div>
			</aside>
		</div>
		<footer data-doc="footer" class="idle">
			<button class="submit" data-doc="upload"><?= $replacement ? 'Replace' : 'Upload' ?></button>
			<div class="bar"><b data-doc="barinfo1"></b><div data-doc="bar"><b data-doc="barinfo2"></b></div></div>
		</footer>
	</section>
</div>
