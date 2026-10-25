-- The newest media out of the trash, with the page whose library holds them.

WITH RECURSIVE live (node_id) AS (
	SELECT node_id FROM cms_nodes WHERE COALESCE(node_parent_node_id, 0) = 0 AND node_is_trashed = 0
	UNION ALL
	SELECT n.node_id FROM cms_nodes n INNER JOIN live l ON n.node_parent_node_id = l.node_id
	WHERE n.node_is_trashed = 0
)
SELECT
	node.node_id AS dash_node_id,
	node.node_parent_node_id AS dash_page_id
FROM cms_medias
INNER JOIN cms_nodes AS node ON node.node_id = media_node_id AND node.node_type = 'media'
INNER JOIN live ON live.node_id = node.node_id
WHERE media_type IN ('image', 'video')
ORDER BY media_created_date DESC, media_id DESC
LIMIT 8
