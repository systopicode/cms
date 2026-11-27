# Medien- und Suchausgabe: Release-Abnahme

Stand: 27. November 2026. Lokale Galerie-Lattemann-Installation; gemeinsame
CMS-/Systemfunktionen und zusätzliche Bot-Antworten von Stefan Pohl.

## Ergebnis nach ursprünglichen Prüfpunkten

| Nr. | Ergebnis | Tatsächliche Prüfung |
| --- | --- | --- |
| 1 | PASS | Erstes HTML, Übernahme derselben DOM-Elemente, Navigation und Ausgabe ohne JavaScript bei 1440/834/390 px. |
| 2 | PASS | Maskierung, ein Metadatenblock, Canonical nach Navigation und öffentliche Original-URL von Inhaltsaliasen. |
| 3 | PASS | Aktuelle WebPage-Daten der 59 gelisteten Galerie-Seiten und nach AJAX-Navigation; JSON-LD-Parserprüfungen. |
| 4 | PASS | Unbekannte Pfade und IDs liefern in beiden Projekten 404; eine bestehende alte Galerie-Adresse liefert 301. |
| 5 | PASS | Sitemap/llms-Deduplizierung, interne Links und Icons; alle 101 Bild-/Share-Image-Adressen erreichbar und vom Typ Bild. |
| 6 | PASS | Italienisch als konfigurierte Standardsprache bei zuerst aufgelistetem Deutsch; echte Sprachlinks und x-default; private Übersetzung fehlt. |
| 7 | PASS | Anonyme Liste trotz Admin-Sitzung; private, unveröffentlichte, zukünftige, abgelaufene und gelöschte Seiten ausgeschlossen; LIVE-Vorschau noindex; private Listen 403. |
| 8 | PASS | Echte Sidebar-Ausgabe mit CMS-Stilen in drei Größen; anonyme Prüfung und Erkennung einer absichtlich leeren Ausgabe. |
| 9 | PASS | Lighthouse, kleinere WOFF2-Dateien mit vollständigem Zeichensatz, Bildprioritäten und echte statische Veröffentlichung mit Cache-/Versionsprüfung. |
| 10 | PASS | 24 Kombinationen aus acht Seitentypen und drei Größen ohne gemeldete axe-Verletzungen oder Browser-Ausnahmen; Navigation und Touch-Geste; Kontakt-/Footer-Mails genau einmal im lokalen Empfänger, Fehler und erneuter Versuch. |

Desktop, Tablet- und schmale Ansichten wurden zusätzlich visuell kontrolliert.
Ein bei der Sichtprüfung verrutschtes Logo wurde vor der Schlussmessung
proportional korrigiert. Im letzten Lauf entstanden keine neuen PHP-Laufzeitfehler.
Der Nutzer hat den Mailversand zusätzlich bestätigt.

## Lokale Lighthouse-Werte

Lighthouse 13.6.0, lokale langsame Namensauflösung für die Messung umgangen:

| Seite | Performance | Accessibility | Best Practices | LCP |
| --- | ---: | ---: | ---: | ---: |
| Startseite mobil | 99 | 100 | 100 | 1,8 s |
| Startseite Desktop | 100 | 100 | 100 | 0,4 s |
| Galerie mobil | 98 | 100 | 100 | 2,3 s |
| Ausstellungen mobil | 95 | 100 | 100 | 2,9 s |

Der SEO-Laborwert 66 stammt von der absichtlichen DEV-Indexierungssperre.
Die übrigen automatischen SEO-Prüfungen bestanden. Die bildreiche Übersicht
bleibt der größte weitere Ladezeit-Ansatzpunkt. Es wurde keine zusätzliche
Bildkompression auf Kosten der redaktionellen Originale durchgeführt.

## Wiederholbare Prüfungen

PHP: `tests/search-output.php`, `tests/json-ld-parser.php`,
`tests/preview-widths.php`, `tests/image-ratio-choices.php`,
`tests/media-upload-policy.php`, `tests/media-animation-video.php`.

Mit einer getrennten Datenbank und `FLUXO_SEARCH_TEST_CONFIG`:
`tests/search-public-pages.php` und anschließend derselbe Test mit `--private`.
Der Test lehnt Datenbanknamen außerhalb seines Test-Präfixes ab. Er ist kein
Befehl für eine Projekt- oder Produktionsdatenbank.

Browser mit `FLUXO_PLAYWRIGHT_MODULE`: `tests/search-output-ui.mjs`,
`tests/search-hydration-ui.mjs`, `tests/search-sidebar-ui.mjs`,
`tests/image-editor-gui.mjs`, `tests/file-editor-tabs-ui.mjs`,
`tests/native-image-ui.mjs`, `tests/project-image-queries-ui.mjs`.
Die jeweiligen Fixture-Server beziehungsweise Testverbindungen müssen laufen.
System ergänzt `tests/media-picture.php`, `tests/media-crop-math.mjs`,
`tests/request-action.php` und `tests/password-stage.php` nach dem Remote-Abgleich.

## Grenzen und Übergabe

Es wurde nicht veröffentlicht. Online-PageSpeed, Search Console,
Suchmaschinenaufnahme und echte Nutzerdaten stehen für die LIVE-Installation
noch aus. Touch war simuliert; physische iOS-Geräte waren nicht verfügbar.
Die vom Nutzer ausgenommenen fehlenden Stefan-Pohl-Originalmedien bleiben
ausgenommen. Vertrauliche Downloads sind keine durch öffentliche Datei-URLs
abgesicherte Funktion dieser Content-Permissions.

Testdatenbanken, Verbindungsdateien und veröffentlichte Cache-Testdateien
wurden entfernt. Automatische Testmails blieben im lokalen SMTP-Empfänger.
GUI-Releasenummern sind von Composer-Paketversionen unabhängig.


## Dokumentation, GUI-Release und Remote-Abgleich

Am 27.11. wurden die Dokumentation und GUI 0.6.0 ergänzt. Die echte Release-
Seite wurde bei 1440/834/390 px visuell geprüft; Auswahl der Login-Methode und
Zurück-Navigation per Maus beziehungsweise Touch bestanden in allen drei
Größen ohne Browserfehler. Dabei wurde der veraltete Client-Pfad
`panels.admin.auth` auf den tatsächlichen Auth-Pfad korrigiert und die
Methodenauswahl mit den Kennungen des vorhandenen Fokus-Systems versehen.
In den relevanten PHP-Logs entstanden keine neuen Einträge.

Erneut bestanden: Picture-Ausgabe und Legacy-Delegation, Request-Aktionen,
Passwort-Stage-Regeln, Konfigurationsschema, Crop-Mathematik, Suchausgabe,
Projekt-Media-Queries, Ratio-Auswahl, Upload-Prüfungen sowie echte GIF-/FFmpeg-
Verarbeitung an Wegwerfdateien. Der System-Abgleich bis `b3e5e1e` blieb ohne
Konflikte. Der spätere LIVE-Wechsel über den gewünschten Sync-Release-Manager
ist von dieser lokalen Abnahme getrennt.
