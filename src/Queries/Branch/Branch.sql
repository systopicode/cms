-- A node and everything below it — pages, media, whatever hangs there — with
-- the page row of one language where there is one.
--
-- For what works on a whole branch: publishing it, putting it into the trash,
-- getting it back out. Trashed nodes are part of it; recovering needs them.

WITH RECURSIVE branch (id) AS (
	SELECT node_id FROM cms_nodes WHERE node_id = :nodeId
	UNION ALL
	SELECT child.node_id
	FROM cms_nodes child
	JOIN branch ON child.node_parent_node_id = branch.id
)
SELECT
	cms_nodes.*,
	cms_pages.*
FROM branch
JOIN cms_nodes ON node_id = branch.id
LEFT JOIN cms_pages ON
	page_node_id = COALESCE(NULLIF(node_original_node_id, 0), node_id)
	AND page_language_iso = :lang
ORDER BY node_id

-- :nodeId
-- :lang
