-- Real media files that are not in the trash. Aliases are left out:
-- they share another medium's files.

SELECT
	media_id AS id,
	media_node_id AS node,
	COALESCE(node_parent_node_id, 0) AS parent,
	media_filename AS filename,
	COALESCE(media_type, '') AS type
FROM cms_medias
INNER JOIN cms_nodes ON node_id = media_node_id
WHERE node_is_trashed = 0
	AND COALESCE(node_original_node_id, 0) = 0
