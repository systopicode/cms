<?php
/** Dashboard: the pages changed last. @var \Systopic\Cms\Panels\Root\Admin\Pages\Panel $this */

use Systopic\Cms\Queries\Dashboard\Dashboard;
use Systopic\System\Panels\Root\Admin\Panel as Cms;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$href = \http::$root . 'admin/pages/?id=';
?>
<header><h2><i class="cmsIcon cmsIcon-clock-rotate-left"></i>Recently edited</h2></header>
<ul class="dashList">
	<?php foreach (Dashboard::pages(\Systopic\Db\Scope::default(), 'recent', 6) as $page) { ?>
		<li>
			<a class="ajax" href="<?= $e($href . $page['id'] . '&lang=' . rawurlencode($page['language'])) ?>">
				<i class="cmsIcon cmsIcon-file"></i>
				<span><strong><?= $e($page['label'] !== '' ? $page['label'] : 'Untitled') ?></strong><small><?= $e(trim(Cms::ago($page['modified']) . ' · ' . $page['language'] . ($page['user'] !== '' ? ' · ' . $page['user'] : ''))) ?></small></span>
				<?php if (!$page['published']) { ?><em class="dashBadge">Hidden</em><?php } ?>
			</a>
		</li>
	<?php } ?>
</ul>
