<?php
/**
 * The link dialog of the WYSIWYG fields: a new or an existing link on text or
 * on an image. The target comes from one of three tabs - an address typed in,
 * a page found by its title, a file of the page's media library. The field
 * itself is changed in the browser (applyHTMLeditorLink).
 *
 * @var \Systopic\System\Panels\PanelNode $this  cms/pages/editor/editLink
 */
if (http::$method !== 'POST' && !$this->action) {
	http::redirect('../../');
}
/** @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $pages */
$pages = $this->parent->parent;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
// the image a link is set on (Tables\Medias\Media), FALSE for a text link
$imageRow = http::post('image_node_id', FALSE) ? $pages->mediaOfNode((int) http::post('image_node_id')) : NULL;
$media = $imageRow !== NULL ? new \Systopic\System\Tables\Medias\Media($imageRow) : FALSE;
$text = (string) http::post('text', '');
$href = preg_replace('~^(media|page)::(?=\d)~', '$1:', (string) http::post('href', ''));
$classNames = (string) http::post('classNames', '');
$newTab = $href === '' || http::post('target', '') === '_blank';
$download = http::post('download') ? 'checked' : '';
$types = [
	'url' => ['Web', 'https://example.com or /page/'],
	'mailto' => ['Email', 'name@example.com'],
	'tel' => ['Phone', '+49 30 1234567'],
	'sms' => ['SMS', '+49 170 1234567'],
];
$type = preg_match('/^(mailto|tel|sms):/i', $href, $m) ? strtolower($m[1]) : 'url';
$address = $type === 'url' ? $href : rawurldecode(substr($href, strlen($type) + 1));
$title = ($href === '' ? 'Insert ' : 'Edit ') . ($media ? 'Image Link' : 'Link');
$pageUrl = http::$siteUrl . 'cms/pages/';
$returnUrl = (string) http::post('return_url', $this->state->returnUrl ?? $pageUrl);
if (parse_url($returnUrl, PHP_URL_HOST) !== null || parse_url($returnUrl, PHP_URL_SCHEME) !== null || parse_url($returnUrl, PHP_URL_PATH) !== parse_url($pageUrl, PHP_URL_PATH)) $returnUrl = $pageUrl;
$this->state->returnUrl = $returnUrl;


// the files of the page: the originals, as the media library lists them
$files = $thumbs = $fileHrefs = $faces = [];
foreach ($pages->library as $entries) {
	foreach ($entries as $entry) {
		if ($entry['media'] === NULL || $entry['alias'] || \Systopic\System\Tables\Medias\External::is((string) $entry['media']->type)) {
			continue;
		}
		$files[] = new \Systopic\System\Tables\Medias\Media($entry['media']);
		$face = $pages->tileFace($entry['media']);
        $faces[] = $face;
        $thumbs[] = $face['mark'] ? '' : $pages->thumbOf($entry['media']);
		$fileHrefs[] = 'media:' . (int) $entry['node']->id;
	}
}
// a page or a file is stored as a reference (page:43, media:85), so it survives a rename
$linkedPage = preg_match('~^(?:\?id=|page:)(\d+)~', $href, $m) ? $pages->pageFor((int) $m[1]) : NULL;
$tab = in_array($href, $fileHrefs, TRUE) ? 'file' : ($linkedPage !== NULL ? 'page' : 'address');
$tabs = ['address' => 'Address', 'page' => 'Page', 'file' => 'File'];
?>
<div class="cmslightbox cms" data-size="1" data-existing-link="<?= $href !== '' ? 'true' : 'false' ?>">
	<section>
		<header>
			<h1><?= $title ?></h1>
			<ul class="barmenu">
				<li>
					<a class="ajax" onclick="event.preventDefault(); event.stopImmediatePropagation(); wysiwyg.cancelLink(); sys.core.server.sendCall({method: 'get', history: false}, this.href); return false;" data-history="false" href="<?= $e($returnUrl) ?>" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a>
				</li>
			</ul>
		</header>
		<main class="linkDialog" onkeydown="if (event.key === 'Enter' && !event.shiftKey && !event.target.matches('select, [type=search]') && !event.target.closest('[role=tab]')) { event.preventDefault(); this.closest('section').querySelector('footer button[data-cms-action]').click(); }">
			<div class="fieldGrid is-stack">
				<?php if ($media) { ?>
					<label class="field">
						<span>Image format</span>
						<select name="imageFormat" onchange="linkFormatChosen(this)">
							<option value="">Link somewhere else</option>
							<?php
							foreach (mediaFormats::get() as $key => $format) {
								if (($format['cms_display'] ?? '') === 'none') {
									continue;
								}
								$size = \mediaFormats::sizeLabel($format);
								echo '<option value="media:' . (int) http::post('image_node_id') . '?format=' . $e($key) . '">' . $e(\mediaFormats::label((string) $key, $format) . ' (' . $size . ')') . '</option>';
							}
							?>
						</select>
						<small>Links the image to a larger version of itself.</small>
					</label>
				<?php } else { ?>
					<label class="field">
						<span>Text</span>
						<textarea name="linkTitle" rows="2" placeholder="What the reader clicks"><?= $e($text) ?></textarea>
					</label>
				<?php } ?>
				<div class="field linkTarget">
					<span>Link to</span>
					<nav class="linkTabs" role="tablist">
						<?php foreach ($tabs as $key => $label) { ?>
							<button type="button" role="tab" data-tab="<?= $key ?>" aria-selected="<?= $key === $tab ? 'true' : 'false' ?>" onclick="linkTab(this)"><?= $label ?></button>
						<?php } ?>
					</nav>
					<div class="linkPane linkTo" data-pane="address" <?= $tab === 'address' ? '' : 'hidden' ?>>
						<select name="linkType" aria-label="Type" onchange="linkTypeChanged(this)">
							<?php foreach ($types as $key => [$label, $hint]) { ?>
								<option value="<?= $key ?>" data-hint="<?= $e($hint) ?>" <?= $key === $type ? 'selected' : '' ?>><?= $label ?></option>
							<?php } ?>
						</select>
						<textarea aria-label="Link address" name="linkUrl" rows="2" autocapitalize="off" autocorrect="off" spellcheck="false" placeholder="<?= $e($types[$type][1]) ?>" autofocus><?= $e($address) ?></textarea>
					</div>
					<div class="linkPane" data-pane="page" <?= $tab === 'page' ? '' : 'hidden' ?>>
						<input type="search" name="search" data-on_input="<?= $e($pages->href . 'editor/editLink/searchLinkPages') ?>" placeholder="Search pages by title" aria-label="Search pages" autocomplete="off">
						<ul class="linkPages">
							<?php if ($linkedPage !== NULL) {
								$label = $linkedPage->label->plain;
								$path = $linkedPage->rewritepaths->path . $linkedPage->rewritepaths->appendix; ?>
								<li><button type="button" class="selected" data-href="page:<?= (int) $linkedPage->id ?>?lang=<?= $e($pages->params->lang) ?>" data-label="<?= $e($label) ?>" onclick="linkPicked(this)"><b><?= $e($label) ?></b><small>/<?= $e($path) ?></small></button></li>
							<?php } else { ?>
								<li class="linkHint">Search pages by title.</li>
							<?php } ?>
						</ul>
					</div>
					<div class="linkPane" data-pane="file" <?= $tab === 'file' ? '' : 'hidden' ?>>
						<?php if ($files === []) { ?>
							<p class="linkHint">The media library of this page is empty. Upload a file there first.</p>
						<?php } else { ?>
							<ul class="linkFiles">
								<?php foreach ($files as $i => $file) {
									$icon = $faces[$i]['icon'];
									$fileLabel = trim((string) $file->label) !== '' ? $file->label : $file->filename;
									?>
									<li>
										<button type="button" class="<?= $fileHrefs[$i] === $href ? 'selected' : '' ?>" data-file-inline="<?= \Systopic\System\Media\Presentation::opensInline((string) $file->mimetype) ? '1' : '0' ?>" data-href="<?= $e($fileHrefs[$i]) ?>" data-label="<?= $e($fileLabel) ?>" onclick="linkPicked(this)" title="<?= $e($fileLabel) ?>">
											<i class="cmsIcon cmsIcon-<?= $icon ?>"></i>
											<?php if ($thumbs[$i] !== '') { ?><img src="<?= $e($thumbs[$i]) ?>" alt="" loading="lazy" onerror="this.remove()"><?php } ?>
											<span><?= $e($fileLabel) ?></span>
										</button>
									</li>
								<?php } ?>
							</ul>
						<?php } ?>
					</div>
				</div>
			</div>
			<div class="fieldGrid is-stack">
				<label class="field">
					<span>Open in</span>
					<select name="target" onchange="this.dataset.userOverride='true'">
						<option value="">Same window</option>
						<option value="_blank" <?= $newTab ? 'selected' : '' ?>>New tab</option>
					</select>
				</label>
				<label class="field">
					<span>Class names</span>
					<input name="linkClass" type="text" value="<?= $e($classNames) ?>" autocapitalize="off" spellcheck="false">
					<small>For special styles of the website, separated by spaces.</small>
				</label>
				<label class="checkbox"><span>Download the file</span><input type="checkbox" <?= $download ?> name="download" onchange="this.dataset.userOverride='true'" class="switch tinyswitch"><div><div></div></div></label>
			</div>
		</main>
		<footer>
			<button type="button" data-cms-action class="button submit" onclick="applyHTMLeditorLink(this)"><i class="cmsIcon cmsIcon-link"></i><?= $href === '' ? 'Insert link' : 'Save' ?></button>
		</footer>
	</section>
</div>
