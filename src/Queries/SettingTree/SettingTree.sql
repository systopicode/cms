-- Alle Setting-Deklarationen in einer Abfrage.
--
-- Der Nachfolger von `settings.sql` + `setting::getChildren()`. Dort ist der
-- Baum eine Abfrage je Ebene: `setting::get(1)`, dann die Kinder der Wurzel,
-- dann je Kind noch einmal. Hier kommt der ganze Baum auf einmal, und der
-- Zusammenbau laeuft ueber `node_parent_node_id`, den die Kindzeile ohnehin
-- traegt.
--
-- Drei echte Tabellen, keine abgeleiteten Aliase, keine Concat-Spalte —
-- `getColumnMeta()` traegt also durchgehend, und der RowReader macht daraus
-- `Node`, `Setting` und `NodeJunction`, ohne dass hier etwas deklariert
-- werden muesste.
--
-- Die Junction mit `junction_node_id = 1` ist die **Vorgabe** der Einstellung:
-- die Zeile mit der id 1 ist im Bestand die Vorlagenzeile, und ein Wert, der
-- an ihr haengt, gilt fuer jede Seite, die keinen eigenen hat.
--
-- `node_type = 'setting'` ist die Bedingung, die auch der Bestand stellt
-- (`pageSettings.sql`: `setting_parent_nodes.node_type = 'setting'`).
-- Deklarationen ohne Knoten — die Vorlagenzeile `setting_node_id = 0` — fallen
-- durch den JOIN heraus, und das ist richtig: ohne Knoten kein Platz im Baum.

SELECT
	node_id,
	node_parent_node_id,
	node_ordering,
	node_type,

	setting_id,
	setting_node_id,
	setting_type,
	setting_name,
	setting_datatype,
	setting_label,
	setting_description,
	setting_show_in_templates,

	junction_node_id,
	junction_setting_node_id,
	junction_varchar,
	junction_int,
	junction_decimal

FROM cms_nodes
JOIN cms_settings ON setting_node_id = node_id
LEFT JOIN cms_junctions ON junction_setting_node_id = node_id AND junction_node_id = 1

WHERE node_type = 'setting'

-- COALESCE, weil die Wurzel in dieser Installation beides sein kann: der
-- Spalten-DEFAULT steht inzwischen auf NULL, die vorhandenen Zeilen tragen
-- aber noch 0. Dieselbe Schreibweise wie in `PageTree.sql`.
ORDER BY COALESCE(node_parent_node_id, 0), node_ordering, node_id
