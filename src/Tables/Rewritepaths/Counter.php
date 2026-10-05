<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Rewritepaths;

use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;

/**
 * Counting the visits of a path — LIVE in `livecount`, every other stage in
 * `testcount`, with the time of the last one.
 *
 * An increment in the database (`x = x + 1`), not a value assigned in PHP and
 * saved: two requests at the same time must both count. It goes past the
 * unit of work, so the table is invalidated by hand — orm-migration.md §1.
 *
 * By id, not by string. The legacy (`pageRewritepathFuncs::count()`) counted
 * every row with that string, and since paths are unique per site a visit of
 * /kontakt/ on one site counted the /kontakt/ of every other site too.
 */
final class Counter
{
    public static function hit(Scope $scope, Model $path, bool $live): void
    {
        if ($path->id === null) {
            return;
        }
        $table = Schema::tableNameOf(Model::class);
        $kind = $live ? 'livecount' : 'testcount';
        $scope->connection->run(
            "UPDATE $table SET rewritepath_$kind = rewritepath_$kind + 1, rewritepath_{$kind}_datetime = NOW() WHERE rewritepath_id = ?",
            [(int) $path->id],
        );
        $scope->results->invalidate([$table]);
    }
}
