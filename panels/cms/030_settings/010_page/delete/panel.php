<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Cms\Settings\Page\Delete;

use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\Cms\Queries\SettingTree\Definition;
use Systopic\Cms\Queries\SettingTree\SettingTree;
use Systopic\System\Tables\Junctions\Model as NodeJunction;

/**
 * The lightbox deleting one setting declaration (`?id=` is its node).
 */
class Panel extends PanelNode {

	public bool $inNavi = false;

	/** Set by the action once the declaration is gone: the lightbox then offers "Ok". */
	public bool $done = FALSE;

	public Scope $scope {
		get => Scope::default();
	}

	public ?Definition $definition {
		get => ($id = (int) \http::get('id')) > 1 ? SettingTree::byNodeId($this->scope, $id) : NULL;
	}

	/**
	 * Remove a declaration: the values pages stored against it, the setting
	 * row and its node — in one transaction, because half a deleted setting is
	 * a row pointing at a node that is no longer there.
	 *
	 * The values go in one statement past the unit of work (nobody holds
	 * those rows), so their table is invalidated by hand.
	 *
	 * @return int how many stored values were removed
	 */
	public function removeDefinition(Definition $definition): int {
		$connection = $this->scope->connection;
		$uow = $this->scope->uow()->remove($definition->setting)->remove($definition->node);
		$table = Schema::tableNameOf(NodeJunction::class);
		$removed = $connection->transactional(static function () use ($connection, $uow, $table, $definition): int {
			$count = $connection->run("DELETE FROM $table WHERE junction_setting_node_id = ?", [$definition->nodeId])->rowCount();
			$uow->commit();
			return $count;
		});
		$this->scope->results->invalidate([$table]);
		return $removed;
	}
}
