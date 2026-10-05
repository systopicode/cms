-- Nodes in the trash whose parent is not: the tops of trashed branches, with
-- their page rows in every language.

SELECT cms_nodes.*, cms_pages.*
FROM cms_nodes
JOIN cms_pages ON page_node_id = cms_nodes.node_id
LEFT JOIN cms_nodes AS parent ON parent.node_id = cms_nodes.node_parent_node_id
WHERE cms_nodes.node_is_trashed = 1 AND COALESCE(parent.node_is_trashed, 0) = 0
ORDER BY cms_nodes.node_id, cms_pages.page_language_iso
