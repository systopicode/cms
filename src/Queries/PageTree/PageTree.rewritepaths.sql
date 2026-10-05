-- Every speaking path of several pages in one statement.
--
-- `pageRewritepath::getRewritepaths($page_id)` asks per page. In the sidebar
-- that is one page and goes unnoticed; as soon as a list needs the paths it is
-- one statement per row.
--
-- Splitting into `auto` and `custom` is done in PHP by the legacy code, via
-- `is_null($rewritepath->auto_index)`. That stays — it is a question for the
-- row, not for the database.

SELECT cms_rewritepaths.*
FROM cms_rewritepaths
WHERE rewritepath_page_id IN (:pageIds)
ORDER BY rewritepath_page_id, rewritepath_is_primary DESC, rewritepath_auto_index

-- :pageIds
