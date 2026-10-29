<?php

/**
 * The content groups of the page — `cms_nodes.node_acl_id` and
 * `node_acl_inherit`.
 *
 * Only the node itself is saved here. The pages below that inherit follow
 * after the commit (`Tables\Nodes\AclInheritance`).
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */

use Systopic\System\Auth\Session;
use Systopic\System\Tables\Acls\Operator as AclOperator;
use Systopic\System\Tables\Nodes\Operator as NodeOperator;

$node = $this->parent->page?->node;

switch ($this->action) {
	case 'aclInherit':
		if ($node === null) {
			break;
		}
		$rows = filter_var(http::values('acl_inherit'), FILTER_VALIDATE_BOOLEAN)
			? NodeOperator::inheritingAcl($node)
			// the groups it had from its parent become its own, nothing changes yet
			: NodeOperator::withAcl($node, $node->acl);
		$this->parent->scope->save(...$rows);
		$this->updateView('045_permissions');
		break;

	case 'aclToggle':
		$groupId = (int) http::dataset('group_id');
		if ($node === null || $groupId < 2 || $node->aclInherit) {
			break;
		}
		if (!Session::isSuperuser() && !in_array($groupId, Session::current()->writeGroupIds, true)) {
			message::error('You may not change this group.');
			break;
		}
		$groupIds = AclOperator::groupIds($node->acl);
		$groupIds = in_array($groupId, $groupIds, true)
			? array_values(array_diff($groupIds, [$groupId]))
			: [...$groupIds, $groupId];
		[$acl, $rows] = AclOperator::forGroups($this->parent->scope, $groupIds);
		$this->parent->scope->save(...$rows, ...NodeOperator::withAcl($node, $acl));
		$this->updateView('045_permissions');
		break;
}
