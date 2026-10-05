-- Every rewrite path with its page and node, the way the maintenance list
-- shows them: primary first, the start page's empty path last among them.

SELECT cms_rewritepaths.*, cms_pages.*, cms_nodes.*
FROM cms_rewritepaths
LEFT JOIN cms_pages ON page_id = rewritepath_page_id
LEFT JOIN cms_nodes ON node_id = page_node_id
ORDER BY
    rewritepath_is_primary DESC,
    rewritepath_string = '',
    rewritepath_auto_index = 0,
    rewritepath_auto_index DESC,
    page_is_navi_element DESC,
    node_ordering
LIMIT 500

