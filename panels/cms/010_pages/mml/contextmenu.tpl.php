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
<ul class="contextmenu">
    <li>
	<ul class="iconsonly">
	    <li title="Edit" class="iconOnly">
		<a class="ajax" href="editor/file/?target_id=<?= $nodeId ?>">
		    <i class="fa-regular fa-crop-simple"></i>
		</a>
	    </li>
	    <li title="Show Original" class="iconOnly">
		<a target="_blank" href="<?= $e($original) ?>">
		    <i class="fa-regular fa-eye"></i>
		</a>
	    </li>
	    <li title="Download Original" class="iconOnly">
		<a href="<?= $e($original) ?>" target='_self' download="">
		    <i class="fa-regular fa-arrow-down-to-line"></i>
		</a>
	    </li>
	    <li title="Delete" class="iconOnly">
		<a class="put" data-confirm="Do you really want to delete this File?" data-on_click='userConfirm' data-action="mml/deleteMedia?node_id=<?= $nodeId ?>&media_id=<?= (int) $media->id ?>">
		    <i class="fa-regular fa-trash"></i>
		</a>
	    </li>
	</ul>
    </li>
</ul>
