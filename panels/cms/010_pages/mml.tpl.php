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
use Systopic\System\Media\GroupPolicy;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$groupInfo = $this->mediaGroups;
$siteRoot = (string) http::$siteRoot;
?>
<ul data-all-media-accept="<?= $e(GroupPolicy::accept([], 'text')) ?>" data-node_parent_node_id='<?= (int) ($this->state->id ?? 0) ?>'>
<?php foreach ($this->library as $groupName => $entries) {
	$label = $groupInfo[$groupName]['label'] ?? match ($groupName) {
		'unassigned' => 'unassigned (not displayed)',
		$this::TEXT_GROUP => 'In text',
		default => $groupName,
	};
	?>
	<li class='title notselectable' <?= html::arrayToDataAttr(['media_group' => $groupName, 'drop_accept' => 'media', 'on_drop' => 'mml/updateMediaGroup', 'media-accept' => GroupPolicy::accept($groupInfo, $groupName)]) ?>>
		<h4><span><?= $e($label) ?></span><a class="ajax button transparent icononly small mediaGroupInfo" data-history="false" href="<?= $e($this->href . 'mml/groupInfo/?group=' . rawurlencode($groupName)) ?>" title="Allowed file types" aria-label="Allowed file types for <?= $e($label) ?>"><i class="cmsIcon cmsIcon-circle-info"></i></a></h4>
		<ul>
			<li class="upload" data-upload-many>
				<i class='cmsIcon cmsIcon-plus'></i>
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
				<?php $faceForClass = $entry['alias'] ? ['mark' => false] : $this->tileFace($entry['media']); ?>
				<li class="<?= !empty($faceForClass['mark']) ? 'is-mark' : '' ?>" <?= html::arrayToDataAttr([
					'drag_type' => 'media', 'node_id' => $id,
					// without a medium these stay off, as in the legacy markup
					'media-extension' => GroupPolicy::extension($filename),
                    'media_id' => $media?->id, 'type' => $media?->type, 'value' => $media?->label,
					'on_contextmenu' => 'mml/getMmlContextmenu',
				]) ?>>
					<?php if ($entry['alias']) { ?>
					<input type=file accept="<?= $e(GroupPolicy::accept($groupInfo, $groupName)) ?>" data-on_change='upload/open'>
					<?php } ?>
					<?php if ($entry['alias']) {
						$ext = in_array($mime, ['image/png', 'image/jpeg'], true) ? '' : '.png';
						?>
						<a class='ajax' href='./?id=<?= (int) $entry['originalParentId'] ?>'>
							<img src='<?= $e("{$siteRoot}var/formats/mmlThumb/$folders$filename$ext") ?>' data-broken='<?= $e($this->brokenIcon($media)) ?>'>
							<i class='cmsIcon cmsIcon-file-import'></i>
						</a>
					<?php } else {
						$face = $this->tileFace($media);
						$src = ($media === NULL || $face['mark']) ? '' : $this->thumbOf($media);
						?>
						<?php if (!$face['mark']) { ?>
						<img src='<?= $e($src) ?>' data-broken='<?= $e($this->brokenIcon($media)) ?>'>
						<?php } ?>
						<i class='cmsIcon cmsIcon-<?= $e($face['icon']) ?> thumbType'></i>
						<div class='thumbActions'>
							<span class='thumbGrip' title='Drag'><span></span><span></span><span></span><span></span><span></span><span></span></span>
							<div class='thumbButtons'>
								<a class='ajax' href='editor/file/?target_id=<?= $id ?>' title='Edit' onmousedown='event.stopPropagation()' onpointerdown='event.stopPropagation()'><i class='cmsIcon cmsIcon-pen'></i></a>
								<label title='Replace' onmousedown='event.stopPropagation()' onpointerdown='event.stopPropagation()'>
									<i class='cmsIcon cmsIcon-arrows-rotate'></i>
									<input type=file accept="<?= $e(GroupPolicy::accept($groupInfo, $groupName)) ?>" data-on_change='upload/open'>
								</label>
							</div>
						</div>
					<?php } ?>
					<div class='sort h insertBefore' data-drop_accept='media' data-on_drop='mml/insertMediaBefore'></div>
					<div class='sort h insertAfter' data-drop_accept='media' data-on_drop='mml/insertMediaAfter'></div>
				</li>
			<?php } ?>
		</ul>
	</li>
<?php } ?>
</ul>
