<?php
/**
 * Visibility of the page: navigation, published, time window, trash.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Sidebar\Panel $this
 */
$page = $this->parent->page;
$row = $page->row;
$e = static fn($v): string => htmlspecialchars((string) ($v instanceof \Systopic\Db\Type\Text ? $v->value : $v), ENT_QUOTES);
$datetime = static fn(?DateTimeImmutable $d): string => $d?->format('Y-m-d\TH:i:s') ?? '';
?>
<?php
$publishing = \Systopic\Cms\Pages\PublishingState::of($row !== null, (bool) $page->node->isTrashed, (bool) $row?->isPublished, $row?->startPublishing, $row?->endPublishing);
?>
<h3>Publishing</h3>
<div class="publishingSummary" role="status">
    <strong><?= $e($publishing['label']) ?></strong>
    <p><?= $e($publishing['reason']) ?></p>
    <?php if ($publishing['public'] && !$row->isNaviElement): ?><p>Not listed in navigation. Direct links can still open this page.</p><?php endif; ?>
    <small>Server time: <?= $e(date(\Systopic\System\Sys\LocalTime::DATETIME)) ?> · <?= $e(date_default_timezone_get()) ?></small>
</div>
<ul class="releaseFields">
    <li><a target="_blank" rel="noopener" class="sideAction previewAction" href="<?= http::$siteRoot ?>?id=<?= $page->id ?>&amp;lang=<?= $page->language ?>&amp;preview<?= $page->node->isTrashed ? '&amp;showtrashed' : '' ?>"><i class="cmsIcon cmsIcon-user-hat-tie-magnifying-glass"></i>Preview</a></li>
	<li>
		<label class="checkbox">
			<span>Navi element</span>
			<input type=checkbox class=switch data-on_change='sidebar/updateParam'
				   <?= \Systopic\Cms\Pages\FieldVersion::attributes($page, 'is_navi_element') ?> name=is_navi_element <?php if ($row->isNaviElement) { ?> checked='checked' <?php } ?> />
			<div>
				<div></div>
			</div>
		</label>
		<label class="checkbox"><span>Published</span><input data-on_change='sidebar/updateParam'
															 type="checkbox" <?php if ($row->isPublished) { ?> checked='checked' <?php } ?>
															 value='1' <?= \Systopic\Cms\Pages\FieldVersion::attributes($page, 'is_published') ?> name='is_published' class="switch tinyswitch"/>
			<div>
				<div></div>
			</div>
		</label>
		<div class="sideActions">
			<button type="button" class="sideAction" data-cms-action data-on_click="userConfirm" data-action="<?= $e($this->parent->href . 'publishAll') ?>" data-node_id="<?= (int) $page->id ?>" data-confirm="<?= $e($this->parent->branchConfirmation((int) $page->id, 'publishAll')) ?>"><span class="layerEyeIcon" aria-hidden="true"><i class="cmsIcon cmsIcon-layer-group"></i><i class="cmsIcon cmsIcon-eye"></i></span>Publish with children</button>
			<button type="button" class="sideAction" data-cms-action data-on_click="userConfirm" data-action="<?= $e($this->parent->href . 'unPublishAll') ?>" data-node_id="<?= (int) $page->id ?>" data-confirm="<?= $e($this->parent->branchConfirmation((int) $page->id, 'unPublishAll')) ?>"><span class="layerEyeIcon" aria-hidden="true"><i class="cmsIcon cmsIcon-layer-group"></i><i class="cmsIcon cmsIcon-eye-slash"></i></span>Unpublish with children</button>
		</div>
	</li>
	<li>
		<?php
		$startPublishing = $datetime($row->startPublishing);
		$endPublishing = $datetime($row->endPublishing);
		?>
		<div class="inputGroup">
			<input <?= \Systopic\Cms\Pages\FieldVersion::attributes($page, 'start_publishing') ?> name="start_publishing" placeholder=' ' type="datetime-local"
				   data-on_blur='sidebar/updateParam' class="date" value="<?= $startPublishing ?>"
				   autocomplete="off"/>
			<span class="bar"></span>
			<label>Publishing start</label>
		</div>
		<div class="inputGroup">
			<input <?= \Systopic\Cms\Pages\FieldVersion::attributes($page, 'end_publishing') ?> name="end_publishing" placeholder=' ' type="datetime-local" data-on_blur='sidebar/updateParam'
				   class="date" value="<?= $endPublishing ?>" autocomplete="off"/>
			<span class="bar"></span>
			<label>Publishing end</label>
		</div>
	</li>
	<?php if (!$page->isAlias && !$page->node->isTrashed) { ?>
		<li title="Delete Page" >
			<button type="button" data-cms-action data-confirm="<?= $e($this->parent->branchConfirmation((int) $page->id, 'deletePage')) ?>"
			   class="sideAction quietAction"
			   data-on_click="userConfirm"
			   data-action="<?= $e($this->parent->href . 'deletePage') ?>"
			   data-node_id="<?= $page->id ?>">
				<i class="cmsIcon cmsIcon-trash"></i>
				Remove page
			</button>
		</li>
	<?php } elseif ($page->node->isTrashed) { ?>
		<li title="Recover Page" >
			<button type="button" data-cms-action data-confirm="Restore this page in every language, including children?"
			   class="sideAction quietAction"
			   data-on_click="userConfirm"
			   data-action="recoverPage"
			   data-node_id="<?= $page->id ?>">
				<i class="cmsIcon cmsIcon-arrow-rotate-left"></i>
				Restore page
			</button>
		</li>
	<?php } ?>
</ul>
