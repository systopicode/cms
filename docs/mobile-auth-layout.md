# Mobile Auth – aktueller Stand

Stand: 28. Oktober 2026.

## Layout und Hintergrund

Die Login-Karte, ihre Inhalte und Authentifizierungsfunktionen bleiben erhalten. Mobile Karten besitzen eine Mindesthöhe aus `100svh` abzüglich der Inhalts-/Safe-Area-Abstände, wachsen mit langen Formularen und haben keine maximale Formularhöhe. Legal-Texte behalten die bestehende begrenzte Karte mit ihrem inneren `.authScroll`. Die Legal-Fußzeile folgt im Dokumentfluss.

Auth-Seiten verwenden `interactive-widget=resizes-visual`, damit die Android-Tastatur den Layout-Viewport nicht zusammenpresst. Die übrige CMS-Hülle behält ihre Viewport-Konfiguration.

Der ausgewählte Hintergrund **Liquid Petrol** ist im System-Auth-CSS umgesetzt. Die Hauptfläche verwendet `--cmsMainColor`; die linke warme Lichtbahn und der rechte helle Lichtschein werden ausschließlich aus `--cmsMainColor`, `--cmsHighlightColor` und `--cmsBgColor` abgeleitet. Es sind keine festen Petrol-, Gold- oder Türkiswerte hinterlegt.

Das dekorative Pseudoelement ist nicht interaktiv und liegt hinter der Karte. Seine obere und untere Grenze liegen mindestens fünf rem vom Viewportrand entfernt und berücksichtigen zusätzlich Safe-Area-Inset plus drei rem. Eine Alphamaske blendet das Licht an beiden Grenzen vollständig aus. Dadurch bleiben beide Viewportkanten in der Theme-Grundfarbe. Es gibt keine Animation, neue GUI-Komponente oder Bild-/Netzwerkabhängigkeit.

Die früher untersuchte transparente Safari-Statusleiste wird nicht weiterverfolgt; die experimentellen Varianten wurden nicht übernommen.

## Prüfung

`../system/tests/auth-mobile-ui.mjs` besteht im lokalen Chrome mit Playwright Core. Der Test rendert das echte Login-Template mit isolierten PHP-Stubs und aktuellen Styles. Er prüft Methodenwahl, Passwort, Code, Remote-Login-Zustand ohne QR-Backend, Erfolg, verschiedene Smartphone-/Tabletbreiten, lange Formulare, kurze Viewports, Legal-Innenscrollen und partielle Methodenwechsel. Die Desktop-Kartengeometrie entspricht den eingecheckten Styles.

Zusätzlich geprüft: wechselnde Theme-Farben verändern die Lichtquellen; die Dekoration bleibt bei Hoch-/Querformat und Desktop außerhalb simulierter oberer und unterer Safe-Area-Abstände; sie fängt keine Pointer-Ereignisse ab und verschwindet nach Wechsel ins CMS ohne Auth-Karte.

Echte iOS-/Android-Tastaturen und browserspezifische Leisten sind durch die Chrome-Prüfung nicht abgedeckt. Die isolierten Safari-Vergleiche des vorherigen Versuchs sind keine Geräteabnahme dieses neuen Hintergrunds.
