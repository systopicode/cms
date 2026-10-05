<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\Branch;

use Systopic\Db\Rows;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\Cms\Tables\Pages\Model as PageRow;
use Systopic\Db\Scope;
use Systopic\Cms\Queries\PageTree\PageTreeLevel;
use Systopic\Cms\Queries\Params;

/**
 * A node and everything below it — `Branch.sql`, one recursive statement.
 *
 * The legacy code walked the branch by recursion over `$page->children`,
 * one statement per page and one more per page for its media — and the
 * children it saw were filtered by publishing and trash, so recovering a
 * branch missed exactly the rows it was meant for.
 *
 * An alias contributes its own node; its content belongs to the original and
 * is only touched through the original's branch.
 */
final class Branch
{
    /**
     * @param list<Node> $nodes every node of the branch, the root included
     * @param array<int, PageRow> $rows node id => page row in the scope's language
     */
    private function __construct(
        public readonly array $nodes,
        public readonly array $rows,
    ) {}

    public static function of(Scope $scope, Params $params, int $nodeId): self
    {
        PageTreeLevel::register();
        $result = $scope->rows(__DIR__ . '/Branch.sql', ['nodeId' => $nodeId, 'lang' => $params->lang]);

        $nodes = [];
        $rows = [];
        foreach ($result->records() as $record) {
            $node = $record[Node::class] ?? null;
            if ($node === null || $node->id === null) {
                continue;
            }
            $nodes[$node->id] = $node;
            $row = $record[PageRow::class] ?? null;
            // An alias names its original's row; that row is the original's
            // to change, not this branch's.
            if ($row !== null && ($node->relationId('originalNode') ?? 0) === 0) {
                $rows[$node->id] = $row;
            }
        }
        return new self(array_values($nodes), $rows);
    }
}
