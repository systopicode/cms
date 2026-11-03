-- The children of **several** parents in one statement.
--
-- An additional file beside pages.sql, not a replacement — the same rule as
-- with TagGroups.sql. Two differences to pages.sql, both measured:
--
-- 1. `node_parent_node_id IN (:parentIds)` instead of
--    `= :node_parent_node_id`. The legacy code asks per node; one call of
--    cms/pages/?id=19 came to 15 statements for three levels. With the list it
--    is three.
--
-- 2. 22 columns instead of 68. The anchor list of a collection uses three
--    fields per page (label, rewritepath, is_navi_element) — so far all of
--    them were loaded. What is missing here the LoadGroup fetches on access,
--    for the whole group at once.
--
-- The conditions are character for character those of pages.sql: the same
-- visibility, the same language choice, the same trash rule. `:nodeType` is
-- NULL-able so the comparison against the legacy code does not hang on an
-- extra condition.

SELECT
	cms_nodes.*,
	page_id,
	page_node_id,
	page_language_iso,
	page_label,
	page_is_published,
	page_is_navi_element,
	page_template_name,
	rewritepath_id,
	rewritepath_page_id,
	rewritepath_string,
	rewritepath_is_primary,
	child_node_parent_node_id,
	alias_of
FROM cms_nodes
LEFT JOIN cms_pages ON
	page_node_id = COALESCE(NULLIF(node_original_node_id, 0), node_id)
	AND ( page_language_iso = :lang OR page_language_iso = '*' )
LEFT JOIN cms_rewritepaths ON
	rewritepath_page_id = page_id AND rewritepath_is_primary
-- "Does this node have children?" — the same derived table as in tree.sql.
-- One number per row, not a collection: the tree must be able to draw its fold
-- icon without loading the children. `DISTINCT` keeps it at one row per parent,
-- so the LEFT JOIN cannot multiply.
LEFT JOIN (
	SELECT DISTINCT node_parent_node_id AS child_node_parent_node_id
	FROM cms_nodes WHERE node_type IN ('page', 'site') AND !node_is_trashed
) children ON child_node_parent_node_id = node_id
-- "Are there aliases of this node?" — again one number per row, not a
-- collection. The tree fetches the alias nodes in a statement of its own
-- (route D from section 6), but only for the nodes that have any. Without this
-- column the statement would run even when there is nothing to fetch.
LEFT JOIN (
	SELECT DISTINCT node_original_node_id AS alias_of
	FROM cms_nodes WHERE COALESCE(node_original_node_id, 0) != 0 AND node_type = 'page' AND !node_is_trashed
) aliases ON alias_of = node_id
WHERE
	-- A root carries NULL, not 0. `IN (…)` never matches NULL — not even
	-- `IN (NULL)` — so the top level needs a branch of its own rather than a
	-- magic parent id. `:roots` is 1 exactly for the topmost call.
	-- A root is spelled NULL once the foreign key exists and 0 before that.
	-- `COALESCE(…, 0) = 0` accepts both, and that is deliberate: the schema
	-- migration and the code then stop depending on each other's order. `IN`
	-- never matches NULL — not even `IN (NULL)` — so the top level needs this
	-- branch of its own either way.
	( node_parent_node_id IN (:parentIds) OR ( :roots AND COALESCE(node_parent_node_id, 0) = 0 ) )
	-- The untyped row with id 1 (record::getDefaultRecord()'s template) is
	-- not a node of the tree.
	AND !ISNULL(node_type)
	AND ( page_is_published OR :preview )
	AND ( page_start_publishing <= NOW() OR :preview OR ISNULL(page_start_publishing) )
	AND ( page_end_publishing >= NOW() OR :preview OR ISNULL(page_end_publishing) )
	AND ( ISNULL(:nodeType) OR node_type = :nodeType OR ( :nodeType = 'page' AND node_type = 'site' ) )
	AND ( !node_is_trashed OR :showtrashed )
	-- who may see the node: its ACL among the viewer's (Params::$readAcls; NULL
	-- there reads everything), or the viewer owns it
	AND ( :aclAll OR node_acl_id IN (:readAcls) OR ( :aclOwner > 0 AND node_user_id = :aclOwner ) )
ORDER BY node_parent_node_id, node_ordering

-- :parentIds
-- :roots
-- :lang
-- :preview
-- :nodeType
-- :showtrashed
-- :aclAll
-- :readAcls
-- :aclOwner
