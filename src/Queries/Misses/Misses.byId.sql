SELECT cms_strays.*
FROM cms_strays
WHERE stray_id = :id
LIMIT 1
