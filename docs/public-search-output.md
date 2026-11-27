# Öffentliche Ausgabe für Suche und KI

Stand: 27. November 2026.

## Dieselbe Seite vor und nach JavaScript

`site.php` sendet die erfassten Head-/Body-Layer bereits als HTML. Die
Kommentaranker erlauben dem vorhandenen Pages-Client, diese Elemente zu
übernehmen und spätere Navigationen zu aktualisieren. Bilder und Formulare
werden beim Start nicht ein zweites Mal eingesetzt; Inline-Skripte werden
nicht nochmals ausgeführt. Der Body-Platzhalter vor der Edit bar bleibt für
die getrennte Ereignisbindung des gemeinsamen Loaders erhalten.

Projektanimationen dürfen wichtigen Inhalt ohne JavaScript nicht verdecken.
Die gemeinsame Ausgabe enthält dafür den bestehenden WOW-Fallback.

## Ein Eigentümer für die Metadaten

`appSite::headTags()` erzeugt Titel, Beschreibung, Robots, Canonical,
Sprachalternativen, Sharing-Tags und den automatischen JSON-LD-Graphen.
Alle HTML-Werte sind maskiert. Der Client ersetzt denselben markierten Block
bei einer Navigation und aktualisiert die Dokumentsprache.

`Search\HeadMarkup` entfernt die alten Kopien aus Projekt-Head-Templates und
Head-Loadern. Assets und nicht seitenbezogene strukturierte Daten, etwa Person
oder WebSite, bleiben erhalten. WebPage und BreadcrumbList gehören zur
aktuellen Seite. Zusätzliche JSON-LD-Felder dürfen deren aktuelle Identität
nicht durch alte URL-/Sprachwerte ersetzen. PHP benötigt die DOM-Erweiterung.

Die konfigurierte Standardsprache ist maßgeblich. Tatsächlich öffentliche
Übersetzungen erhalten ihren eigenen Sprachlink; die Standardübersetzung
erhält zusätzlich `x-default`. Ein Sprachcode ohne Region bleibt beispielsweise
`de`; aus `en` wird nicht künstlich `en-EN`.

## Welche Seiten öffentlich gelistet werden

`Search\PublicPages` verwendet ausdrücklich die anonyme Identität, auch wenn
ein Redakteur die Liste im angemeldeten Browser öffnet. `sitemap.xml` und
`llms.txt` teilen diese Projektion:

- veröffentlichte, zur aktuellen Zeit freigegebene und anonym lesbare Seiten;
- aktivierte Sprachen, ausgehend von der konfigurierten Standardsprache;
- Navigationselemente mit Template und ohne `noindex`;
- keine Trash-Einträge, reinen Organisationsordner, Sprungmarken oder
  Redirect-Quellseiten;
- eindeutige kanonische URLs. Ein Inhaltsalias verweist auf sein öffentliches
  Original, sofern dieses eine eigene URL ohne Fragment besitzt.

Die Aliasplätze in Navigation und Redaktion bleiben bestehen. Die
Sitemap gibt absolute URLs, verfügbare Sprachalternativen und vorhandene
Änderungsdaten aus. Sie erfindet keine Änderungszeit bei jedem Aufruf.

Bei einer insgesamt privaten Website liefern die Listen keine Seiten; die
Antwort ist 403. Entwicklungsumgebungen und Vorschauen bleiben `noindex`.
Unbekannte Seiten liefern 404, alte aufgelöste Adressen eine dauerhafte
Weiterleitung zur kanonischen Adresse.

## robots.txt und bestehende Installationen

Das aktuelle System-Scaffold leitet `robots.txt` über den Frontend-Handler.
Bestehende Installationen müssen diese erzeugte `.htaccess`-Regel übernehmen,
ohne projektspezifische Regeln zu verlieren. Galerie Lattemann und Stefan Pohl
wurden lokal angepasst.

Nicht-LIVE und private Websites geben `Disallow: /` aus. Auf einer öffentlichen
LIVE-Installation bleiben vorhandene Projektregeln erhalten, einschließlich
getrennter Regeln für Such- und Trainingsbots. Veraltete Sitemap-Zeilen werden
durch die vollständige Sitemap-Adresse dieser Installation ersetzt. Projekte
ohne CMS behalten den statischen robots.txt-Fallback des Systems.

## Prüfung in der Redaktion

Search and AI zeigt die effektive Sprache, Standardsprache, Robots und
Listenausschlüsse. Check public output lädt die erste HTML-Antwort ohne
Anmeldecookie und ohne Ausführung von Seitenskripten. Geprüft werden Status,
Titel, Canonical, Beschreibung, aktuelle WebPage-Daten und Inhalt im HTML.
Eine abgewiesene oder nicht erreichbare Ausgabe wird nicht als erfolgreich
geprüft dargestellt.

Das ist keine Bestätigung einer Aufnahme in Google oder eine KI-Suche.
Die tatsächliche LIVE-Installation und deren Indexierungsfreigabe werden
nach dem Umschalten separat geprüft. Laborwerte von Lighthouse ersetzen
keine echten Nutzerdaten aus der veröffentlichten Website.
