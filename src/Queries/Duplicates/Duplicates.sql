-- Rows that exist twice where they should exist once: a page per node and
-- language, a crop per media and format. Two result sets, every copy listed.

SELECT cms_pages.*
FROM cms_pages
JOIN (
    SELECT page_node_id AS n, page_language_iso AS l
    FROM cms_pages
    GROUP BY page_node_id, page_language_iso
    HAVING COUNT(*) > 1
) twice ON twice.n = page_node_id AND twice.l <=> page_language_iso
ORDER BY page_node_id, page_language_iso, page_id;

SELECT cms_media_crops.*, cms_medias.media_id, cms_medias.media_label
FROM cms_media_crops
JOIN (
    SELECT media_crop_media_id AS m, media_crop_format AS f
    FROM cms_media_crops
    GROUP BY media_crop_media_id, media_crop_format
    HAVING COUNT(*) > 1
) twice ON twice.m <=> media_crop_media_id AND twice.f <=> media_crop_format
LEFT JOIN cms_medias ON media_id = media_crop_media_id
ORDER BY media_crop_media_id, media_crop_format, media_crop_id;
