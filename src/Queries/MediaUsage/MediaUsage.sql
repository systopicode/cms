SELECT page_node_id AS node, page_language_iso AS lang, page_label AS label,
 page_meta_share_image AS share,
 CONCAT_WS(' ', page_html01,page_html02,page_html03,page_html04,page_html05,
 page_html06,page_html07,page_html08,page_html09,page_text01,page_text02,page_text03,
 page_text04,page_text05,page_text06,page_text07,page_text08,page_text09) AS content
FROM cms_pages INNER JOIN cms_nodes ON node_id = page_node_id
WHERE node_is_trashed = 0
ORDER BY page_label, page_language_iso
