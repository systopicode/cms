# Arbeitsblock 1 – Tools, Logs, Tabellen und E-Mail

Stand: 30. Oktober 2026. Der Block ist **noch nicht vollständig abgenommen**: 1, 4 und 5 bleiben teilweise nicht live prüfbar.

## Einzelstatus

| Nr. | Status | Tatsächliche Verifizierung |
|---|---|---|
| 1 | BLOCKED | Cleanup live bei 1440, 1024 und 390 Pixeln geöffnet; bei 390px seitlich scrollende Tabelle mit sticky Aktionen, Buttons untereinander. Einzelvorschau geöffnet/geschlossen, keine Bereinigung gestartet. Desktop/iPad-/Touch-Fixtures prüfen Geometrie und Scrollen. Die abschließende visuelle Wiederholungsprüfung nach der letzten Header-Anpassung wurde durch die automatische Browser-Freigabe blockiert; die vorherige schmale Ansicht war geprüft, die Header-Nachbesserung ist noch nicht visuell abgenommen. |
| 2 | PASS | E-Mail-Log live: Nutzersuche ergibt 0 Treffer, Icon-Reset stellt alle Einträge wieder her. Screenshot bei 390px ohne Filterüberlagerung. Gemeinsame Filter-Fixtures bei 1440/1024/390px. Tooltip und Accessible Name des Resets vorhanden. |
| 3 | PASS | Audit live: Checkbox an- und wieder abgewählt. Kein Dirty-Attribut und kein Unsaved-Status. Keine Audit-Aktion ausgeführt. |
| 4 | BLOCKED | Tabellenaktion auf `cmsIcon-laptop-code`, Icon-only, Tooltip/Accessible Name geändert. Remote Debugging wird durch `RemoteDebug::available()` in dieser Dev-Installation nicht angeboten. Öffnen eines echten Debugfensters deshalb nicht live geprüft; keine Relay-/Berechtigungskonfiguration dafür verändert. |
| 5 | BLOCKED | Inventar aller Panel-Tabellenvorlagen in System/CMS/Relay/Legacy geprüft (26 Dateien; eine davon ist ein altes E-Mail-Layout, keine Admin-Tabelle). Gemeinsame Regeln und aktive Bereiche Cleanup, Logs, Audit, Backup und Media Settings getestet. Die nicht aktive Legacy-Shop-Tabelle und sämtliche möglichen Daten-/Berechtigungszustände konnten nicht komplett in einer laufenden Installation durchgespielt werden. Echtes iPad-Safari steht nicht zur Verfügung; Touch wurde im Fixture simuliert. |
| 6 | PASS | Vorhandenen Legacy-Backup-Restore nur geöffnet: Warnung sichtbar gelb mit Warnsymbol, gemeinsame Message-Struktur. Fehler verwenden dieselbe rote Message-Komponente. Preflight-Fixtures: beschädigte/neue/unvollständige Archive werden abgelehnt; keine Extraktion. Kein Restore ausgeführt. |
| 7 | PASS | Backup-Medienauswahl live an-/zurückgeschaltet, kein Saved-/Unsaved-Status. Audit und Remote-Formularparameter explizit als temporär markiert. Gemeinsame Fixture prüft: Parameter bleiben ruhig, eigentlicher Submit behält Working/Done und persistente Autosaves behalten Dirty-/Fehlerzustand. |
| 32 | PASS | Settings-Pylon auf Desktop und 1024px geprüft: kompaktere Abstände, System/Pages-Gruppen, Trenner in `cmsLineStrong`. Tools nutzt dieselben Gruppenklassen; Zusatztools bleiben über die bestehende Pylon-Logik erreichbar (Regressionstest). Users bleibt fachlich eine zusammenhängende Rechte-Navigation; dort wäre eine künstliche System/Projekt-Trennung irreführend. Gemeinsame Abstände gelten auch dort. |
| 33 | PASS | Echter Send-Test zeigt sichtbar SMTP-Annahme und wirksamen Empfänger/Testmodus. Lokale SMTP-Fixtures erzeugen echte Begrüßungs-, Auth-, Empfänger- und TLS-Fehler und prüfen sichere Logausgabe. Handler-Fixture prüft verständliche Meldungen; Klartext/Base64-Zugangsdaten werden entfernt. Kein Passwort zum Erzeugen eines Fehlers geändert. Annahme durch SMTP ist kein Nachweis des Posteingangs. |
| 35 | PASS | Backup-Log live geöffnet und nach Reload visuell geprüft: 10px Radius, 1px Rahmen, `overflow:hidden`, gemeinsame List-Widget-Struktur. Die bisherige Lightbox-Regel hatte List-Widgets auf Radius 0 zurückgesetzt. |
| 36 | PASS | Zentrale Betreff-Fixtures: HTTPS/HTTP und `www.` entfallen, andere Subdomains bleiben, keine doppelte Ergänzung; Test-Präfix und Empfänger-Routing bleiben erhalten. Echter Testbetreff enthält kompakt `dev.local`. |
| 37 | PASS | Test-Media-Tools live ausgeführt; vertikale Differenz Button-/Header-Mittelachse 0,5px auf Desktop und 1024px. Gemeinsame Header-Margins korrigiert, keine Positionierungs-Offsets. |
| 39 | PASS | Bestehende kompakte, history-freie Medienhilfe wiederverwendet; zusätzlich Tooltip. Mediengruppen-Info bei 1024px angeklickt, mit Escape geschlossen, mit Enter erneut geöffnet und per X geschlossen. URL bleibt unverändert. |
| 40 | PASS | Leere Website-/Cleanup-Seiten reproduziert. Fehlende Weitergabe der `main`-Ansicht an ausgewählte Unterseiten ergänzt; zusätzlich Statusvariablen-Kollision bei Cleanup behoben. Links zeigt Daten/Empty State, Trash zeigt Einträge, automatische Bereinigung zeigt Regeln und Vorschau. Navigation und Server-Logs nach Korrektur geprüft. |

## Änderungen an gemeinsamen Komponenten

- `data-feedback="parameters"`: temporäre Auswahl/Filter und deren Session-Persistenz sind keine Inhaltsänderung. Explizite Aktionen behalten ihren Aktionsstatus. `data-feedback="none"` unterdrückt weiterhin sämtliches Feedback.
- `data-table-scroll`: ausdrücklich seitlich scrollende Reports verwenden die vorhandene Scroll-/Sticky-Logik statt Karten-Accordion. Andere Tabellen behalten ihre bisherigen responsiven Karten.
- List-Widgets in Lightboxen behalten den gemeinsamen Radius; das flache Styling bleibt für normale Formular-Widgets bestehen.
- Settings-Meldungen werden nicht mehr in einer unveränderten übergeordneten AJAX-Ansicht vorzeitig verbraucht.
- Restore-Warnungen sind dauerhaft am Dialog, keine flüchtige Erfolgsmeldung.
- SMTP-Fehler werden aus tatsächlichen Antworten klassifiziert; keine erfundene Zustellursache bei bereits erfolgreicher SMTP-Annahme.

## Ausgeführte Regressionstests

- `system/tests/gui-feedback-ui.mjs`: persistente Änderungen, konkurrierende Saves, Fehler/Retry, Navigation, Escape, Fokus; neue temporäre Parameterregeln.
- `system/tests/tools-block1-ui.mjs`: 1440×900 Desktop; 1024×768 und 390×844 mit simuliertem Touch; Filtergeometrie, Headerzentrierung, horizontales Scrollen und Sticky-Aktionen.
- `system/tests/mail-smtp-failures.py`: ausschließlich Loopback, tatsächlicher SMTP-Transport und Fehler-Log.
- `system/tests/mail-routing.py`: DEV zwingend Testempfänger, optionale Stage/Live-Umleitung, CC/BCC, ungültiger Empfänger.
- `system/tests/mail-failure.php`, `mail-probe-errors.php`, `mail-subject.php`, `mail-test-delivery.php`.
- `system/tests/maintenance-view.php`, `maintenance-actions.php`, `tools-navigation.php`, `backup-preflight.php`.
- PHP-/JS-Syntax und `git diff --check`.

## Noch für die Vollabnahme nötig

1. Abschließenden Screenshot der 390px-Cleanup-Ansicht nach der Header-Anpassung kontrollieren (1). Browserzugriff wurde am 30. Oktober durch die automatische Freigabe nach einem Nutzungslimit abgelehnt.
2. Eine Dev-Installation mit freigegebenem Remote Debugging: Icon/Tooltip und tatsächliches Öffnen des Debugfensters testen (4).
3. Eine aktive Legacy-Shop-Installation bzw. deren zukünftiger Nachfolger und die übrigen tabellenspezifischen Daten-/Berechtigungszustände: vollständige End-to-End-Matrix auf Desktop und echtem iPad (5).

Keine Accounts, Backups oder Inhalte gelöscht/wiederhergestellt. Backup-Auswahl zurückgestellt. Mehrere ausdrücklich ausgelöste SMTP-Proben wurden vom aktuell konfigurierten SMTP-Server angenommen; Posteingang wurde nicht gelesen. Die übrigen Punkte der großen Feedbackliste gehören nicht zu diesem Block.
## Follow-up: real iPad date filter overflow

The real-iPad screenshot reopens item 2 (and the Safari part of item 5): native date controls overlap adjacent filters. The comparison uses different log pages and origins, so it does not establish a cache defect. Asset modification-date versioning remains unchanged.

Shared filter controls now have explicit maximum physical/logical widths; native date controls are constrained to their field without replacing the native picker. The isolated layout test now checks actual input bounds as well as label bounds. Chrome fixture tests pass at 1440, 1024 with touch, and 390 with touch. Real Safari/iPad verification remains BLOCKED: no WebKit runtime is installed and access to the running project through browser automation remains blocked by automatic approval review. This is not a verified Safari fix until tested on the actual device.

