-- The paths of the pages of some nodes, every language.

SELECT cms_rewritepaths.*
FROM cms_rewritepaths
JOIN cms_pages ON page_id = rewritepath_page_id
WHERE page_node_id IN (:nodeIds)

-- :nodeIds
