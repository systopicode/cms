<?php
/** Dashboard: the pages changed last. @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this */

use Systopic\Cms\Queries\Dashboard\Dashboard;
use Systopic\System\Panels\Root\Cms\Panel as Cms;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$href = \http::$root . 'cms/pages/?id=';
?>
<header><h2><i class="fa-regular fa-clock-rotate-left"></i>Recently edited</h2></header>
<ul class="dashList">
	<?php foreach (Dashboard::pages(\Systopic\Db\Scope::default(), 'recent', 6) as $page) { ?>
		<li>
			<a class="ajax" href="<?= $e($href . $page['id']) ?>">
				<i class="fa-regular fa-file"></i>
				<span><strong><?= $e($page['label'] !== '' ? $page['label'] : 'Untitled') ?></strong><small><?= $e(trim(Cms::ago($page['modified']) . ($page['user'] !== '' ? ' · ' . $page['user'] : ''))) ?></small></span>
				<?php if (!$page['published']) { ?><em class="dashBadge">Hidden</em><?php } ?>
			</a>
		</li>
	<?php } ?>
</ul>
