# Medieneditor und Bildausgabe

Stand: 27. November 2026, GUI 0.6.0.

## Ein Editor für die Datei

Alle Dateitypen verwenden die gemeinsame Tab-Leiste. Bilder haben Master,
Ratio-Crops, feste Formate, erzeugte Dateien und File details. Andere Dateien
zeigen die passende Vorschau sowie File details. Video & poster verbindet die
Wiedergabe mit der Erzeugung eines Posters aus dem aktuellen Frame. Die
Videostufen behalten das Seitenverhältnis; ihre konfigurierte Breite ist eine
Obergrenze, keine feste Kombination aus Breite und Höhe.

File details enthält Dateifelder, Tags, Verwendung, technische Angaben,
Berechtigungen und Löschen. Open original und Download gehören in den Footer
des Datei-Widgets. Die URL eines externen Mediums steht in File details; das
Bild im Master ist dessen Poster. Nicht verfügbare Vorschauen und noch nicht
erzeugte Poster erhalten einen erklärten Leerzustand.

Auf schmalen Ansichten liegen die Werkzeuge waagerecht über dem Bild und die
Formatauswahl darunter. Beide Leisten können unabhängig gewischt werden. Der
Ratio-Regler führt die ausgewählte Ratio nach. Die Tabs unter dem Titel
scrollen ausschließlich horizontal und führen den aktiven Tab in den sichtbaren
Bereich. Ein Tabwechsel und ein stilles Speichern starten die Eintrittsanimation
des Editors nicht erneut.

Touch unterstützt Ziehen, Zwei-Finger-Zoom und Rotation. Der obere Fenstergriff
schließt das Sheet; Wischen im Inhalt ist kein Schließgriff. Beim Abbrechen
einer Schließbestätigung mit ungespeicherten Änderungen bleibt der Editor
einschließlich Backdrop offen. Apply verwendet die gemeinsame Ladeanzeige.

In der Media Library öffnet ein kurzer Tap die Aktionen. Hold-and-release
kopiert das Medium in das bestehende Clipboard. Edit öffnet auch aus der
kompakten Library, dem Share-image-Feld und dem Texteditor den passenden
Editor beziehungsweise das referenzierte Format.

## Master, Ratio, Größe und Original

- Original ist die hochgeladene Datei. Der Master enthält die gespeicherten
  grundlegenden Bearbeitungen und kann daher ein anderes Seitenverhältnis haben.
- Ratio-Crops bestimmen den Bildausschnitt. Ohne Ratio wird der Master gezeigt.
- Größen wie 0,5 / 1 / 2 / 5 / 10 MP bestimmen die Auflösung. Sie sind keine
  unterschiedlichen Kompressionsqualitäten. Es wird nicht über die vorhandenen
  Pixel hinaus hochgerechnet.
- Feste Formate wie Share image bleiben für konkrete Ausgabemaße verfügbar.
  Alte Aliasnamen werden weiterhin verstanden; neue Templates benötigen sie nicht.
- GIF und SVG behalten ihre Originaldarstellung. Animierte GIFs werden nicht
  durch eine statische Crop-Ausgabe ersetzt; die Library zeigt ein ruhiges Vorschaubild.

## Eine normale Bildausgabe

```php
// Master im gespeicherten Seitenverhältnis, mit nativer Auflösungswahl.
echo $media->getCrop()->toPictureHtml();

// Ein bekanntes Verhältnis und die bekannte Spaltenbreite.
echo $media->getCrop('16:9')->toPictureHtml([], [
    'sizes' => '(max-width: 768px) calc(100vw - 2rem), 50vw',
]);

// Das erste sichtbare Hauptbild erhält eine passende Ladepriorität.
echo $media->getCrop()->toPictureHtml([], [
    'loading' => 'eager',
    'fetchpriority' => 'high',
    'sizes' => '(max-width: 768px) calc(100vw - 2rem), 50vw',
]);
```

Die Ausgabe ist immer ein `picture` mit einem responsiven `img`. Sie enthält
echte `src`-/`srcset`-Adressen, auch wenn die Ableitungen noch nicht erzeugt
sind. Der Browser wählt nach Bildfläche und Pixeldichte; der Server erzeugt
eine angeforderte Ableitung bei Bedarf. Die normale Ausgabe benötigt dafür
kein Bildauswahl-Skript. `toImgHtml($attributes)` delegiert als veralteter
Kompatibilitätsaufruf an dieselbe Ausgabe und meldet dies über `p()`.

`sizes="auto, 100vw"` ist der Standard für Lazy-Bilder. Ein Template mit
bekannten Spaltenbreiten gibt genaue `sizes` an, besonders bei einem eager
geladenen Hauptbild. Größenattribute allein verändern keinen Ausschnitt.

## Unterschiedliche Ausschnitte je Gerät

```php
echo $media->getCrop()->toPictureHtml([
    '(max-width: 560px)' => '1:1',
    '(max-width: 960px)' => '4:3',
]);
```

Die erste passende Media Query wählt den Crop; sonst bleibt der Master.
Diese Grenzen sind Beispiele. Jedes Projekt besitzt seine eigenen Bedingungen.
Für die Geräteauswahl im Texteditor wird die tatsächliche Projektregel markiert:

```css
/* cms-preview: mobile */
@media only screen and (max-width: 768px) { /* Projektregeln */ }

/* cms-preview: tablet 900px */
@media screen and (40rem <= width <= 64rem) { /* Projektregeln */ }
```

`PreviewWidths` liest sowohl die Vorschaugröße als auch die vollständige Query.
Nicht konfigurierte Geräte werden nicht zusätzlich erfunden. Die Texteditor-
Zuordnung verwendet diese Queries ebenfalls in der öffentlichen Ausgabe.

Ein beliebiger Elterncontainer kann durch HTML-`source[media]` nicht abgefragt
werden. Das optionale `toAutoHtml()` verwendet dafür den vorhandenen
Container-Helfer und zeigt ohne dessen Skript einen responsiven Master. Es ist
kein zusätzlicher notwendiger Modus für normale Bilder.

## Dateiberechtigungen und Konflikte

Use page permissions bezieht sich auf die Seite, zu der die Datei gehört,
nicht auf jede Seite, die dieselbe Datei verwendet. Ohne Vererbung werden die
Gruppen für die Datei gesetzt. Die projektweite Einstellung Page permissions
aktiviert die Prüfung. Media-Referenzen, Downloadlinks und Picture-Ausgabe
lassen nicht lesbare Medien aus.

Das ist eine Freigabe der CMS-Ausgabe. Bereits bekannte URLs zu physisch
öffentlich gespeicherten Dateien sind damit nicht geschützt: der Webserver
kann vorhandene Originale und Ableitungen direkt ausliefern. Vertrauliche
Downloads benötigen einen gesonderten geschützten Auslieferungsweg.

Crop-Änderungen senden die geladene Versionskennung. Der Server vergleicht sie
unter einer Datensatzsperre. HTTP 409 schreibt nichts und erhält den lokalen
Entwurf zur erneuten Prüfung; andere Bearbeiter werden nicht überschrieben.

Prüfstand und ausführbare Prüfungen: [Release-Abnahme](qa/media-search-release-2026-11-27.md).
