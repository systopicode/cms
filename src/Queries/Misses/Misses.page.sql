SELECT cms_pages.*
FROM cms_pages
INNER JOIN cms_nodes ON node_id = page_node_id AND node_is_trashed = 0 AND node_type = 'page'
WHERE page_node_id = :node
ORDER BY page_language_iso = :lang DESC, page_id
LIMIT 1
