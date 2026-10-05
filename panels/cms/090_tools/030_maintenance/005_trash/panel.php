<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Trash;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\Cms\Queries\Branch\Branch;
use Systopic\Cms\Queries\Params;
use Systopic\Cms\Queries\TrashedPages\TrashedPages;
use Systopic\System\Tables\Nodes\Operator as NodeOperator;

/**
 * tools/maintenance/trash — the trashed branches, to get them back.
 */
class Panel extends PanelNode {

	public Scope $scope {
		get => Scope::default();
	}

	/** @var array<int, array{node: \Systopic\System\Tables\Nodes\Model, pages: list<\Systopic\Cms\Tables\Pages\Model>}> */
	public array $trashed {
		get => TrashedPages::of($this->scope);
	}

	/**
	 * A branch out of the trash, everything below it too.
	 *
	 * @return int how many nodes came back
	 */
	public function restoreBranch(int $nodeId): int {
		// The branch's pages are not needed, only its nodes: any language will do.
		$changed = NodeOperator::trash(Branch::of($this->scope, new Params(lang: ''), $nodeId)->nodes, FALSE);
		$this->scope->save(...$changed);
		return count($changed);
	}
}
