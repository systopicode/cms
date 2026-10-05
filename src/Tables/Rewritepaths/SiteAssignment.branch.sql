-- After a branch moved: every path in it gets the site of its new place.
--
-- :parentSite is the site the branch's new parent belongs to (NULL = the
-- default site). The branch top keeps its own id when it is a site itself,
-- and so does every site deeper down — a moved site carries its pages along
-- and leaves nested sites alone.

UPDATE cms_rewritepaths
JOIN cms_pages ON page_id = rewritepath_page_id
JOIN (
	WITH RECURSIVE branch (node_id, site, depth) AS (
		SELECT node_id, IF(node_type = :type, node_id, :parentSite), 0
		FROM cms_nodes WHERE node_id = :root
		UNION ALL
		SELECT n.node_id, IF(n.node_type = :type2, n.node_id, b.site), b.depth + 1
		FROM cms_nodes n INNER JOIN branch b ON n.node_parent_node_id = b.node_id
		WHERE b.depth < 32
	)
	SELECT node_id, site FROM branch
) branch ON branch.node_id = page_node_id
SET rewritepath_site_node_id = branch.site
WHERE NOT (rewritepath_site_node_id <=> branch.site)
