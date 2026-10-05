-- The way from a page up to the topmost level — in one statement.
--
-- The legacy code walks it node by node: `page::getParent()` calls
-- `page::get($node_parent_node_id)`, which is one statement per step, plus two
-- for the root row with id 1 that does not exist at all.
-- `PageBuilder::buildRoute()` does the same thing over again.
--
-- MySQL 8 can do it itself. The recursion runs upwards, `depth` counts along
-- and bounds it at the same time: a cycle in `node_parent_node_id` ends after
-- 32 steps instead of hanging the process.
--
-- The topmost level carries `node_parent_node_id = 0`, and there is no node
-- with id 0 — the recursion ends there by itself. The row with id 1 is
-- therefore never fetched, and that is the point: in the legacy code it never
-- contributed anything except two statements.
--
-- Columns as in PageTree.sql, so both produce the same plan.

WITH RECURSIVE chain (chain_node_id, chain_parent_id, depth) AS (
	SELECT node_id, node_parent_node_id, 0
	FROM cms_nodes
	WHERE node_id = :node_id
	UNION ALL
	SELECT n.node_id, n.node_parent_node_id, c.depth + 1
	FROM cms_nodes n
	INNER JOIN chain c ON n.node_id = c.chain_parent_id
	WHERE c.depth < 32
)
SELECT
	cms_nodes.*,
	page_id,
	page_node_id,
	page_language_iso,
	page_label,
	page_is_published,
	page_is_navi_element,
	page_template_name,
	rewritepath_id,
	rewritepath_page_id,
	rewritepath_string,
	rewritepath_is_primary,
	child_node_parent_node_id,
	alias_of
FROM chain
INNER JOIN cms_nodes ON cms_nodes.node_id = chain.chain_node_id
LEFT JOIN cms_pages ON
	page_node_id = COALESCE(NULLIF(node_original_node_id, 0), node_id)
	AND ( page_language_iso = :lang OR page_language_iso = '*' )
LEFT JOIN cms_rewritepaths ON
	rewritepath_page_id = page_id AND rewritepath_is_primary
LEFT JOIN (
	SELECT DISTINCT node_parent_node_id AS child_node_parent_node_id
	FROM cms_nodes WHERE node_type IN ('page', 'site') AND !node_is_trashed
) children ON child_node_parent_node_id = node_id
-- "Are there aliases of this node?" — again one number per row, not a
-- collection. The tree fetches the alias nodes in a statement of its own
-- (route D from section 6), but only for the nodes that have any. Without this
-- column the statement would run even when there is nothing to fetch.
LEFT JOIN (
	SELECT DISTINCT node_original_node_id AS alias_of
	FROM cms_nodes WHERE COALESCE(node_original_node_id, 0) != 0 AND node_type = 'page' AND !node_is_trashed
) aliases ON alias_of = node_id
WHERE
	( page_is_published OR :preview )
	AND ( page_start_publishing <= NOW() OR :preview OR ISNULL(page_start_publishing) )
	AND ( page_end_publishing >= NOW() OR :preview OR ISNULL(page_end_publishing) )
	AND ( ISNULL(:nodeType) OR node_type = :nodeType OR ( :nodeType = 'page' AND node_type = 'site' ) )
	AND ( !node_is_trashed OR :showtrashed )
ORDER BY chain.depth DESC

-- :node_id
-- :lang
-- :preview
-- :nodeType
-- :showtrashed
