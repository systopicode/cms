-- Several nodes by id — `page::get()` in bulk.
--
-- The legacy code fetches every page on its own: `page::get()` calls
-- `page::build()`, and that is one statement per id, two on a miss. The
-- placeholders in texts (`?id=42&lang=de`) resolve exactly that way — a text
-- with five references costs five statements.
--
-- Columns and conditions as in PageTree.sql, so both produce the same plan
-- and the identity map hands back the same objects.

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
FROM cms_nodes
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
	node_id IN (:nodeIds)
	AND ( page_is_published OR :preview )
	AND ( page_start_publishing <= NOW() OR :preview OR ISNULL(page_start_publishing) )
	AND ( page_end_publishing >= NOW() OR :preview OR ISNULL(page_end_publishing) )
	AND ( ISNULL(:nodeType) OR node_type = :nodeType OR ( :nodeType = 'page' AND node_type = 'site' ) )
	AND ( !node_is_trashed OR :showtrashed )
ORDER BY node_ordering

-- :nodeIds
-- :lang
-- :preview
-- :nodeType
-- :showtrashed
