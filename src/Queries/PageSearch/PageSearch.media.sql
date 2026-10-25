-- Media with a word in their label, file name or keywords, not in the trash,
-- with the page whose library holds them.

SELECT
	media_node_id AS ps_node_id,
	node_parent_node_id AS ps_page_id,
	media_label AS ps_label,
	media_filename AS ps_file,
	media_type AS ps_type
FROM cms_medias
JOIN cms_nodes ON node_id = media_node_id
WHERE !node_is_trashed
	AND LOWER(CONCAT_WS(' ', media_label, media_filename, media_keywords)) LIKE :term
ORDER BY media_label
LIMIT 8

-- :term
