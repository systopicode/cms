-- What a link may lead to: every node out of the trash, and the paths of
-- the pages among them.

WITH RECURSIVE live (node_id) AS (
	SELECT node_id FROM cms_nodes WHERE COALESCE(node_parent_node_id, 0) = 0 AND node_is_trashed = 0
	UNION ALL
	SELECT n.node_id FROM cms_nodes n INNER JOIN live l ON n.node_parent_node_id = l.node_id
	WHERE n.node_is_trashed = 0
)
SELECT live.node_id AS lc_node_id, rewritepath_string AS lc_path
FROM live
LEFT JOIN cms_pages ON page_node_id = live.node_id
LEFT JOIN cms_rewritepaths ON rewritepath_page_id = page_id AND COALESCE(rewritepath_string, '') <> ''
