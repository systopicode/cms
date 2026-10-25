SELECT CONCAT_WS(' ', page_html01, page_html02, page_html03, page_html04, page_html05,
	page_html06, page_html07, page_html08, page_html09) AS html
FROM cms_pages
INNER JOIN cms_nodes ON node_id = page_node_id AND node_is_trashed = 0
