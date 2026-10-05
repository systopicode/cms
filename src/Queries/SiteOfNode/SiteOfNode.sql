-- The site a node belongs to: the nearest node of type 'site' on its way up
-- (the node itself included, unless :skipSelf). No row: the default site.
--
-- A loose value, not a node — the caller wants the id, and the answer is
-- cached per request by Pages\Sites\NodeSite.

WITH RECURSIVE chain (node_id, parent_id, node_type, depth) AS (
	SELECT node_id, node_parent_node_id, node_type, 0 FROM cms_nodes WHERE node_id = :id
	UNION ALL
	SELECT n.node_id, n.node_parent_node_id, n.node_type, c.depth + 1
	FROM cms_nodes n INNER JOIN chain c ON n.node_id = c.parent_id
	WHERE c.depth < 32
)
SELECT node_id AS chain_site_node_id
FROM chain
WHERE node_type = :type AND depth >= :skipSelf
ORDER BY depth
LIMIT 1

-- :id
-- :type
-- :skipSelf
