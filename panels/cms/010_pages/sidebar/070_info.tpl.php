<?php
/**
 * Preview link, label, who made it when.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */
$page = $this->parent->page;
$row = $page->row;
$e = static fn($v): string => htmlspecialchars((string) ($v instanceof \Systopic\Db\Type\Text ? $v->value : $v), ENT_QUOTES);
$stamp = static fn(?DateTimeImmutable $d): string => $d?->format('d.m.Y H:i:s') ?? '';
?>
<h3>Info</h3>
<ul>
	<?php if ($page->node->isTrashed) { ?>
		<li>
			<div class='group'>
				<a target="_blank" class='previewLink' href="<?= http::$siteRoot ?>?id=<?= $page->id ?>&lang=<?= $page->language ?>&preview&showtrashed"><i style='margin-right: 10px' class="fal fa-eyes"></i>Page Preview</a>
			</div>
		</li>
	<?php } else { ?>
		<li>
			<div class='group'>
				<a target="_blank" class='previewLink' href="<?= http::$siteRoot ?>?id=<?= $page->id ?>&lang=<?= $page->language ?>&preview"><i style='margin-right: 10px' class="fal fa-eyes"></i>Page Preview</a>
			</div>
		</li>
	<?php } ?>

	<li class="seperator"></li>
	<li>
		<div class="inputGroup">
			<input autocomplete="off" type="text" data-on_change="sidebar/updateParam" name="label" value="<?= $e($row->label) ?>">
			<span class="bar"></span>
			<label>Label</label>
		</div>
	</li>
	<li class="seperator"></li>
	<li>
		<div class='group'>
			<span>
				<b>Created&nbsp;at&nbsp;</b><br><?= $stamp($row->createdDate) ?>
				<br><b>by&nbsp;</b><?= $e($row->createdUser?->fullname) ?>
			</span>
		</div>
		<div class='group'>
			<span>
				<b>Modified&nbsp;at&nbsp;</b><br><?= $stamp($row->modifiedDate) ?>
				<br><b>by&nbsp;</b><?= $e($row->modifiedUser?->fullname) ?>
			</span>
		</div>
	</li>
</ul>

<?php
