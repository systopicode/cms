-- A path of another page with this string — the first one, if any.
--
-- Within one site (:site = its node id, NULL for the default site), or in
-- every site when :anySite is set.

SELECT cms_rewritepaths.*
FROM cms_rewritepaths
WHERE rewritepath_string = :path
	AND rewritepath_page_id <> :pageId
	AND ( :anySite OR NULLIF(rewritepath_site_node_id, 0) <=> :site )
ORDER BY rewritepath_id
LIMIT 1

-- :path
-- :pageId
-- :anySite
-- :site
