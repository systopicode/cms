-- The paths of pages in the trash.

SELECT cms_rewritepaths.*
FROM cms_rewritepaths
JOIN cms_pages ON page_id = rewritepath_page_id
JOIN cms_nodes ON node_id = page_node_id
WHERE node_is_trashed = 1
