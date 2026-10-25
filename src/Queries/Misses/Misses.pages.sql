SELECT page_node_id AS node, page_label AS label
FROM cms_pages
INNER JOIN cms_nodes ON node_id = page_node_id AND node_is_trashed = 0 AND node_type = 'page'
WHERE page_language_iso = :lang OR page_language_iso = '*'
ORDER BY page_label
