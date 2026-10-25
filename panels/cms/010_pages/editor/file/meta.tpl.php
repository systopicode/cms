<?php
/**
 * The file editor's sidebar — the same accordion as the page.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Editor\File\Panel $this
 * @var \Systopic\System\Tables\Medias\Media                      $original
 */
$row = $this->mediaRow;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$when = static function (?DateTimeImmutable $d, ?string $who) use ($e): string {
	$stamp = \Systopic\System\Sys\LocalTime::format($d, 'd.m.Y H:i');
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
					<summary>File</summary>
					<div class="sidebarItem">
						<div class="inputGroup">
							<input data-on_input="updateMediaLabel" spellcheck="off" type="text" name="label" value="<?= $e($row->label) ?>">
							<span class="bar"></span>
							<label>Label</label>
						</div>
						<div class="inputGroup">
							<input data-on_input="updateFilename" spellcheck="off" type="text" name="filename" value="<?= $e($row->filename) ?>">
							<span class="bar"></span>
							<label>File name</label>
						</div>
						<?php
						echo \Systopic\System\Tables\Medias\External::fieldsHtml([
							'type' => (string) $row->type,
							'url' => (string) $row->url,
							'autosave' => true,
						]);
						?>
					</div>
				</details>
				<details class="sidebarSection" data-section="media-details" open>
					<summary>Details</summary>
					<div class="sidebarItem">
						<dl class="cmsFacts">
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
					<summary>Tags</summary>
					<div class="sidebarItem">
						<?php $this->withView('tags'); ?>
					</div>
				</details>
			</div>
		</div>
	</div>
</aside>
