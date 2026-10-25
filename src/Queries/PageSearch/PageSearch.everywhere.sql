-- Pages with a word in their title, description or texts, any language, not
-- in the trash. Title hits first; one row per page and language.

SELECT
	page_node_id AS ps_node_id,
	page_language_iso AS ps_lang,
	page_label AS ps_label,
	LOWER(page_label) LIKE :term AS ps_in_label
FROM cms_pages
JOIN cms_nodes ON node_id = page_node_id AND node_type = 'page'
WHERE !node_is_trashed
	AND LOWER(CONCAT_WS(' ', page_label, page_meta_description,
		page_text01, page_text02, page_text03, page_text04, page_text05, page_text06, page_text07, page_text08, page_text09,
		page_html01, page_html02, page_html03, page_html04, page_html05, page_html06, page_html07, page_html08, page_html09)) LIKE :term
ORDER BY ps_in_label DESC, page_label
LIMIT 60

-- :term
