<?php
/** Dashboard: the media added last. A click opens the file editor. @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this */

use Systopic\Cms\Queries\Dashboard\Dashboard;
use Systopic\System\Queries\MediaNodes\MediaNodes;
use Systopic\System\Tables\Medias\External;
use Systopic\System\Tables\Medias\Media;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$scope = \Systopic\Db\Scope::default();
$recent = Dashboard::media($scope);
$medias = MediaNodes::byNodeIds($scope, array_column($recent, 'node'));
$badge = static function (object $media): string {
	$type = (string) $media->type;
	$mime = (string) $media->mimetype;
	if ($type === 'application' || $type === 'pdf') {
		return 'file-pdf';
	}
	if ($mime === 'image/gif') {
		return 'gif';
	}
	if (External::is($type)) {
		return 'circle-play';
	}
	if ($mime === 'image/svg+xml') {
		return 'vector-polygon';
	}
	return $type !== '' ? $type : 'file';
};
?>
<header><h2><i class="cmsIcon cmsIcon-images"></i>Recent media</h2></header>
<?php if ($recent === []) { ?>
	<main><p class="hint">No media yet.</p></main>
<?php } else { ?>
	<div class="dashThumbs">
		<?php foreach ($recent as ['node' => $node, 'page' => $page]) {
			$media = $medias[$node] ?? null;
			if ($media === null) {
				continue;
			}
			$file = new Media($media);
			$face = $this->tileFace($media);
			$src = $face['mark'] ? '' : ($media->type === 'video' && $file->poster !== ''
				? $file->poster
				: $file->setFormat((string) $media->mimetype === 'image/svg+xml' ? 'original' : 'mmlThumb')->src);
			?>
			<a class="ajax<?= $face['mark'] ? ' is-mark' : '' ?>" href="<?= $e(rtrim((string) $this->href, '/') . '/editor/file/?target_id=' . (int) $node) ?>" title="<?= $e(trim((string) $media->label) ?: (string) $media->filename) ?>">
				<?php if (!$face['mark']) { ?>
				<img src="<?= $e($src) ?>" alt="" loading="lazy" data-broken="<?= $e($this->brokenIcon($media)) ?>">
				<?php } ?>
				<i class="cmsIcon cmsIcon-<?= $e($face['mark'] ? $face['icon'] : $badge($media)) ?> thumbType"></i>
			</a>
		<?php } ?>
	</div>
<?php } ?>
