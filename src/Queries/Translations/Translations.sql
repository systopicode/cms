-- Every language of one content node. Whole rows — a duplicate copies them.

SELECT cms_pages.*
FROM cms_pages
WHERE page_node_id = :nodeId
ORDER BY page_language_iso

-- :nodeId
