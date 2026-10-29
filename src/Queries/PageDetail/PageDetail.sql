-- One page, whole — the query behind `Page::load()`.
--
-- The level queries are lean on purpose: a tree needs a label and a flag, not
-- nine HTML fields. The editor needs all of it. Running this through the same
-- identity map as the tree means the node and page the tree already holds
-- come back as the same objects, completed with the columns missing so far.
--
-- No publishing and no trash filter: the CMS edits what the site hides.

SELECT
	cms_nodes.*,
	cms_pages.*,
	child_node_parent_node_id,
	alias_of
FROM cms_nodes
LEFT JOIN cms_pages ON
	page_node_id = COALESCE(NULLIF(node_original_node_id, 0), node_id)
	AND ( page_language_iso = :lang OR page_language_iso = '*' )
LEFT JOIN (
	SELECT DISTINCT node_parent_node_id AS child_node_parent_node_id
	FROM cms_nodes WHERE node_type = 'page' AND !node_is_trashed
) children ON child_node_parent_node_id = node_id
LEFT JOIN (
	SELECT DISTINCT node_original_node_id AS alias_of
	FROM cms_nodes WHERE COALESCE(node_original_node_id, 0) != 0 AND node_type = 'page' AND !node_is_trashed
) aliases ON alias_of = node_id
WHERE node_id = :nodeId
	-- a page the viewer may not read does not open (Params::$readAcls)
	AND ( :aclAll OR node_acl_id IN (:readAcls) OR ( :aclOwner > 0 AND node_user_id = :aclOwner ) )

-- :nodeId
-- :lang
-- :aclAll
-- :readAcls
-- :aclOwner
