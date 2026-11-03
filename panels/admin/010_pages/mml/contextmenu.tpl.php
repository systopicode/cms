<?php
/**
 * The context menu of a medium in the library — edit, original, download,
 * delete. Successor of lib/class/mml/item/contextmenu.tpl.php.
 *
 * @var int                                       $nodeId the media node (an alias: its original)
 * @var \Systopic\System\Tables\Medias\Model      $media
 */
$original = http::$root . 'var/original/' . \Systopic\System\Tables\Medias\Files::idPath((int) $media->id) . $media->filename;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<ul class="contextmenu mediaContextMenu">
    <li class="iconsonly">
	<ul>
	    <li title="Edit" class="iconOnly">
		<a class="ajax" href="editor/file/?target_id=<?= $nodeId ?>">
		    <i class="cmsIcon cmsIcon-crop-simple"></i>
		</a>
	    </li>
	    <li title="Show Original" class="iconOnly">
		<a target="_blank" href="<?= $e($original) ?>">
		    <i class="cmsIcon cmsIcon-eye"></i>
		</a>
	    </li>
	    <li title="Download Original" class="iconOnly">
		<a href="<?= $e($original) ?>" target='_self' download="">
		    <i class="cmsIcon cmsIcon-arrow-down-to-line"></i>
		</a>
	    </li>
	    <li title="Delete" class="iconOnly">
		<button type="button" data-cms-action class="put" data-confirm="<?= htmlspecialchars('Delete this file? ' . count(\Systopic\Cms\Queries\MediaUsage\MediaUsage::of($pages->scope, (int) $nodeId, (int) $media->id)) . ' page references found. Deleting may break these references.', ENT_QUOTES) ?>" data-on_click='userConfirm' data-action="mml/deleteMedia?node_id=<?= $nodeId ?>&media_id=<?= (int) $media->id ?>">
		    <i class="cmsIcon cmsIcon-trash"></i>
		</button>
	    </li>
	</ul>
    </li>
</ul>
