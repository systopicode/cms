<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Rewritepaths;

use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\Cms\Pages\Sites\SiteRegistry;
use Systopic\Cms\Queries\SiteOfNode\SiteOfNode;

/**
 * Writing `rewritepath_site_node_id` from the tree — for a moved branch and
 * for the whole installation (maintenance).
 *
 * One `UPDATE … JOIN (WITH RECURSIVE …)` each, past the unit of work: the
 * rows are not loaded, and loading every path of a branch to set one column
 * would cost what the statement does in one go. The table is invalidated by
 * hand — orm-migration.md §1. `Pages\Sites\NodeSite` delegates here and
 * forgets its cached answers.
 */
final class SiteAssignment
{
    /** @return int the number of paths that changed */
    public static function branch(Scope $scope, int $nodeId): int
    {
        if ($nodeId <= 0) {
            return 0;
        }
        $parentSite = SiteOfNode::of($scope, $nodeId, skipSelf: true);
        $count = $scope->connection->run((string) file_get_contents(__DIR__ . '/SiteAssignment.branch.sql'), [
            'root' => $nodeId, 'parentSite' => $parentSite,
            'type' => SiteRegistry::NODE_TYPE, 'type2' => SiteRegistry::NODE_TYPE,
        ])->rowCount();
        $scope->results->invalidate([Schema::tableNameOf(Model::class)]);
        return $count;
    }

    /**
     * Every path onto its site.
     *
     * Also makes the column nullable where it still carries the first
     * spelling (NOT NULL DEFAULT 0, 0 = default site) — an ALTER the schema
     * catch-up does not run on its own — and turns the remaining 0 (row 1,
     * paths of a deleted page) into NULL.
     *
     * @return int the number of paths that changed
     */
    public static function all(Scope $scope): int
    {
        $connection = $scope->connection;
        $table = Schema::tableNameOf(Model::class);
        $nullable = $connection->value(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, 'rewritepath_site_node_id'],
        );
        if ($nullable === 'NO') {
            $connection->exec("ALTER TABLE $table MODIFY rewritepath_site_node_id INT UNSIGNED NULL DEFAULT NULL");
        }
        $count = $connection->run((string) file_get_contents(__DIR__ . '/SiteAssignment.all.sql'), [
            'type' => SiteRegistry::NODE_TYPE, 'type2' => SiteRegistry::NODE_TYPE,
        ])->rowCount();
        $count += $connection->run("UPDATE $table SET rewritepath_site_node_id = NULL WHERE rewritepath_site_node_id = 0")->rowCount();
        $scope->results->invalidate([$table]);
        return $count;
    }
}
