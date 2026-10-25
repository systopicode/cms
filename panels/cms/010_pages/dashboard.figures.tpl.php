<?php
/** Dashboard: the size of the site at a glance; what needs work is in To do. @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this */

use Systopic\Cms\Queries\Dashboard\Dashboard;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$figures = Dashboard::figures(\Systopic\Db\Scope::default());
$tiles = [
	['Pages', $figures['pages'], 'fa-file-lines', 'cms/pages/'],
	['Media', $figures['media'], 'fa-image', 'cms/pages/'],
	['Tags', $figures['tags'], 'fa-tags', 'cms/settings/tags/'],
	['Edited this week', $figures['week'], 'fa-pen-to-square', 'cms/pages/'],
];
?>
<header><h2><i class="fa-regular fa-chart-simple"></i>At a glance</h2></header>
<div class="dashFigures">
	<?php foreach ($tiles as [$label, $count, $icon, $path]) { ?>
		<a class="ajax" href="<?= $e(\http::$root . $path) ?>">
			<i class="fa-regular <?= $icon ?>"></i>
			<strong><?= number_format($count, 0, ',', '.') ?></strong>
			<span><?= $label ?></span>
		</a>
	<?php } ?>
</div>
