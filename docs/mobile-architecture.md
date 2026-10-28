# Fluxo Mobile – Architektur und Umsetzungsvorschlag

Stand: 25. Oktober 2026. Grundlage: `fluxo-mobile-konzept.md`, beigefügte GUI-Referenz und Analyse der Pakete `cms` und `system`. Dieses Dokument beschreibt Zielarchitektur und Umsetzung. Die erste mobile Hülle ist inzwischen implementiert; siehe Umsetzungsstand. Festlegung nach Rückmeldung: Der Pilon/Seitenbaum bleibt im Hintergrund, Page Edit und MML öffnen als separate Overlays von unten. Die alte mobile Pane-Implementierung wird ersetzt und bildet keine technische Grundlage.

## 1. Bestehende Architektur

Die gemeinsame CMS-Hülle liegt in `../system/panels/cms/body.tpl.php`. Sie rendert Hauptnavigation, Pilon, Content mit Controls, optionale Sidebar, Statusleiste und Lightbox. Die mobile Lösung muss hier ansetzen, damit auch Users, Settings und Tools dieselbe Navigation erhalten.

`../system/public/css/x_responsive.css` und das Inline-Script in der Hülle implementieren bereits vier mobile Panes: Menu, Tree, Edit und Side. Unter 860 px werden ganze Bereiche mit `display: none` umgeschaltet. Die DOM-Knoten bleiben dabei grundsätzlich bestehen, aber die Arbeitsfläche bleibt visuell nicht hinter den Werkzeugen erhalten. Verfügbare Pane-Buttons werden momentan nur beim Start geprüft; nach partiellen DOM-Updates kann diese Auswahl veralten. Die mobile Statusleiste wird vollständig ausgeblendet.

Im Pages-Modul sind die Rollen konkret:

| Bereich | Bestehende Quelle | Aufgabe |
| --- | --- | --- |
| Pilon | `panels/cms/010_pages/pylon.tpl.php` | Suche, Seitenbaum, neue Seite, Mediathek im Footer |
| Arbeitsfläche | `panels/cms/010_pages/main.tpl.php` | Seitenvorschau und Inline-Bearbeitung, alternativ Site-Editor |
| Eigenschaften | `panels/cms/010_pages/sidebar/` | Content, SEO & KI, Publishing; zusätzliche Templates landen unter More |
| Element-/Dateieditor | `panels/cms/010_pages/editor/` | Vorhandene Bearbeitungsdialoge |

Für die mobile Anordnung bleibt der Pilon mit Suche und Seitenbaum die konstante Arbeitsfläche. Die bisherige Content-Ansicht mit Seitenvorschau und Bearbeitung wird zu Page Edit als Overlay; die MML erhält ein eigenes Overlay. Ihre bisherige Lage im Pilon-Footer ist eine technische Kopplung, die für diese Darstellung gezielt aufgelöst werden muss. Inhalte, Aktionen und Panel-Identitäten werden weiterverwendet.

Der Module State ist ein serverseitiger, panelbezogener Session-Baum (`../system/src/Panels/PanelNode.php`, `initState`, `attachState`). Pages hält unter anderem Auswahl, Sprache, offene Baumknoten, Suchzustand und Pilonmaße. Die Sidebar hält unter anderem aktive Templates, Gruppe und eingeklappten Zustand. `hiddenViews` steuert vorhandene View-Sichtbarkeit. Neue persistente Schlüssel müssen in `getDefaultState()` stehen: `attachState()` entfernt nicht deklarierte Schlüssel. Dashboard-Widgets haben bereits eigene State-Felder und sind von den mobilen Werkzeug-Panels zu unterscheiden.

Touch ist bereits zentral vorhanden: `../system/public/js/core/event/event.js` startet Drag auf Touch/Stift nach 400 ms Long Press und bricht vor Aktivierung bei mehr als 8 px Bewegung ab. Resize-Handles starten sofort. `event.drag.js` übernimmt Pointer-Bewegung, Helper und Drop-Ziele. Pages ergänzt Sidebar-Swipe, Thumbnail-Overlays und iOS-Fokusübergabe beim Umbenennen. Diese Wege werden weiterverwendet.

## 2. Zielarchitektur

**Globale Navigation:** Hamburger im kompakten Header, vorhandenes Menü als petrolfarbener Drawer von links, Logo im Drawer. Backdrop, Schließen-Button und aktiver Menüpunkt. Der vorhandene reale Stage-Wert erscheint als Text und farbige Markierung im Header und Drawer; keine Darstellung aller drei Umgebungen als auswählbare Ziele.

**Permanente Arbeitsfläche:** Der Pilon mit Suche und Seitenbaum bleibt im DOM und im Hintergrund erhalten. Öffnen und Schließen von Page Edit oder MML baut ihn nicht neu auf; Scrollposition, offene Knoten und Auswahl bleiben erhalten. Echte Seitenwechsel und fachliche Aktionen verwenden weiterhin die vorhandenen Update-Wege.

**Overlays:** Page Edit und MML sind eigenständige Fenster, die von unten über den Pilon gleiten. Page Edit verwendet die vorhandene Content-Ansicht inklusive ihrer Controls und Bearbeitungsmöglichkeiten; Eigenschaften gehören zum Bearbeitungskontext und bleiben dort vollständig erreichbar. Die MML verwendet die vorhandenen Medieninhalte und Upload-Aktionen. Die gemeinsame Sheet-Hülle bietet Griff, Titel und Schließen-Button. Schließen legt den Pilon wieder frei. Kein Klonen von Formularen; View-Grenzen und Server-Updates müssen die eigenständige mobile Präsentation der MML unterstützen. CSS-Positionierung am bestehenden Ort ist zu bevorzugen, soweit Layer- und Stacking-Struktur das erlauben.

**Andere Module:** Der jeweilige Pilon beziehungsweise die vorhandene Modulnavigation bleibt im Hintergrund, Detail- und Bearbeitungsansichten öffnen darüber. Module ohne Pilon behalten ihre natürliche Hauptansicht als Basis. Pages dient als umfangreichster Referenzfall für die gemeinsame Overlay-Steuerung.

**Große Inhalte:** Medien und umfangreiche Formulare dürfen nahezu die volle verfügbare Höhe einnehmen. Bestehende Lightboxen erhalten eine mobile Darstellung unter Beibehaltung ihrer Aktionen und ihres Speicherns/Abbrechens. Keine parallele zweite Dialogsteuerung.

**Tablet:** Ein Werkzeug kann neben der Arbeitsfläche andocken, sobald die tatsächlich verfügbare Breite für Arbeitsfläche, Werkzeug und Abstände reicht. Ein `ResizeObserver` auf dem CMS-Host bewertet die Breitenanforderungen des Moduls; die Orientierung allein entscheidet nicht. Mobile Öffnungszustände dürfen Desktop-Breiten und Sidebar-Präferenzen nicht überschreiben.

## 3. Kontextuelle Werkzeuge im Kopf

Festlegung nach GUI-Abgleich: keine untere Navigation. Der Seitenbaum bleibt die Basis; ein Klick auf eine Seite öffnet Page Edit. Dessen bestehender Kopf zeigt Titel, Griff und ein `…`-Menü. Schließen erfolgt per Griffgeste, Escape oder Browser-Zurück; zusätzliche X-Schalter entfallen. Dort liegen die vorhandenen Sprach- und Geräte-Schalter sowie die Zugänge zu Eigenschaften und MML. Keine zusätzliche Edit-Titelleiste und keine Kopien der Steuerelemente. Die Eigenschaften lassen sich im Edit-Fenster ein- und ausblenden; Medien schließen zurück zur Bearbeitung. Plus bleibt ausschließlich zum Anlegen neuer Inhalte.

Mobile Darstellung ohne Debug-Rahmen und Debug-Tabs. Der Hamburger sitzt fest oben neben dem Modulnamen. Die Hauptnavigation öffnet über die gesamte Breite, enthält Logo und tatsächlichen Umgebungsstatus und erhält keine zusätzliche Überschrift. Die vorhandenen Modul-Aktionen und Berechtigungen bleiben maßgeblich für die angebotenen Werkzeuge.

## 4. Zustände, Gesten und Übergänge

- Fachlicher Zustand bleibt im vorhandenen Module State. Offenes Overlay, laufende Griffbewegung und Rücksprung-Fokus sind flüchtiger UI-Zustand pro Modul. Eine später gewünschte Werkzeugpräferenz wird ausdrücklich im jeweiligen Default State deklariert.
- Sheets lassen sich ausschließlich am eigenen Griff herunterziehen. Pointer-Capture, Bewegung und Abbruch werden nur dort behandelt; keine globale Swipe-Erkennung über Formularen, Baum oder Editor. Während eines bestehenden Drags wird keine neue Sheet-Geste gestartet. Der Medien-Resize-Griff und der neue Sheet-Griff müssen getrennte Ziele bleiben.
- Panel-Inhalt scrollt eigenständig; Scrollen schließt kein Sheet. Der Griff folgt dem Finger über `transform`, beim Loslassen wird geschlossen oder zurückgesetzt. `pointercancel`, Orientierung und Größenwechsel räumen den temporären Drag-Zustand auf.
- Drawer etwa 250 ms, Sheet etwa 300 ms, Werkzeugwechsel 180 ms. `prefers-reduced-motion` deaktiviert nicht notwendige Bewegung. Keine pauschale Animation serverseitig erneuerter Formulare.
- Escape und Backdrop schließen zunächst das oberste Overlay. Bestehende Dialoge behalten Vorrang und ihre Semantik für ungespeicherte Änderungen. Fokus wird beim Öffnen gesetzt, im modalen Bereich gehalten und beim Schließen zurückgegeben; abgedeckte Bereiche werden für Tastatur und Assistenztechnik inaktiv.
- Browser-Zurück muss mit dem vorhandenen `onpopstate` und dem `history.pushState`-Wrapper in `core/event/event.js` integriert werden. Ein Overlay-Schritt darf weder einen Server-Seitenwechsel auslösen noch fremde History-State-Daten überschreiben. Diese Integration vor zusätzlichen History-Einträgen separat prüfen.
- Safe Areas, `dvh` und bei Bedarf `visualViewport` begrenzen die Sheet-Höhe bei Bildschirmtastatur. Felder und Speichern/Schließen bleiben erreichbar. Status- und Upload-Anzeigen erhalten eine mobile Position.

## 5. Wiederverwenden und anpassen

| Komponente | Vorgehen |
| --- | --- |
| Panel-Routing, Rechte, Actions, Queries, Speichern | Unverändert weiterverwenden |
| Baum, Mediathek, Uploads, Inline-Editor, Sidebar-Inhalte | Bestehende Inhalte und Handler übernehmen; Darstellung prüfen |
| Module State und Session | Beibehalten; nur deklarierte neue Präferenzen ergänzen |
| Long Press und Drag & Drop | Beibehalten; Griffgesten davon isolieren |
| Gemeinsame CMS-Hülle | Header, Drawer, Backdrop und dynamische Werkzeugbeschreibung ergänzen |
| Bisherige Pane-Umschaltung und responsive CSS | Durch Overlay-Darstellung ersetzen, doppelte Steuerung vermeiden |
| Pages-Pilon | Konstante mobile Basis; MML aus der festen Footer-Darstellung lösen |
| Page Edit, MML und Eigenschaften | Gemeinsame Overlay-Hülle, passende Maße und Bearbeitungskontext ergänzen |
| Lightbox und Kontextmenüs | Viewport, Fokus, Stapelreihenfolge und Touch-Ziele überprüfen |

## 6. Technische Konflikte und Prüfbedarf

1. Baum und Mediathek sind gekoppelt. Die notwendige Trennung ihrer mobilen Darstellung muss Layer-Updates, Footer-Resize auf Desktop und Drop-Ziele erhalten. Sie gehört zur ersten Pages-Umsetzung und wird nicht zugunsten eines gemeinsamen Baum-/Medien-Sheets vertagt.
2. `pageChanged()` aktualisiert unter anderem Main, Pilon, Mediathek, Baum und Sidebar. Overlayzustand muss diese legitimen DOM-Updates überstehen; Werkzeugwechsel darf sie nicht unnötig auslösen.
3. Page Edit und MML liegen beide über dem Pilon. Beim Öffnen der MML aus einem Editor müssen dessen Auswahl, Fokus-Rücksprung und nicht gespeicherte Eingaben erhalten bleiben. Medienübernahme benötigt einen definierten Rückweg in diesen Editor, etwa über die vorhandene Einfügeaktion. Drag zwischen beiden Fenstern braucht eine ausdrücklich dafür ausgelegte Darstellung; eine inaktive, verdeckte Ansicht ist kein Drop-Ziel. Auf Tablet können parallel angezeigte Fenster den bestehenden Drag erlauben.
4. Globale Pointer-Handler, Sidebar-Swipe, Kontextmenü und neue Griffgesten brauchen klar getrennte Startbereiche. Das bestehende Touch-Verhalten wurde im Quelltext geprüft, noch nicht auf Geräten verifiziert.
5. Vorschau-Gerätewahl (`cmsDevice` in localStorage) ist eine Content-Vorschaupräferenz und darf nicht den tatsächlichen Modus der CMS-Hülle bestimmen.
6. Die gemeinsame Hülle wirkt auf alle Module. Ein reiner Pages-Patch genügt nicht; eine globale CSS-Regel ohne Prüfung von Settings/Users/Tools wäre ebenfalls zu breit.

## 7. Schrittweiser Umsetzungsplan

1. Alte mobile CSS-Regeln, Pane-HTML und Pane-JavaScript zum Entfernen abgrenzen. Benachbarte Desktop-, Vorschau- und Sidebar-Funktionen erhalten. Einen neuen gemeinsamen Controller auf Basis dieses Konzepts aufbauen; keine Migration der alten Pane-Architektur. Desktop als Vergleich festhalten.
2. Mobile Hülle ergänzen: Header mit Modulname und Stage, Hamburger, Drawer und Backdrop. Bestehende Navigation und Rechte verwenden. Desktop-CSS außerhalb des kompakten Modus beibehalten.
3. Pages als Referenz umsetzen: Pilon als konstante Basis, Page Edit und MML als getrennte Bottom-Overlays. Kontextmenü im bestehenden Kopf und gemeinsame Sheet-Steuerung einführen. Eigenschaften im Bearbeitungskontext erhalten. Auswahl, Scrollposition und Eingaben bei Fensterwechsel erhalten; Fokus, Escape und reduzierte Bewegung einschließen.
4. Griffgeste, Viewport/Tastatur und Browser-Zurück integrieren. Vorhandene Lightboxen in die Overlay-Reihenfolge einbinden.
5. Pages im Detail anpassen: Medientransfer, Kontextaktionen, Uploads, lange Formulare und Content-Controls. Tablet-Andocken anhand verfügbarer Breite ergänzen.
6. Das geprüfte Basis-/Overlay-Prinzip auf andere Module übertragen. Sicherstellen, dass das alte mobile CSS/HTML/JS vollständig abgelöst ist und keine zweite mobile Steuerung aktiv bleibt.

Abnahme: schmale Smartphones, größere Smartphones, Tablet hoch/quer und Desktop; insbesondere Werkzeugwechsel ohne Verlust von Auswahl/Scroll/Formularwerten, Seitenwechsel mit geöffnetem Sheet, Long Press plus Sortierung, Medien einfügen und hochladen, Suche, Umbenennen mit iOS-Tastatur, komplette Sidebar inklusive zusätzlicher Templates, Dialoge, Zurück/Escape, Berechtigungen und sichtbarer Umgebungsstatus. Automatisierte UI-Prüfungen für Navigation und Zustand ergänzen; echte Touch-/Tastatur-Prüfung auf iOS/iPadOS und Android bleibt erforderlich.


## 8. Umsetzungsstand

Die alte Pane-Navigation und die zunächst eingeführte untere Werkzeugleiste wurden in `system/panels/cms/body.tpl.php` und `system/public/css/x_responsive.css` ersetzt. `system/panels/cms/mobile.js` steuert die Präsentation bestehender DOM-Bereiche: Pilon als Basis, Content und MML als getrennte Bottom-Fenster, Hauptnavigation als Drawer. Baumklick öffnet Edit. Die bestehenden Sprach- und Geräte-Schalter, Eigenschaften und MML sind über das `…`-Menü in dessen vorhandener Kopfzeile erreichbar. Mobile Debug-Darstellung ist ausgeblendet. Keine duplizierten Formulare, keine neue serverseitige State-Schicht. Eigenschaften bleiben im Edit-Fenster erreichbar. Medien schließen zurück zur Bearbeitung, wenn sie von dort geöffnet wurden.

Enthalten: dynamische Verfügbarkeit nach Panel-Updates, Fokusbegrenzung, Escape, Browser-Zurück für gleichbleibende URLs, Griffgeste, Safe Areas, Tastaturhöhenanpassung und reduzierte Bewegung. Der kompakte Modus gilt zunächst bis 1024 CSS-Pixel; Tablet-Andocken anhand modulspezifischer Breiten ist noch nicht umgesetzt.

Validiert mit `system/tests/mobile-shell-ui.mjs` in Chrome anhand einer isolierten Panel-Struktur und vorhandener Styles: ungespeicherte Eingaben, Baumidentität und Scrollposition, Medien-Rückkehr, Drawer, Browser-Zurück, partielle Pilon-Erneuerung, Griffgeste, Tablet-Breiten, Desktop-Rückkehr und Module ohne Pilon. PHP-/JS-Syntax und Diff-Whitespace sind geprüft. Die Teststruktur ersetzt keine Prüfung der tatsächlichen Server-Aktionen, Berechtigungen, Uploads oder iOS-Tastatur im laufenden CMS. Insbesondere die Medienübernahme zwischen den Fenstern und das Zusammenspiel der Overlay-History mit echten AJAX-Seitenwechseln müssen dort geprüft werden.


### Gestalterischer Abgleich

Die mobile Hülle übernimmt Petrolrahmen, Rundungen und Schatten der Desktop-Oberfläche. Der Pilon sitzt als helle, gerundete Fläche im Rahmen. Edit und MML nutzen die volle verfügbare Höhe mit schmalem Petrolrand und gerundeten Ecken. Der globale Kopf steht im HTML vor der Arbeitsfläche und sitzt fest am oberen Rand; kein CMS-Platzhalter und kein Status-Badge im Kopf. Die volle Hauptnavigation lässt sich mit demselben Hamburger wieder schließen. Keine X-Schalter in den mobilen Fensterleisten; Tastatur und Browser-Zurück bleiben bedienbar. Der Debug-Rahmen bleibt mobil ausgeblendet.


### Korrekturen nach Screenshot-Abgleich – 26. Oktober 2026

- Hauptnavigation nutzt unabhängig vom Modul die volle Breite und die linksbündige Desktop-Anordnung. Bei offenem Menü bleibt nur das X rechts im globalen Kopf sichtbar; Status steht unter den übrigen Menüeinträgen.
- Auch normale Links im Pilon öffnen das Bearbeitungsfenster. Ein erneutes Ausführen des Body-Scripts ersetzt die bisherigen mobilen Listener und erhält den Fensterzustand; dadurch wird das Kopfmenü nicht mehrfach getoggelt.
- Dashboard erhält das vorhandene, überschreibbare Logo zentriert im Kopf.
- Suche und Lightboxen verwenden den Desktop-Radius. Fenster reichen unten ohne unteren Radius bis an den Bildschirmrand.
- Mobile Vorschau hat eine getrennte Präferenz (`cmsDeviceMobile`) mit Standard Fit/Expand; die Desktop-Präferenz bleibt erhalten.
- Ladebalken ist auf Desktop und Mobile 6 px hoch.
- Kontextmenüs erscheinen mobil als im Viewport liegendes Bottom-Sheet. Die Debug-Shadow-Host-Oberfläche wird ebenfalls ausgeblendet.
- Die CMS-PWA verwendet `CMS_PAGE_TITLE_OVERRIDE` beziehungsweise `fluxo cms`, unabhängig vom öffentlichen `PUBLIC_APP_NAME`. Manifest-URLs sind versioniert; Theme- und Bild-Overrides werden weiterhin über die bestehenden Resolver ausgewählt.

Validierung: erweiterte Browserprüfung einschließlich mehrfacher Initialisierung, unveränderter Eingaben, normaler Settings-Links, Suche, Dashboard, Menü, Kontextaktionen und Desktop-Rückkehr. Standalone-PHP-Test prüft PWA-Name, Theme-/Icon-Overrides, Scope und Manifest-Versionierung. Zusätzlich wurde der laufende lokale Manifest-Endpunkt ohne Anmeldung abgerufen: Name `fluxo cms`; ein ausgeliefertes Android-Icon wurde als Fluxo-PNG mit HTTP 200 geprüft. Installation auf realen Android-/iOS-Geräten ist damit noch nicht praktisch verifiziert.
