-- The first page of a site — its home when it has no '' path of its own.
--
-- :site is the site's node id; NULL asks the topmost level (the default
-- site), where 0 is the spelling of rows from before the self-referencing
-- foreign key.

SELECT cms_nodes.*
FROM cms_nodes
WHERE COALESCE(node_parent_node_id, 0) = COALESCE(:site, 0)
	AND node_type = 'page'
	AND !node_is_trashed
ORDER BY node_ordering
LIMIT 1

-- :site
