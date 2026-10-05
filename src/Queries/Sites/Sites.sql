-- Every site. The default site (no node) first, the others in tree order.
--
-- `site_name <> ''` leaves out row 1: app.php runs db::createDefaultRecords()
-- once per login, and that puts an empty row with id 1 into every table of
-- the naming convention — cms_sites included. It is no site.
--
-- The columns are named, not `cms_sites.*`: a column the table does not have
-- yet (site_aliases, 2026-10-02) then fails with 1054, and the provisioner
-- adds it — on every stage, at the first request. `*` would just leave it out.

SELECT site_id, site_node_id, site_name, site_meta, site_aliases
FROM cms_sites
LEFT JOIN cms_nodes ON node_id = site_node_id
WHERE site_name <> ''
ORDER BY ISNULL(site_node_id) DESC, node_ordering, site_id
