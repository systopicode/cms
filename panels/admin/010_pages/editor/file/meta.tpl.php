<?php
/**
 * The file editor's sidebar — the same accordion as the page.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Panel $this
 * @var \Systopic\System\Tables\Medias\Media                      $original
 */
$row = $this->mediaRow;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$when = static function (?DateTimeImmutable $d, ?string $who) use ($e): string {
	$stamp = \Systopic\System\Sys\LocalTime::format($d, \Systopic\System\Sys\LocalTime::DATETIME);
	$who = trim((string) $who);
	if ($stamp === '') {
		return '';
	}
	return $who !== '' ? $stamp . ' · ' . $e($who) : $e($stamp);
};
$path = \fs::toNative($original->path);
$onDisk = is_file($path);
$size = $onDisk ? str::formatbytes($path) : 'not on disk';
$format = ((int) $row->width > 0 && (int) $row->height > 0)
	? (int) $row->width . ' × ' . (int) $row->height . ' px · ' . $size
	: $size;
?>
<aside class="cms">

	<div class="flexContainer">
		<div class="scrollContainer">
			<div class="scrollContainerContent" data-node_id="<?= (int) $this->targetId ?>">
				<details class="sidebarSection" data-section="media-file" open>
					<summary><span class="sidebarLabel">File</span><span class="sidebarTools"><i class="cmsIcon cmsIcon-chevron-down sidebarFold" aria-hidden="true"></i></span></summary>
					<div class="sidebarItem">
						<div class="inputGroup">
							<input aria-label="Label" data-on_blur="updateMediaLabel" spellcheck="off" type="text" name="label" value="<?= $e($row->label) ?>">
							<span class="bar"></span>
							<label>Label</label>
						</div>
						<div class="inputGroup">
							<input aria-label="File name, the ending stays" data-on_blur="updateFilename" spellcheck="off" type="text" name="filename" value="<?= $e($row->filename) ?>" autocomplete="off" enterkeyhint="done">
							<span class="bar"></span>
							<label>File name, the ending stays</label>
						</div>
						<?php
						$type = strtolower((string) $row->type);
						$file = strtolower((string) $row->filename);
						if (($type === '' || $type === 'application')
							&& (str_contains(strtolower((string) $row->mimetype), 'sketchup') || str_ends_with($file, '.skp'))) {
							$type = 'sketchup';
						}
						echo \Systopic\System\Tables\Medias\External::fieldsHtml([
							'type' => $type,
							'url' => (string) $row->url,
							'autosave' => true,
						]);
						?>
					</div>
				</details>
				<?php
$usageScope = $this->pages()->scope;
$usageNode = $this->targetId;
$usageMedia = (int) $row->id;
$usageHref = $this->pages()->href;
include dirname(__DIR__, 2) . '/media-usage.tpl.php';
?>
<details class="sidebarSection" data-section="media-details" open>
					<summary><span class="sidebarLabel">Details</span><span class="sidebarTools"><i class="cmsIcon cmsIcon-chevron-down sidebarFold" aria-hidden="true"></i></span></summary>
					<div class="sidebarItem">
						<dl class="cmsFacts">
                            <div><dt>Media ID</dt><dd><?= (int) $row->id ?></dd></div>
                            <div><dt>File details</dt><dd>Dimensions and size below describe the original file. Generated image formats can have different dimensions and crops.</dd></div>
							<?php if ($created = $when($row->createdDate, $row->createdUser?->fullname)) { ?>
								<div>
									<dt>Created</dt>
									<dd><?= $created ?></dd>
								</div>
							<?php } ?>
							<?php if ($modified = $when($row->modifiedDate, $row->modifiedUser?->fullname)) { ?>
								<div>
									<dt>Modified</dt>
									<dd><?= $modified ?></dd>
								</div>
							<?php } ?>
							<div>
								<dt>Format</dt>
								<dd><?= $e($format) ?></dd>
							</div>
							<div>
								<dt>Type</dt>
								<dd><?= $e($row->mimetype) ?></dd>
							</div>
						</dl>
					</div>
				</details>

				<details class="sidebarSection" data-section="media-tags" open>
					<summary><span class="sidebarLabel">Tags</span><span class="sidebarTools"><i class="cmsIcon cmsIcon-chevron-down sidebarFold" aria-hidden="true"></i></span></summary>
					<div class="sidebarItem">
						<?php $this->withView('tags'); ?>
					</div>
				</details>
			</div>
		</div>
	</div>
</aside>
