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
 * Below, for everybody: what the groups mean — per group the roles with a
 * right on it, and in the summary what each role may do on the page all in
 * all (read on any group is read, write on any group is write).
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
	echo ' Visitors of the site see a page through a group the role Everyone may read.';
?></p>
<?php if (!defined('PAGE_ACL') || !PAGE_ACL) { ?>
	<p class="hint">Not switched on for this project yet (PAGE_ACL in the global settings): the groups are kept, but every page shows to everyone.</p>
<?php } ?>
<?php if ($active !== []) {
	// who that means: each group of the page, the roles with a right on it,
	// and below what each role may do on the page all in all
	$admin = UserAdmin::of($this->parent->scope);
	$sum = [];
	?>
	<table class="aclResolution">
		<thead>
			<tr><th>Group</th><th>Role</th><th class="rw" title="Read">R</th><th class="rw" title="Write">W</th></tr>
		</thead>
		<tbody>
			<?php foreach ($active as $groupId) {
				$group = $admin->group($groupId);
				$rows = [];
				foreach ($admin->roles as $role) {
					$link = $group === null ? null : $admin->permission($group, $role);
					if ($link !== null && ($link->read || $link->write)) {
						$rows[] = [$role, (bool) $link->read, (bool) $link->write];
						$sum[(int) $role->id] ??= [$role, false, false];
						$sum[(int) $role->id][1] = $sum[(int) $role->id][1] || $link->read;
						$sum[(int) $role->id][2] = $sum[(int) $role->id][2] || $link->write;
					}
				}
				$span = max(1, count($rows));
				$groupCell = '<td class="group" rowspan="' . $span . '">' . $e($group?->name ?? "#$groupId") . '</td>';
				if ($rows === []) { ?>
					<tr class="groupStart"><?= $groupCell ?><td colspan="3" class="none">no role</td></tr>
				<?php }
				foreach ($rows as $i => [$role, $read, $write]) { ?>
					<tr<?= $i === 0 ? ' class="groupStart"' : '' ?>>
						<?= $i === 0 ? $groupCell : '' ?>
						<td><?= $e($role->name) ?></td>
						<td class="rw"><?= $read ? '<i class="fa-regular fa-check" title="Read"></i>' : '' ?></td>
						<td class="rw"><?= $write ? '<i class="fa-regular fa-check" title="Write"></i>' : '' ?></td>
					</tr>
				<?php }
			} ?>
		</tbody>
		<tbody class="summary">
			<?php $check = static fn(bool $on, string $title): string => $on ? '<i class="fa-regular fa-check" title="' . $title . '"></i>' : ''; ?>
			<tr><th>Summary</th><th>Role</th><th class="rw" title="Read">R</th><th class="rw" title="Write">W</th></tr>
			<?php if ($sum === []) { ?>
				<tr><td></td><td colspan="3" class="none">nobody but the owner and superusers</td></tr>
			<?php }
			$i = 0;
			foreach ($sum as [$role, $read, $write]) { ?>
				<tr>
					<?php if ($i++ === 0) { ?><td rowspan="<?= count($sum) ?>"></td><?php } ?>
					<td><?= $e($role->name) ?></td>
					<td class="rw"><?= $check($read, 'Read') ?></td>
					<td class="rw"><?= $check($write, 'Write') ?></td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
<?php } ?>
