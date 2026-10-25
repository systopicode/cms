<?php
/**
 * Preview link, label, who made it when.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */
$page = $this->parent->page;
$row = $page->row;
$e = static fn($v): string => htmlspecialchars((string) ($v instanceof \Systopic\Db\Type\Text ? $v->value : $v), ENT_QUOTES);
$stamp = static fn(?DateTimeImmutable $d): string => \Systopic\System\Sys\LocalTime::format($d, 'd.m.Y H:i:s');
?>
<h3>Info</h3>
<ul>
	<?php if ($page->node->isTrashed) { ?>
		<li>
			<a target="_blank" class="sideAction" href="<?= http::$siteRoot ?>?id=<?= $page->id ?>&lang=<?= $page->language ?>&preview&showtrashed"><i class="fa-regular fa-eye"></i>Preview</a>
		</li>
	<?php } else { ?>
		<li>
			<a target="_blank" class="sideAction" href="<?= http::$siteRoot ?>?id=<?= $page->id ?>&lang=<?= $page->language ?>&preview"><i class="fa-regular fa-eye"></i>Preview</a>
		</li>
	<?php } ?>

	<li>
		<div class="inputGroup">
			<input autocomplete="off" type="text" data-on_change="sidebar/updateParam" name="label" value="<?= $e($row->label) ?>">
			<span class="bar"></span>
			<label>Label</label>
		</div>
	</li>
	<li>
		<dl class="cmsFacts">
			<div>
				<dt>Created</dt>
				<dd><?= $stamp($row->createdDate) ?><?php if ($row->createdUser?->fullname) { ?> · <?= $e($row->createdUser->fullname) ?><?php } ?></dd>
			</div>
			<div>
				<dt>Modified</dt>
				<dd><?= $stamp($row->modifiedDate) ?><?php if ($row->modifiedUser?->fullname) { ?> · <?= $e($row->modifiedUser->fullname) ?><?php } ?></dd>
			</div>
		</dl>
	</li>
</ul>

<?php
