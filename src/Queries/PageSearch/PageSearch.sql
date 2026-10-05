-- Pages whose label contains a word, not in the trash.

SELECT page_id, page_node_id, page_label, page_language_iso
FROM cms_pages
JOIN cms_nodes ON node_id = page_node_id
WHERE LOWER(page_label) LIKE :term
	AND page_language_iso = :lang
	AND !node_is_trashed
ORDER BY page_label

-- :term
-- :lang
