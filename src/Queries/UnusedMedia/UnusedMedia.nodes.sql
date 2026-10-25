SELECT
	node_id AS id,
	COALESCE(node_parent_node_id, 0) AS parent,
	COALESCE(node_original_node_id, 0) AS original,
	node_type AS type,
	node_is_trashed AS trashed
FROM cms_nodes
