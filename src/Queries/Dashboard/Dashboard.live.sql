-- The figures of the dashboard: pages (hidden, without description, planned,
-- edited in the last 7 days), media (without a label), tags and the trash. Only what is out of the trash counts - the
-- children of a trashed branch carry no flag of their own, so the tree is
-- walked from the top.

WITH RECURSIVE live (node_id) AS (
	SELECT node_id FROM cms_nodes WHERE COALESCE(node_parent_node_id, 0) = 0 AND node_is_trashed = 0
	UNION ALL
	SELECT n.node_id FROM cms_nodes n INNER JOIN live l ON n.node_parent_node_id = l.node_id
	WHERE n.node_is_trashed = 0
)
SELECT
	(SELECT COUNT(*) FROM cms_nodes n INNER JOIN live USING (node_id) WHERE n.node_type = 'page') AS dash_pages,
	(SELECT COUNT(DISTINCT page_node_id) FROM cms_pages INNER JOIN live ON live.node_id = page_node_id WHERE page_is_published = 0) AS dash_hidden,
	(SELECT COUNT(DISTINCT page_node_id) FROM cms_pages INNER JOIN live ON live.node_id = page_node_id
		INNER JOIN cms_nodes n ON n.node_id = page_node_id AND n.node_type = 'page'
		WHERE page_is_published = 1 AND COALESCE(TRIM(page_meta_description), '') = '') AS dash_nodescription,
	(SELECT COUNT(DISTINCT page_node_id) FROM cms_pages INNER JOIN live ON live.node_id = page_node_id
		WHERE page_start_publishing > NOW()) AS dash_planned,
	(SELECT COUNT(DISTINCT page_node_id) FROM cms_pages INNER JOIN live ON live.node_id = page_node_id
		INNER JOIN cms_nodes n ON n.node_id = page_node_id AND n.node_type = 'page'
		WHERE page_modified_date >= UTC_TIMESTAMP() - INTERVAL 7 DAY) AS dash_week,
	(SELECT COUNT(*) FROM cms_nodes n INNER JOIN live USING (node_id) WHERE n.node_type = 'media') AS dash_media,
	(SELECT COUNT(*) FROM cms_medias INNER JOIN live ON live.node_id = media_node_id
		WHERE media_type <> 'folder' AND COALESCE(TRIM(media_label), '') = '') AS dash_noalt,
	(SELECT COUNT(*) FROM cms_tags) AS dash_tags,
	(SELECT COUNT(*) FROM cms_nodes t LEFT JOIN cms_nodes p ON p.node_id = t.node_parent_node_id
		WHERE t.node_is_trashed = 1 AND COALESCE(p.node_is_trashed, 0) = 0) AS dash_trashed
