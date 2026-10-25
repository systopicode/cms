<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Pages;

use Systopic\Cms\Queries\Branch\Branch;
use Systopic\Cms\Queries\Params;
use Systopic\Cms\Queries\RewritepathIndex\RewritepathIndex;
use Systopic\Cms\Tables\KeywordsInPages\Model as KeywordInPage;
use Systopic\Cms\Tables\Rewritepaths\Removal as PathRemoval;
use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\System\Queries\MediaNodes\MediaNodes;
use Systopic\System\Tables\Junctions\Model as Junction;
use Systopic\System\Tables\Medias\Removal as MediaRemoval;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Requests\Model as Request;
use Systopic\System\Tables\TagsInNodes\Model as TagInNode;
use Systopic\System\Tables\Texts\Model as Text;

/**
 * A branch out of the trash for good: its nodes, pages in every language,
 * texts, settings, tags, keywords, rewrite paths and media with their files.
 *
 * The tables have no foreign keys to cascade, so every one that names a node
 * or page is cleared here. The request log keeps its rows and loses the link.
 * An alias or redirect outside the branch that points into it would be left
 * pointing at nothing: then nothing is removed.
 */
final class Removal
{
    /**
     * @return int|string how many nodes went, or why none did
     */
    public static function branch(Scope $scope, int $nodeId): int|string
    {
        $ids = [];
        $trashed = false;
        foreach (Branch::of($scope, new Params(lang: ''), $nodeId)->nodes as $node) {
            $ids[] = (int) $node->id;
            $trashed = $trashed || ((int) $node->id === $nodeId && $node->isTrashed);
        }
        if (!$trashed) {
            return 'This page is not in the trash.';
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $nodeTable = Schema::tableNameOf(Node::class);
        $pointers = (int) $scope->connection->run(
            "SELECT COUNT(*) FROM $nodeTable WHERE node_id NOT IN ($marks) AND (node_redirect_node_id IN ($marks) OR node_original_node_id IN ($marks))",
            [...$ids, ...$ids, ...$ids]
        )->fetchColumn();
        if ($pointers > 0) {
            return 'An alias or redirect elsewhere still points to this page. Remove it first.';
        }

        MediaRemoval::remove($scope, ...array_values(MediaNodes::byNodeIds($scope, $ids)));
        PathRemoval::remove($scope, ...RewritepathIndex::ofNodes($scope, $ids));

        $pages = Schema::tableNameOf(Model::class);
        $tables = [
            $pages, $nodeTable,
            $texts = Schema::tableNameOf(Text::class),
            $junctions = Schema::tableNameOf(Junction::class),
            $tags = Schema::tableNameOf(TagInNode::class),
            $keywords = Schema::tableNameOf(KeywordInPage::class),
            $requests = Schema::tableNameOf(Request::class),
        ];
        $scope->connection->transactional(static function ($connection) use ($ids, $marks, $pages, $nodeTable, $texts, $junctions, $tags, $keywords, $requests): void {
            $pageIds = "SELECT page_id FROM $pages WHERE page_node_id IN ($marks)";
            $connection->run("UPDATE $requests SET request_page_id = NULL WHERE request_page_id IN ($pageIds)", $ids);
            $connection->run("DELETE FROM $keywords WHERE kip_page_id IN ($pageIds)", $ids);
            $connection->run("DELETE FROM $texts WHERE text_node_id IN ($marks)", $ids);
            $connection->run("DELETE FROM $junctions WHERE junction_node_id IN ($marks)", $ids);
            $connection->run("DELETE FROM $tags WHERE tin_node_id IN ($marks)", $ids);
            $connection->run("DELETE FROM $pages WHERE page_node_id IN ($marks)", $ids);
            $connection->run("DELETE FROM $nodeTable WHERE node_id IN ($marks)", $ids);
        });
        $scope->results->invalidate($tables);
        return count($ids);
    }
}
