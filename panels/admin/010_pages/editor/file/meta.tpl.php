<?php
/**
 * The file editor's sidebar — the same accordion as the page.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Editor\File\Panel $this
 * @var \Systopic\System\Tables\Medias\Media                      $original
 */
$row = $this->mediaRow;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<aside class="cms">

	<div class="flexContainer">
		<div class="scrollContainer">
			<div class="scrollContainerContent" data-node_id="<?= (int) $this->targetId ?>">
				<details class="sidebarSection" data-section="media-file" open>
					<summary><span class="sidebarLabel">File</span><span class="sidebarTools"><i class="cmsIcon cmsIcon-chevron-down sidebarFold" aria-hidden="true"></i></span></summary>
					<div class="sidebarItem">
						<?php include __DIR__ . '/fields.php'; ?>
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
						<?php include __DIR__ . '/facts.php'; ?>
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
