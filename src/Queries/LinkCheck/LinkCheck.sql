-- The html fields of every page out of the trash that link or embed
-- something, one row per page and language.

WITH RECURSIVE live (node_id) AS (
	SELECT node_id FROM cms_nodes WHERE COALESCE(node_parent_node_id, 0) = 0 AND node_is_trashed = 0
	UNION ALL
	SELECT n.node_id FROM cms_nodes n INNER JOIN live l ON n.node_parent_node_id = l.node_id
	WHERE n.node_is_trashed = 0
)
SELECT
	page_node_id AS lc_node_id,
	page_language_iso AS lc_lang,
	page_label AS lc_label,
	CONCAT_WS(' ', page_html01, page_html02, page_html03, page_html04, page_html05,
		page_html06, page_html07, page_html08, page_html09) AS lc_html
FROM cms_pages
INNER JOIN live ON live.node_id = page_node_id
INNER JOIN cms_nodes AS node ON node.node_id = page_node_id AND node.node_type = 'page'
HAVING lc_html LIKE '%href=%' OR lc_html LIKE '%src=%' OR lc_html LIKE '%data-node_id%'
ORDER BY page_node_id, page_language_iso
