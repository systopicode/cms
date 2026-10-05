-- The page a path belongs to.

SELECT page_id, page_node_id, page_language_iso, page_label
FROM cms_rewritepaths
JOIN cms_pages ON page_id = rewritepath_page_id
WHERE rewritepath_id = :id

-- :id
