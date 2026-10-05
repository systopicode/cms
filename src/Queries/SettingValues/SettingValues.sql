-- Die Werte, die an Knoten haengen — eine Abfrage fuer beliebig viele.
--
-- Der Nachfolger des `LEFT JOIN cms_junctions ON junction_node_id =
-- :page_node_id` aus `pageSettings.sql`. Dort haengt der Wert an derselben
-- Abfrage wie die Deklaration, also wird die ganze Deklaration je Seite neu
-- gelesen. Hier sind es zwei Fragen und zwei Abfragen: was es gibt, und was
-- diese Seite davon gesetzt hat.
--
-- `IN (:nodeIds)` statt `= :page_node_id`: eine Liste kostet dasselbe wie ein
-- einzelner Wert, und eine Uebersicht ueber 30 Seiten braucht dann trotzdem
-- nur eine Abfrage. `Rows` ersetzt den Platzhalter textuell — binden laesst
-- sich eine Liste nicht.

SELECT
	junction_node_id,
	junction_setting_node_id,
	junction_varchar,
	junction_int,
	junction_decimal

FROM cms_junctions
WHERE junction_node_id IN (:nodeIds)
