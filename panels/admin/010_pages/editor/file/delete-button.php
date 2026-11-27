<?php
$count = isset($uses) ? count($uses) : count(\Systopic\Cms\Queries\MediaUsage\MediaUsage::of($this->pages()->scope, (int) $this->targetId, (int) $this->mediaRow->id));
$references = $count === 1 ? '1 page reference found' : "$count page references found";
$confirmation = "Delete this file? $references. Deleting may break these references.";
?>
<button type="button" data-cms-action class="put button" data-confirm="<?= htmlspecialchars($confirmation, ENT_QUOTES) ?>" data-on_click="userConfirm" data-action="deleteMedia" aria-label="Delete file" title="Delete file"><i class="cmsIcon cmsIcon-trash" aria-hidden="true"></i> Delete file</button>
