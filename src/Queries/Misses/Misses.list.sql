SELECT cms_strays.*
FROM cms_strays
WHERE stray_path <> ''
ORDER BY stray_hits DESC, stray_last_at DESC
LIMIT 100
