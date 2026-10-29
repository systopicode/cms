# Pages, Sidebar und Touch

Stand: 29. Oktober 2026.

## Verhalten

- Tabellen nutzen die bestehende Drag-Engine mit Long Press auf Touch. Bewegung vor der Halteschwelle bricht den Start ab; Kompatibilitäts-Mausevents lösen keinen zweiten Drag aus. Griffe sind symmetrisch, Einfügemarkierungen beziehen sich auf die ganze Zeile.
- Tags bleiben bis 1400 px einspaltig. Touch-Flächen sind leicht größer; der Einfügeindikator hat einen zentrierten Kreis und reicht oben 26 px, unten 10 px über den Tag hinaus. Eigene Tag-/MML-Elterngruppen reagieren nicht als Dropziel.
- Sidebar-Tabs bleiben außerhalb des vertikalen Scrollers. Beim Verlassen von Pages verschwinden Sidebar und Pages-Controls. Datumsfelder bleiben innerhalb der Sidebar-Breite.
- Kontextmenü und Split-Button-Menü teilen Material, Abstände und Touch-Darstellung. Preview verwendet `user-hat-tie-magnifying-glass`; Hierarchie-Aktionen kombinieren Layer mit Auge. Show live erscheint nur für öffentlich sichtbare Seiten; Copy ID entfällt.
- Alias verwendet `file-import` (blau), Redirect `route` (grün). Ein Original zeigt im Baum einen einzelnen neutralen Verweis auf seine Alias-Liste. Titel-Beziehungsicons stehen rechts vom Stift.
- Relations-Links verwenden normale Seiten-GETs mit explizitem Sidebar-/Baumziel. Der Baum misst die aktive Label-Zeile statt ihres gesamten Unterbaums. Die zentrale Scroll-Wiederherstellung respektiert gezielt angesteuerte Scroller auch nach ihren verzögerten Restore-Durchläufen.
- Release enthält Preview, Sichtbarkeit, Hierarchie-Aktionen, Zeitfenster und neutrale Seitenaktionen. Restore erscheint ausschließlich für gelöschte Seiten. Info zeigt Seiten-ID, Erstellung und letzte Änderung samt Benutzer.
- „Aliases & Redirects“ zeigt Original, eingehende Aliase und Redirect-Verknüpfungen als direkte Listen. Alias-Erstellung bleibt im Baum. Entfernen sitzt ohne Rahmen/Trennlinie rechts in der gemeinsamen Verknüpfungszeile; Aliase mit Kindern sind geschützt. Eine kleine Hilfe-Lightbox ist über das Info-Icon erreichbar.
- Die Redirect-Suche zeigt Titel/Pfad und rechts eine eigene Verknüpfen-Aktion. Nur dieser Button speichert, ohne Bestätigungsdialog; nach Erfolg werden Suche und Ergebnisse geleert. Späte Suchantworten werden verworfen. Bestehende Seitenrollen und serverseitige Zyklusprüfungen bleiben aktiv.
- Bildeditor: Crop ist eine Highlight-Icon-Aktion. Die Eckgriffe skalieren diagonal. Vorschau-Anpassungen verwerfen und gespeichertes Format wiederherstellen bleiben getrennte Aktionen.
- Die Media-Settings-Karten erlauben horizontalen Scroll bei Überlauf; gemessen wird auch die Inhaltsbreite. Der JS-Loader wartet auf DOM und Pflichtskripte, nicht auf sämtliche Bilder.
- Benutzer-Fehlermeldungen spannen im Account-Raster die ganze Zeile auf. Profil-Fußaktionen verwenden Highlight; Show-all-Aktionen haben einheitliche Trennlinien.
- Glas gehört zum Lightbox-Backdrop: gleichmäßige Theme-Tönung, Blur, Sättigung und Reduced-Transparency-Fallback. Dialogoberflächen bleiben unverändert.

## Prüfungen

CMS:

- `php tests/page-relations.php`: Selbstverweise, gemischte Zyklen, ungültige Ziele und Alias-Platzierung.
- `php tests/page-relation-actions.php`: tatsächlicher Sidebar-Handler speichert Quelle → aktuelle Seite und leert anschließend die Suche; keine Projektdatenbankänderung.

System (Browserprüfungen mit `FLUXO_PLAYWRIGHT_MODULE`):

- `tests/pages-touch-ui.mjs`: 390/1180/1440 px, Drag/Scroll-Abbruch, eigene Gruppen, Sidebar-Tabs, Tabellenüberlauf, Formularmeldungen, Suchfokus und verspätete Antworten sowie zentrale Scroll-Restore-Timer.
- `tests/mobile-refinements-ui.mjs`: Navigation, Accordion-Zustand, mobile Titel/Suchhinweise und Glas-Backdrop.
- `tests/loader-ready.mjs`: Initialisierung nach DOM- und Skriptbereitschaft vor vollständigem Ressourcenladen.
- PHP-/JS-Syntax und `git diff --check`.

Live read-only geprüft: veröffentlichte/unveröffentlichte Kontextaktionen, Suche und Autocomplete, Pages/Dashboard-Wechsel, tatsächlicher Tabellenüberlauf, Hilfe-Lightbox und Alias-/Original-Navigation. Bestehende Seiten und Verknüpfungen wurden für diese Prüfungen nicht verändert. Physische iPad-/iPhone-Tests erfolgen durch den Benutzer; automatisierte Touch-Prüfungen sind Simulationen.
