<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Rewritepaths;

use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\System\Tables\Requests\Model as Request;

/**
 * Deleting rewrite paths the request log still points at.
 *
 * `log_requests.request_rewritepath_id` has a foreign key onto the paths, so a
 * plain remove fails with 1451 as soon as a path was ever visited. The log
 * keeps its rows and loses the link — it is history, and a path that no
 * longer exists has nothing to link to. Both in one transaction: detached log
 * rows next to paths that are still there would be a lie.
 *
 * The detach goes past the unit of work (nobody holds those log rows), so its
 * table is invalidated by hand — orm-migration.md §1.
 *
 * Every place that deletes paths goes through here: the maintenance panel
 * (tools/maintenance/rewrite) and the url list of a page (pages/sidebar).
 */
final class Removal
{
    /** @return int how many paths were removed */
    public static function remove(Scope $scope, Model ...$paths): int
    {
        if ($paths === []) {
            return 0;
        }
        $connection = $scope->connection;
        $uow = $scope->uow();
        foreach ($paths as $path) {
            $uow->remove($path);
        }
        $ids = array_map(static fn(Model $path): int => (int) $path->id, $paths);
        $table = Schema::tableNameOf(Request::class);
        $connection->transactional(static function () use ($connection, $uow, $table, $ids): void {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $connection->run("UPDATE $table SET request_rewritepath_id = NULL WHERE request_rewritepath_id IN ($marks)", $ids);
            $uow->commit();
        });
        $scope->results->invalidate([$table]);
        return count($paths);
    }
}
