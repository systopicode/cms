SELECT cms_strays.*
FROM cms_strays
WHERE stray_path = :path AND stray_site_node_id <=> :site
LIMIT 1
