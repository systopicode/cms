<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Rewritepaths;

use Systopic\Db\Scope;

/**
 * Deleting rewrite paths, also where the old request log still points at them.
 *
 * Nobody writes `log_requests` any more and the model is gone (2026-11-11).
 * A database that still has the table can carry a foreign key from
 * `log_requests.request_rewritepath_id` onto the paths (galerie), and then a
 * plain remove fails with 1451 as soon as a path was ever visited. There the
 * log keeps its rows and loses the link — it is history, and a path that no
 * longer exists has nothing to link to. Both in one transaction: detached log
 * rows next to paths that are still there would be a lie. Once `db audit`
 * has dropped the table, only the remove is left.
 *
 * Every place that deletes paths goes through here: the maintenance panel
 * (tools/maintenance/rewrite) and the url list of a page (pages/sidebar).
 */
final class Removal
{
    /** the old request log - no model, only its link to the paths matters */
    private const LOG = 'log_requests';

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
        $linked = (bool) $connection->value(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'request_rewritepath_id'",
            [self::LOG]
        );
        $connection->transactional(static function () use ($connection, $uow, $ids, $linked): void {
            if ($linked) {
                $marks = implode(',', array_fill(0, count($ids), '?'));
                $connection->run('UPDATE ' . self::LOG . " SET request_rewritepath_id = NULL WHERE request_rewritepath_id IN ($marks)", $ids);
            }
            $uow->commit();
        });
        return count($paths);
    }
}
