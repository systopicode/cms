<?php

/** @var \Systopic\Cms\Panels\Root\Cms\Settings\Page\Panel $this */

use Systopic\Cms\Queries\SettingTree\Definition;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$selected = $this->selected;

$chain = [];
for ($walker = $selected; $walker instanceof Definition; $walker = $walker->parent) {
	array_unshift($chain, $walker);
}
$ancestors = $selected ? array_slice($chain, 0, -1) : [];
$title = $selected !== null && $selected->label !== '' ? $selected->label : 'Untitled';
?>
<?php if ($selected !== null) { ?>
	<nav class="crumbs">
			<a class="ajax" href="?">Template Settings</a>
		<i class="fa-regular fa-chevron-right"></i>
		<?php foreach ($ancestors as $crumb) { ?>
			<a class="ajax" href="?id=<?= (int) $crumb->nodeId ?>"><?= $e($crumb->label !== '' ? $crumb->label : 'Untitled') ?></a>
			<i class="fa-regular fa-chevron-right"></i>
		<?php } ?>
		<b><?= $e($title) ?></b>
	</nav>
<?php } ?>
