-- Pages out of the trash, newest change first, with who changed them. Every
-- language is a row; the caller keeps the first of a node.
--
-- :filter  'recent'       every page
--          'nodescription' published, without a meta description
--          'hidden'        not published
--          'planned'       published from a date still to come

WITH RECURSIVE live (node_id) AS (
	SELECT node_id FROM cms_nodes WHERE COALESCE(node_parent_node_id, 0) = 0 AND node_is_trashed = 0
	UNION ALL
	SELECT n.node_id FROM cms_nodes n INNER JOIN live l ON n.node_parent_node_id = l.node_id
	WHERE n.node_is_trashed = 0
)
SELECT
	page_node_id AS dash_node_id,
	page_label AS dash_label,
	page_language_iso AS dash_language,
	page_is_published AS dash_published,
	page_start_publishing AS dash_start,
	page_modified_date AS dash_modified,
	COALESCE(NULLIF(user_fullname, ''), user_name) AS dash_user
FROM cms_pages
INNER JOIN live ON live.node_id = page_node_id
INNER JOIN cms_nodes AS node ON node.node_id = page_node_id AND node.node_type = 'page'
LEFT JOIN usm_users ON user_id = page_modified_user_id
CROSS JOIN (SELECT :filter AS f) AS wanted
WHERE wanted.f = 'recent'
	OR (wanted.f = 'nodescription' AND page_is_published = 1 AND COALESCE(TRIM(page_meta_description), '') = '')
	OR (wanted.f = 'hidden' AND page_is_published = 0)
	OR (wanted.f = 'planned' AND page_start_publishing > NOW())
ORDER BY page_modified_date DESC, page_node_id DESC
LIMIT 40

-- :filter
