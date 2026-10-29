<?php
/**
 * Who may see the page: its content groups, and whether they come from the
 * parent page.
 *
 * The node stores its effective ACL either way (`Nodes\Model::$acl`), so the
 * chips always show the groups that apply. Inheriting, they are locked: the
 * switch first gives the page the same groups as its own, then they toggle.
 * A user who is not a superuser toggles only the groups they may write.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */

use Systopic\System\Auth\Session;
use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Acls\Operator as AclOperator;

$node = $this->parent->page->node;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$groups = UserAdmin::of($this->parent->scope)->groups;
$active = AclOperator::groupIds($node->acl);
$inherit = $node->aclInherit;
$isRoot = (int) $node->relationId('parentNode') === 0;
$superuser = Session::isSuperuser();
$writable = Session::current()->writeGroupIds;
?>
<h3>Permissions</h3>
<ul>
	<li>
		<label class="checkbox">
			<span>Inherit from parent page</span>
			<input type=checkbox class=switch data-on_change='sidebar/aclInherit'
				   name=acl_inherit value=1 <?php if ($inherit) { ?> checked='checked' <?php } ?> />
			<div>
				<div></div>
			</div>
		</label>
	</li>
</ul>
<?php if ($groups === []) { ?>
	<p class="hint">There are no content groups yet.</p>
	<?php return;
} ?>
<div class="tags aclGroups">
	<?php foreach ($groups as $group) {
		$id = (int) $group->id;
		$on = in_array($id, $active, true);
		$may = !$inherit && ($superuser || in_array($id, $writable, true));
		$color = $group->color !== null && $group->color !== '' ? " style='--tag-color: {$e($group->color)}'" : '';
		?>
		<div class="tag<?= $on ? ' active' : '' ?><?= $may ? '' : ' locked' ?>"<?= $color ?>
			<?php if ($may) { ?> data-on_click="sidebar/aclToggle" data-group_id="<?= $id ?>"<?php } ?>
			title="<?= $may ? ($on ? 'Remove group' : 'Add group') : ($inherit ? 'Inherited' : 'You may not change this group') ?>"><?= $e($group->name ?? "#$id") ?></div>
	<?php } ?>
</div>
<p class="hint"><?php
	if ($inherit && $isRoot) {
		echo 'A top-level page inherits nothing: no group.';
	} elseif ($inherit) {
		echo 'The groups of the parent page. Switch off inheriting to choose them here.';
	} else {
		echo 'Pages below that inherit get these groups too.';
	}
	if ($active === []) {
		echo ' Without a group only the owner and superusers have access.';
	}
?></p>
