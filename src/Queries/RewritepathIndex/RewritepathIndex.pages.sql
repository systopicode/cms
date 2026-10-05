-- Every page that should have an automatic path: not trashed, of type page.

SELECT cms_nodes.node_id, cms_pages.page_id, cms_pages.page_language_iso
FROM cms_pages
JOIN cms_nodes ON node_id = page_node_id
WHERE node_type = 'page' AND node_is_trashed = 0
ORDER BY node_id, page_language_iso
