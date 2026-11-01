<?php
/**
 * Page identity, who created and modified it, and when.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */
$page = $this->parent->page;
$row = $page->row;
$e = static fn($v): string => htmlspecialchars((string) ($v instanceof \Systopic\Db\Type\Text ? $v->value : $v), ENT_QUOTES);
$stamp = static fn(?DateTimeImmutable $d): string => \Systopic\System\Sys\LocalTime::format($d, \Systopic\System\Sys\LocalTime::DATETIME_SECONDS);
?>
<h3>Info</h3>
<ul>
	<li>
		<dl class="cmsFacts">
            <div><dt>Page ID</dt><dd><?= (int) $page->id ?></dd></div>
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
