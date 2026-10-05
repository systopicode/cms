-- The aliases of several nodes — as rows, not as a packed column.
--
-- `tree.sql` fetches the same thing with `GROUP_CONCAT(node_id) AS alias_ids`.
-- That is route A from section 6 of the design: no table in
-- `getColumnMeta()`, hence a loose value, and the length depends on
-- `group_concat_max_len`. Here they are real rows out of `cms_nodes` —
-- route D — so the mapping from section 3 applies unchanged and the alias
-- nodes are the same objects as everywhere else.
--
-- The tree only needs their ids for its icons; whoever wants more has the
-- whole node.

SELECT cms_nodes.*
FROM cms_nodes
WHERE node_original_node_id IN (:originalIds)
	AND node_type = 'page'
	AND ( !node_is_trashed OR :showtrashed )
-- By `node_id`, not by `node_ordering`: the aliases of a node sit at different
-- places in the tree, so their ordering there says nothing about each other.
-- `tree.sql` has no ORDER BY at all inside its GROUP_CONCAT — the order is
-- undefined there and in practice comes out of the scan order, which amounts to
-- the same thing. Here it is written down.
ORDER BY node_original_node_id, node_id

-- :originalIds
-- :showtrashed
