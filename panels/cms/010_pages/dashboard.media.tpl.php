<?php
/** Dashboard: the media added last, a click opens their page. @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this */

use Systopic\Cms\Queries\Dashboard\Dashboard;
use Systopic\System\Queries\MediaNodes\MediaNodes;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$scope = \Systopic\Db\Scope::default();
$recent = Dashboard::media($scope);
$medias = MediaNodes::byNodeIds($scope, array_column($recent, 'node'));
?>
<header><h2><i class="fa-regular fa-images"></i>Recent media</h2></header>
<?php if ($recent === []) { ?>
	<main><p class="hint">No media yet.</p></main>
<?php } else { ?>
	<div class="dashThumbs">
		<?php foreach ($recent as ['node' => $node, 'page' => $page]) {
			$media = $medias[$node] ?? null;
			if ($media === null) {
				continue;
			}
			?>
			<a class="ajax" href="<?= $e(\http::$root . 'cms/pages/?id=' . $page) ?>" title="<?= $e(trim((string) $media->label) ?: (string) $media->filename) ?>">
				<img src="<?= $e($this->thumbOf($media)) ?>" alt="" loading="lazy" data-broken="<?= $e($this->brokenIcon($media)) ?>">
				<?php if ($media->type === 'video') { ?><i class="fa-regular fa-circle-play"></i><?php } ?>
			</a>
		<?php } ?>
	</div>
<?php } ?>
