-- Every path of the installation onto its site — the maintenance button.
--
-- Down from the topmost level: a node of type 'site' starts its site,
-- everything else inherits its parent's. Pages whose node is gone get NULL.

UPDATE cms_rewritepaths
JOIN cms_pages ON page_id = rewritepath_page_id
LEFT JOIN (
	WITH RECURSIVE tree (node_id, site, depth) AS (
		SELECT node_id, IF(node_type = :type, node_id, NULL), 0
		FROM cms_nodes WHERE COALESCE(node_parent_node_id, 0) = 0
		UNION ALL
		SELECT n.node_id, IF(n.node_type = :type2, n.node_id, t.site), t.depth + 1
		FROM cms_nodes n INNER JOIN tree t ON n.node_parent_node_id = t.node_id
		WHERE t.depth < 32
	)
	SELECT node_id, site FROM tree
) tree ON tree.node_id = page_node_id
SET rewritepath_site_node_id = tree.site
WHERE NOT (rewritepath_site_node_id <=> tree.site)
