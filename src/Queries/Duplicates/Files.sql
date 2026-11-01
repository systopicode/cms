SELECT cms_medias.* FROM cms_medias
WHERE media_filename IS NOT NULL AND media_filename <> '' AND (media_type IS NULL OR media_type <> 'folder')
ORDER BY media_id
