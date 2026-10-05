-- The page behind a path — what the router asks first.
--
-- Within one site (:site = its node id, NULL for the default site), or in
-- every site when :anySite is set. The page comes in the same row, so the
-- router gets node and language without a second statement. The primary
-- path first: a path that is a page's primary one and another page's
-- duplicate at once is answered with the page it belongs to.

SELECT cms_rewritepaths.*, page_id, page_node_id, page_language_iso
FROM cms_rewritepaths
JOIN cms_pages ON page_id = rewritepath_page_id
WHERE rewritepath_string = :path
	AND ( :anySite OR NULLIF(rewritepath_site_node_id, 0) <=> :site )
ORDER BY rewritepath_is_primary DESC, rewritepath_id
LIMIT 1

-- :path
-- :anySite
-- :site
