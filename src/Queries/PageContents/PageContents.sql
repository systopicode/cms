-- Every page row, every language, the trash too - for tools over all content.

SELECT cms_pages.*
FROM cms_pages
ORDER BY page_node_id, page_language_iso
