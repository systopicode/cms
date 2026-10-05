<?php
/**
 * The media library below the tree — one list per media group of the
 * project, the uploads of the page in it (Panel::$library). Successor of
 * lib/class/mml (mmlGroup, mmlItem): the markup is theirs, the data and
 * the drop targets (mml/actions.php) are the same.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this
 */

use Systopic\System\Tables\Medias\Files;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$groupInfo = $this->mediaGroups;
$siteRoot = (string) http::$siteRoot;
?>
<ul data-node_parent_node_id='<?= (int) ($this->state->id ?? 0) ?>'>
<?php foreach ($this->library as $groupName => $entries) {
	$label = $groupName === 'unassigned' ? 'unassigned (not displayed)' : ($groupInfo[$groupName]['label'] ?? $groupName);
	?>
	<li class='title notselectable' <?= html::arrayToDataAttr(['media_group' => $groupName, 'drop_accept' => 'media', 'on_drop' => 'mml/updateMediaGroup']) ?>>
		<h4><?= $e($label) ?></h4>
		<ul>
			<li class=upload>
				<i class='fa-regular fa-plus'></i>
				<input type=file data-on_change=upload/open>
			</li>
			<?php foreach ($entries as $entry) {
				$media = $entry['media'];
				$node = $entry['node'];
				// an alias stands for its original: the library speaks of that one
				$id = $entry['alias'] ? (int) $node->relationId('originalNode') : (int) $node->id;
				$mediaId = (int) ($media?->id ?? 0);
				$filename = (string) ($media?->filename ?? '');
				$mime = (string) ($media?->mimetype ?? '');
				$type = (string) ($media?->type ?? '');
				$folders = Files::idPath($mediaId);
				?>
				<li <?= html::arrayToDataAttr([
					'drag_type' => 'media', 'node_id' => $id,
					// without a medium these stay off, as in the legacy markup
					'media_id' => $media?->id, 'type' => $media?->type, 'value' => $media?->label,
					'on_contextmenu' => 'mml/getMmlContextmenu',
				]) ?>>
					<input type=file data-on_change='upload/open'>
					<?php if ($entry['alias']) {
						$ext = in_array($mime, ['image/png', 'image/jpeg'], true) ? '' : '.png';
						?>
						<a class='ajax' href='./?id=<?= (int) $entry['originalParentId'] ?>'>
							<img src='<?= $e("{$siteRoot}var/formats/mmlThumb/$folders$filename$ext") ?>'>
							<i class='fal fa-file-import'></i>
						</a>
					<?php } else {
						$ext = in_array($mime, ['image/svg+xml', 'image/webp', 'image/png', 'image/jpeg'], true) ? '' : '.webp';
						$src = "{$siteRoot}var/formats/mmlThumb/$folders$filename$ext";
						$icon = $type;
						if ($type === 'application') {
							$icon = 'file-pdf';
						} elseif ($type === 'video') {
							$poster = "var/original/$folders$filename.webp";
							if (fs::fileExists(fs::$root . $poster)) {
								$src = $siteRoot . $poster;
							}
						} elseif ($mime === 'image/svg+xml') {
							$icon = 'vector-polygon';
							$src = "{$siteRoot}var/original/$folders$filename";
						}
						?>
						<img src='<?= $e($src) ?>'>
						<i class='fa-regular fa-<?= $e($icon) ?>'><a class='ajax' href='editor/file/?target_id=<?= $id ?>'></a></i>
					<?php } ?>
					<div class='sort h insertBefore' data-drop_accept='media' data-on_drop='mml/insertMediaBefore'></div>
					<div class='sort h insertAfter' data-drop_accept='media' data-on_drop='mml/insertMediaAfter'></div>
				</li>
			<?php } ?>
		</ul>
	</li>
<?php } ?>
</ul>
