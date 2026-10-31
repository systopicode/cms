# Block 2 — Pages, Media & Editor

Prüfung: 30.10.2026. Bestehende Änderungen aus anderen Blöcken wurden nicht zurückgesetzt. Kein Commit und keine Änderung an Projektmedien oder Inhalten.

| Nr. | Status | Tatsächliche Verifizierung |
| --- | --- | --- |
| 12 | PASS | Dev: ohne Auswahl ist der komplette MML-Footer 0 px hoch und unsichtbar; nach Auswahl 300 px. Höhe bleibt über Reload/Seitenwechsel erhalten. Resize-Handle und bestehender Speicherpfad erhalten. CSS-Browsertest bestätigt Reveal und Reduced Motion. Kein echter iPad-Dragtest. |
| 13 | PASS | Dev: offenes Tree-Menü verschwindet beim Auswählen einer anderen Seite. Bestehendes closeAllMenus wird auch nach Tree-Updates, Navigation und Pointer-Auswahl verwendet. |
| 14 | BLOCKED | Client-Test mit dem echten Upload-Modul: Einzelupload meldet nur lokalen Fortschritt; Mehrfachqueue weiterhin global. Fehler- und Gruppentests erfolgreich. Echter Einzel-/Mehrfach-/Backup-Upload wurde nicht ausgeführt; dafür fehlen isolierte Projekttestdaten. Backup-Pfad unverändert. |
| 15 | PASS | Bereits erledigter Punkt: Regression mit geladenem Dev-Bild, Spinner nach Laden nicht sichtbar. Kein neues Spinner-System. |
| 16 | BLOCKED | Dev-Bild bei Desktop und 1194×650/1024×700: Sicherheitsabstand, Fit und Zoom geprüft. Automatisiert zusätzlich 390/1024/1194/1440 px. Beide vorhandenen Videoeinträge melden fehlende Originaldatei; echte Video-/weitere Vorschautypen daher noch nicht vollständig verifiziert. |
| 17 | PASS | Dev: kompakte Icon-Aktionen in maximal zwei Zeilen bei iPad-Größe; Slider ein-/ausblenden, Wert 115 beim Wechsel erhalten. Rotation und Spiegelung gemeinsam erhalten. Template-Browsertest zusätzlich bei 390/1024/1194/1440 px; Desktop zeigt weiterhin vorhandene Slider. |
| 18 | BLOCKED | Dev: Crop-Bereich und Aktionsleiste getrennt, auch nach Größenwechsel; Cancel geprüft. Dabei gefundenen verspäteten Resize-Aufruf behoben und erneut getestet: Crop öffnet nach Cancel nicht erneut. Apply/Persistenz und alle Zoom-/Seitenverhältnisse noch nicht mit disponiblen Medien geprüft. |
| 19 | PASS | Dev: Pages-Metadatenstatus im Dialog verborgen. Shared-UI-Browsertest: Page-Dirty-State bleibt erhalten; Dialogstatus wird gezeigt, bei verschachteltem Dialog zurückgenommen und danach wiederhergestellt. |
| 20 | PASS | Dev: FOOTER ohne Template zeigt Inline-Hinweis in derselben Komponente wie parallele Bearbeitung; keine Floating Message. |
| 28 | PASS | Konkreten ungültigen querySelector in image.tpl.php korrigiert. Template-Browsertest führt Apply mit abgefangenem Request aus; Rotate/Flip/Reset und reale Dev-Bedienung ohne DOMException. Kein pauschales Catch. |
| 30 | PASS | Dev und Browsertest: Close per Tastatur fokussiert, sichtbarer 2-px-Rahmen, vorhandener Radius, 0 px Outline-Abstand. |
| 31 | PASS | Shared-UI-Browsertest: Tab-Fokus mit vorhandenem Radius; bestehender Fokus-/Escape-/Focus-Return-Regressionstest erfolgreich. |
| 34 | PASS | Dev: Alias- und Redirect-Header bleiben BUTTONs, blau bzw. grün; Klick öffnet Publishing/Relations. Globale semantische Varianten mit bestehendem Hover/Focus/Disabled-Verhalten statt lokaler Icon-Farbe. |

## Wiederholbare Prüfungen

`FLUXO_PLAYWRIGHT_MODULE` muss auf das installierte Playwright-Modul zeigen.

- CMS: `tests/block2-media-ui.mjs`, `tests/block2-shared-ui.mjs`, `tests/block2-upload-progress.mjs`, `tests/media-upload-client.mjs`, `tests/media-upload-feedback-ui.mjs`, `tests/media-usage.php` erfolgreich.
- System: `tests/gui-feedback-ui.mjs`, `tests/pages-touch-ui.mjs`, `tests/media-adjustments.php` erfolgreich. Der Touch-Test wartet nun auf das Ende der bestehenden Scrollanimation, statt sie nach drei Frames als abgeschlossen anzusehen.
- PHP-/JS-Syntax und `git diff --check` in beiden Repositories erfolgreich.
- Dev-Konsole: keine neue Editor-Exception. Vorhandene Warnung zu `duplicates`/`cleanup` bleibt außerhalb dieses Blocks. Die gefundenen PHP-Logdateien enthalten keine aktuellen Fehler aus diesem Testlauf.
- Visueller Dev-Beleg: `/private/tmp/fluxo-block2-ipad.png`.

## Offener Abschluss

Der Block ist noch nicht vollständig abgenommen. Für 14/16/18 fehlt ein isolierter End-to-End-Lauf mit vorhandener Video-/Vorschaudatei, disponiblen Uploads und Crop-Apply. Ein echtes iPad/Safari wurde nicht ferngesteuert; Tablet-Touch und schmale Layouts wurden in Chrome simuliert. Nicht als Hardwaretest werten.

## Nachkorrekturen — vier Punkte vom 30.10., 20:00

| Nr. | Status | Verifizierung |
| --- | --- | --- |
| 1 | PASS | Frame startet verborgen und erscheint erst mit gültigen Bildabmessungen. Reales fehlendes Dev-Bild (1409): naturalWidth 0, Frame verborgen. Automatisiert Ladefehler bei 390/1024/1194/1440 px geprüft; kein geschrumpfter gelber Rahmen. |
| 2 | PASS | Ein einziges Range-Input, Auswahl über drei vorhandene Icon-Aktionen; Werte bleiben im bestehenden Editor-State. Dev bei 1194×700: zwei Zeilen, Toolbar 65 px; Reglerwechsel geprüft. Automatisiert Werterhalt, Reset und Rotation bei vier Breiten geprüft. |
| 3 | PASS | Drei symmetrisch angeordnete Griffstriche statt versetzter wiederholter Hintergrundgrafik. Megapixel-Crop in Dev geöffnet und visuell geprüft; Cancel ausgeführt. |
| 4 | PASS | Native Dialoge liegen im Browser-Top-Layer, unabhängig vom Dokument-z-index. Bestehende ganze Statusleiste wird in den obersten nativen Dialog übernommen und beim Schließen zurückgeführt. Browsertest mit zwei showModal-Dialogen: Leiste sichtbar, Rückkehr in ersten Dialog und anschließend body erfolgreich. Dirty-Kontext bleibt getrennt. Kein echter iPad-Hardwaretest. |

Beleg: `/private/tmp/fluxo-block2-shared-slider.png`. Gemeinsame Status-/Fokusregression, PHP-/JS-Syntax und Diff-Whitespace zusätzlich geprüft. Die oben genannten offenen End-to-End-Tests des ursprünglichen Blocks bleiben offen.

## Nachkorrekturen — drei Punkte vom 30.10., 20:31

Die frühere Statusleisten-Prüfung hatte ui.js erst nach dem Footer geladen und den realen Initialisierungsfehler deshalb nicht erfasst. Das ist korrigiert: Footer wird beim ersten Render gefunden; das bestehende Element folgt jeder obersten sichtbaren Lightbox. Auch das native `open`-Attribut wird beim Schließen beobachtet.

| Nr. | Status | Verifizierung |
| --- | --- | --- |
| 1 | PASS | `.selected` und aria-pressed folgen der aktiven Aktion. Vorhandene Highlight-Farbe auch nach Hover; sichtbares Label entfernt, zugänglicher Input-Name bleibt. Dev bei 1194 px: Slider 778 px breit. Template-Test bei 390/1024/1194/1440 px inklusive Werterhalt und Hover-Transition erfolgreich. |
| 2 | PASS | Vorhandenes Grip-Icon in kompaktem rechteckigem Griff statt Hintergrundstrichen. Megapixel-Crop in Dev visuell kontrolliert; gemessene Abweichung zwischen Griffmitte und Crop-Rahmenmitte: 0 px. |
| 3 | PASS | Reale Folge: Users → Lars editieren → Access → Add passkey (nur Dialogansicht, keine Bestätigung/Registrierung). Statusleiste sichtbar im zweiten nativen Dialog. Schließen führt sie zurück in die User-Lightbox, anschließendes Schließen in body. Keine Konsolenfehler. Automatisiert zusätzlich Footer erst nach ui.js, normale/nativer Dialoge und close() geprüft. |

Belege: `/private/tmp/fluxo-final-shared-slider.png`, `/private/tmp/fluxo-statusbar-real-nested.png`. Gemeinsame Feedback-/Fokusregression, PHP-/JS-Syntax und Diff-Whitespace erfolgreich. Kein echter iPad-Hardwaretest und keine Änderung an Medien oder Zugangsdaten.

## Crop follow-up, 2026-10-31 (original items 1–5)

1. PASS: adjustment buttons moved into the right action group, dropdown fills remaining width; slider overlays the preview. Production-template fixture checked at 1440, 1194, 1024 and 390 px; Firefox Dev UI opened and visually checked without saving media.
2. PASS: Original ratio reads the original source dimensions and refits the frame. Ratio assertion in interactive fixture and button exercised in Firefox Dev.
3. PASS: action buttons have equal dimensions; crop button no longer inherits a mismatched shadow. Production-template fixture measures heights; desktop UI visually checked.
4. BLOCKED (native iPad verification only): removed premature viewport clamping during resize; fit occurs on release. All four outward corner drags, fixed/free aspect, pan, simulated pinch, normalized save, cancellation and loading/error cleanup pass in fixtures. White rounded brackets have dark contrast edges; final screenshot inspected. Physical iPad gestures are not available to this session.
5. PASS: petrol scrim increased to 94%; visually checked in Firefox Dev and fixture screenshot.

Commands: crop-frame-ui.mjs and block2-media-ui.mjs (FLUXO_PLAYWRIGHT_MODULE points to installed Playwright). PHP lint and JS syntax pass. Fixtures report no JS errors. Existing PHP_errors.log has no new entries (latest entry 29 Oct). Real project media was not saved or altered. IAB connection unavailable; native Firefox used as fallback. Actual save/backend round-trip was simulated in disposable fixture, not performed on media 1515.

### Normal preview outline during crop
- Fixed: crop previously hid only the image, leaving its parent `.frameView` highlight outline visible. Hide the complete preview frame while cropping and restore its prior visibility through the shared close path.
- PASS in production-code fixture at 1440/1194/1024/390 px: preview frame hidden while cropping and visible after Cancel; crop handles, save/loading and error cleanup regression checks pass. Narrow screenshot inspected. JS syntax/diff checks pass. No real-media save or physical iPad test performed for this follow-up.

### Crop save overlay / sidebar / opaque scrim / ratio icon (2026-10-31)
1. PASS (local fixture): crop-sized loading overlay now performs backdrop blur; spinner center is asserted against crop bounds, not the source image, at 1440/1194/1024/390 px. Saving screenshot visually inspected. Shared spinner retained, actions disabled while saving.
2. PASS (local production-template fixture): removed sidebar toggle markup, handler and obsolete collapse CSS. Metadata remains visible; fit, responsive tools and retained image adjustments pass.
3. PASS: scrim uses opaque cmsMainColor directly. Fixture screenshot inspected.
4. PASS: Original ratio uses existing cmsIcon-expand; ratio operation regression passes.
PHP lint, JS syntax and diff checks pass, fixture browser errors empty. Live Firefox attempt was interrupted by concurrent user interaction; no further native actions performed. Physical iPad and live-server save are not tested in this follow-up; save/error paths use disposable simulated responses.

### Crop grip alignment follow-up
- Removed rectangular pseudo-element box shadow; contrast shadow now follows only the painted grip via drop-shadow.
- Corner strokes align their center with the frame corner; edge grips keep their center on the frame line. Explicit 32px min-height prevents global button sizing from shifting them.
- Hover/active/focus backgrounds remain transparent; coarse-pointer hit-area extension retained.
- PASS: crop fixture with body.cms (global button styles active), transparent hover, no rectangular shadow, exact control height and four-corner drag/save regressions at 1440/1194/1024/390. Final screenshot visually inspected. Physical iPad not available; simulated touch only.

## Follow-up: reload guard, toolbar and outer crop grips (2026-10-31, items 1–6)

1. PASS: removed the global guard based merely on `.fileFrame`/`.imageEditor` existence. Native reload uses actual feedback state or enabled adjustment Apply actions. Crop guard compares normalized crop geometry to its initial selection; viewport fitting does not count as an edit. Firefox Dev: unchanged image reload and unchanged open crop reload succeeded without warning; rotate then reload displayed Firefox's native confirmation, Stay + Discard returned to clean state. No media saved. Fixtures also verify clean/dirty/discard and crop save/cancel/error cleanup.
2. PASS (research; no native-iPad claim): documented iOS Safari beforeunload limitation checked in MDN and WebKit bug 219102. No fallback dialog or mobile workaround introduced. Physical iPad not available to this test session.
3. PASS: removed conflicting 2/2.25rem minimum heights and special yellow crop-button styling. All toolbar controls including both split Apply buttons use cmsControl and border-box. Heights/baselines measured at 1440/1194/1024/390; existing adjustment/format/fit regressions pass.
4. PASS: crop frame centered in the viewport below the crop toolbar, independent of the underlying sidebar/stage. Normalized saved crop unchanged by this visual fitting. Fixture center assertions at four viewport sizes, screenshot inspection and Firefox Dev visual check.
5. PASS (proposal only): sensible future additions are optional rule-of-thirds grid and reset-to-initial-crop; no new functions implemented without selection by user.
6. PASS (fixture): corner and edge grips touch the exterior of the frame outline. Edge grips have square frame-facing sides and rounded outer sides. Computed geometry/radii, transparent hover, no rectangular shadow, all four corner drags, fixed/free ratios, pan/pinch, save bounds checked at desktop/tablet/narrow sizes. Screenshot inspected; real iPad gestures not exercised.

Shared UI regression (nested dialogs, dirty-state preservation and focus) passed. Fixture JS errors empty. Syntax and diff checks pass. PHP log unchanged, latest entry 29 Oct. IAB connection unavailable; native Firefox fallback used in a temporary tab and closed after discarding local preview edits.
Sources: https://developer.mozilla.org/en-US/docs/Web/Events/beforeunload and https://bugs.webkit.org/show_bug.cgi?id=219102

### Crop follow-up — desktop size, grid and grips (2026-10-31)

| Original item | Status | Verification |
| --- | --- | --- |
| 1 | PASS | Crop fitting is capped at 1100 × 760 CSS px on viewports >=1400px with hover/fine pointer. Tablet/coarse-pointer sizing is unchanged. Production-code browser fixtures tested at 2560×1440, 1920×1080, 1440×900, 1194×700 touch, 1024×600 touch and 390×844 touch; desktop bounds asserted. Firefox Dev crop visually inspected. |
| 2 | PASS | Toggleable thirds grid uses the existing selected button style and does not dirty the image. Reset restores the selection and aspect mode present when crop opened; no save request. Grid on/off, reset after pan/resize/pinch, dirty-state clearing, normalized save and loading overlay tested in fixtures. Grid and original-ratio/reset actions also exercised in Firefox Dev without saving project data. |
| 3 | PASS | Removed grip drop-shadow; computed filter and box-shadow are none. Existing outside-edge positioning retained. Desktop Firefox visual inspection plus responsive fixture screenshots checked. |

Regression: existing media-editor UI fixture passed (retained adjustments, rotate/flip/reset, spacing, loading and no JS errors). PHP lint, JS syntax and diff whitespace checks passed. PHP error log has no new entries (latest 2026-10-29). Physical iPad/Safari hardware testing was unavailable; touch coverage above is emulated, not a native-device claim.

### Crop follow-up — fixed ratio and compact toolbar (2026-10-31)

| Item | Status | Verification |
| --- | --- | --- |
| Original ratio | PASS | Hidden for fixed width/height formats; both original-ratio and aspect-choice handlers reject overrides of a fixed ratio. Free-format action remains available. Tested production-code fixtures for fixed/free formats and actual Firefox Dev fixed-format crop. |
| iPad toolbar | PASS | Short hint “Move image · Pinch or scroll to zoom”; secondary actions use icons with accessible labels/tooltips on coarse pointers or widths <=85rem. Apply retains text. Single-row layout asserted at 1194×700; narrow and desktop fixture screenshots inspected. Fixture has no icon font loaded, so icon appearance was not inferred from it. |
| Desktop zoom | PASS | Wheel/trackpad scrolling over image zooms around pointer and prevents page scrolling, preserving output ratio. Wheel zoom, existing pinch/pan, reset, save and loading/error flows passed at six responsive sizes; no JS errors. |

PHP lint, JavaScript syntax and diff checks passed. Native iPad/Safari and physical trackpad gestures remain untested; touch tests are emulated. Actual Dev test cancelled without saving media.

### Crop gesture toolbar (2026-10-31)

| Original item | Status | Verification |
| --- | --- | --- |
| 1 — hide toolbar during crop gestures | PASS | Existing pointer/pinch lifecycle toggles a CSS class. Visibility hides the whole info/action bar without changing its measured footprint or moving the crop. Pointer release, pointercancel, touchend and touchcancel restore it. Production-code fixtures at 2560, 1920, 1440, 1194 touch, 1024 touch and 390 touch assert hiding during pan/resize/pinch, restoration and cancellation. Screenshot during tablet-sized pan visually inspected. Existing crop save/error/reset and normalized geometry regressions pass; no JS errors, syntax and diff checks pass. |

No real project media changed. Physical iPad testing unavailable; touch tests are emulated.

### Square crop buttons (2026-10-31)

1. PASS — All crop actions are icon-only square controls using `--cmsControl`, including Apply (highlight retained). Secondary crop actions share the existing image-editor button CSS rule; accessible names/tooltips retained. Square dimensions asserted at six desktop/tablet/narrow sizes; crop, grid, reset, save/error and gesture regressions passed without JS errors. Firefox Dev appearance visually checked; cancelled without saving. PHP lint and diff checks pass. Native iPad unavailable (emulated touch coverage only).

### Crop spacing and toolbar slide (2026-10-31)

| Item | Status | Verification |
| --- | --- | --- |
| 1 — toolbar effect | PASS | 160–180ms opacity/slide-up while interacting, slide-down on release/cancel; reduced-motion disables transitions. Toolbar layout height remains stable for fitting. Responsive browser fixtures exercise pan/resize/pinch/cancel and reduced-motion. |
| 2 — Safari accidental dismissal | BLOCKED for native verification | Crop surface uses touch-action:none, overscroll containment, selection/callout suppression, non-passive touchstart preventDefault, and pointer capture for pan/resize. Single-touch default cancellation and existing pinch behavior tested in emulation. Physical iPad unavailable. OS screen-edge gestures cannot be guaranteed suppressed from webpage code. |
| 3 — clearance | PASS | Fitting uses a 48–96 CSS px responsive gap on all devices, including below toolbar. Tests assert grip clearance after outward resizing at six viewport sizes; narrow/tablet screenshots visually inspected. Normalized crop/reset/save regressions retained. |

Reference: Apple Safari Web Content Guide, Handling Events (preventing default multi-touch browser actions); Apple UIViewController preferredScreenEdgesDeferringSystemGestures documentation distinguishes OS edge gestures from app handling. No custom workaround for dismissing the Safari app/window was added.

### Crop edge continuation and dirty actions (2026-10-31)

1. Edge continuation: while an outward handle reaches the 40px viewport edge zone, keep its displayed frame stationary and gradually scale the source about the opposite anchor. This extends the selected area without moving the held handle or running fit animation during the gesture. Limited to 2.5 seconds per edge phase and a normalized selection cap in both dimensions (max 2× source or 4× initial selection). Release/cancel/close stop the animation loop; normal fit happens after release.
2. Apply uses the existing normalized changed() comparison, disabled initially and after reset; unchanged Enter also cannot save. Cancel and Escape confirm discarding changed crops; internal successful-save/error cleanup remains unconditional. Rejecting confirmation keeps all edits.

Verification: production-code browser fixtures at six desktop/tablet/narrow sizes test stationary edge frame, continuing relative crop growth, time-limit stop, pan/pinch/resize, unchanged/changed/reset Apply, accepting and rejecting discard, saved normalized crop and loading/error cleanup. Screenshots visually checked. No real media saved; no new PHP log entries. Physical iPad unavailable (touch emulated).

### Crop return frame, edge speed and pan target (2026-10-31)

| Original item | Status | Verification |
| --- | --- | --- |
| 1 — yellow editor frame after closing | PASS | Crop close dispatches cropend to its preview; the existing editor fitBox/paint functions run after overlay removal. Integration fixture saves a replacement with changed natural aspect ratio and asserts image/frame bounds coincide and ratio updates at 1440/1194/1024/390 widths. No real media changed. |
| 2 — faster edge extension | PASS | Source scaling now time-based (0.0005/ms), moderately faster than prior 0.995/frame; avoids dependence on display refresh rate. Existing 2.5s/size bounds retained. Six-size crop fixtures verify continuation and stop. |
| 3 — drag only on image | PASS | Pan begins only on an img target; frame grips remain independent. Empty cropFrame pointerdown does not start interaction, while existing source pan, grip resize and pinch tests pass. |

Editor/crop fixtures report no JS errors; responsive screenshots visually inspected. PHP lint, JS syntax and diff checks pass. Native iPad unavailable; touch emulated.

## Unified image editor — 31 October 2026

Normal media view now retains the format selector and Edit action. Adjustments and crop use one editor state and one save action. Existing render order (crop, then adjustments) is preserved. Saves include the exact target media ID rather than relying on another tab's panel state. Existing split-Apply component provides Apply to all formats.

The latest unnumbered requirements are numbered here in their supplied order for verification:

| Item | Status | Actual verification |
| --- | --- | --- |
| 1 — zoom whole element without crop | PASS | Production JS exercised in Chromium fixtures at 1920, 1194, 1024 and 390px: wheel zoom leaves normalized crop unchanged and does not activate Apply. Crop pan/pinch/resize regressions pass separately. |
| 2 — compact format switch inside editor | PASS | Button list opened and format switched in fixtures; pending changes require confirmation. Existing format selector remains in normal preview. No native select in unified surface. |
| 3 — centered image, bottom hint, opposing hide directions | PASS | Responsive screenshots inspected at desktop/tablet/narrow widths; native Firefox Dev editor visually inspected. Hint at bottom, toolbar at top; production interaction classes move hint down and controls up, with reduced-motion rules. |
| Combined adjustments and crop, Apply/all | PASS | Fixtures exercise rotation, flip and brightness retained on entering crop, Apply/all payload and successful close. Isolated PHP action tests geometry+tune saves across formats and rejects invalid crop before writes. Native Firefox exercised edit, rotate, crop, dirty Apply and discard confirmation without saving project media. |
| Real rendered output and native iPad | BLOCKED | No physical iPad control available. CLI PHP lacks Imagick, so final generated image pixels were not verified through a real backend round-trip. Actual project media was deliberately not altered. |

`unified-image-editor-ui.mjs`, `block2-media-ui.mjs`, `crop-frame-ui.mjs`, `unified-image-save.php`, PHP lint and JS syntax pass. Fixture browser errors: none. This is not a declaration that native iPad or real image rendering passed.

## Editor controls and magnetic edges — 31 October 2026

| Item (request order) | Status | Verification |
| --- | --- | --- |
| 1 — old page-wide modifier grid | PASS (absent) | Searched CMS/system GUI, editable handlers and debug scripts. No active page-wide modifier-grid implementation found to remove; existing crop-only Grid remains. No unrelated keyboard shortcuts removed. |
| 2 — white image tools, grey utilities, highlighted labelled Apply | PASS | Native Firefox Dev screenshot visually checked; Chromium computed-style assertions distinguish edit/utility colours and verify Apply label at 1920/1194/1024/390px. Responsive screenshots inspected. |
| 3 — magnetic crop alignment without a hard stop | PASS | New fixture pans source to within 3px of crop edge, verifies exact alignment, then drags 30px beyond. Wheel zoom snaps source width exactly to frame width and continues beyond on next input. All four sizes pass. Existing six-size crop pan/pinch/resize, dirty guard and save/loading regressions pass. |

Snapping uses a 5px plateau with a smooth release to 15px. Raw gesture/zoom positions keep accumulating, so the displayed snap does not trap further movement. Uses transformed image edges for quarter-turns/flips. No project media saved. Native iPad gestures remain untested; touch tests use browser emulation. No fixture JS errors; syntax and diff checks pass.

## Preview and unified editor follow-up — 31 October 2026

| Original number | Status | Actual verification |
| --- | --- | --- |
| 1 — highlighted Edit | PASS | Production template assertion and native Firefox visual inspection. |
| 2 — shared format dropdown | PASS | Existing splitApply/dropdownMenus component exercised in Firefox and four-size Chromium fixtures; selection reopens the correct format. |
| 3 — preview frame after editor | PASS | Integration fixture now loads actual Pages MML CSS after system CSS. Save with changed image aspect ratio and Cancel both leave image/frame bounds aligned at desktop/tablet/narrow sizes. |
| 4 — checkerboard view zoom | PASS | Preview zoom buttons, fit and touch pinch exercised; no save requests or editor opening. Firefox zoom/fit checked. |
| 5 — edit frame | PASS | Computed outline assertion and native Firefox visual inspection outside crop. |
| 6 — tool dependencies | PASS | Format/Apply menus close when adjustment opens; opening a menu hides adjustment slider; Crop and colour adjustment coexist. Escape closes menu first. |
| 7 — button groups | PASS | Responsive screenshots and native Firefox inspected; white image tools, separated grey utilities and highlighted Apply. |
| 8 — no opening animation | PASS | Production root animation count is zero in all four viewport fixtures. |
| 9 — stronger snap/grid | PASS | Production gesture handlers tested for exact edge, zoom and active grid-third alignment, followed by continued movement beyond snap. Plateau now 8px, smooth release to 22px. |
| 10 — Apply all excludes crop | PASS | Isolated PHP action test verifies only current format receives crop geometry while other formats receive adjustments. Crop-only edits disable Apply-all. |

Final checks: unified-image-editor-ui.mjs (1920/1194/1024/390px), block2-media-ui.mjs (including real Pages CSS), crop-frame-ui.mjs (six sizes), unified-image-save.php, PHP lint, JS syntax and diff checks pass. Fixture browser errors: none. Native Firefox console showed no application exceptions; PHP log contained no new entries. No real project media saved.

Limitations: native physical iPad unavailable (touch emulated); real generated image pixels not verified because CLI PHP lacks Imagick. Backend Apply-all verification uses disposable in-memory fixtures. These limits remain open and are not represented as native-device or real-rendering passes. Obsolete unused imageEditorRaster CSS removed; no active modifier-grid handler existed.

## Editor popovers and format refresh follow-up — 31 October 2026

| Original number | Status | Actual verification |
| --- | --- | --- |
| 1 | PASS | Delayed format response fixture keeps old surface connected until replacement is ready. Cancel during loading does not reopen the editor. Format selection also exercised in native Firefox without saving media. |
| 2 | PASS | Real pointer clicks change the shared range input at 1920/1194/1024/390px; image drag does not start. Responsive slider screenshots inspected. |
| 3 | PASS | Real pointer drag on fitted checkerboard preview leaves its transform unchanged. Zoom/fit/pinch still work in four-size integration fixtures. |
| 4 | PASS | Discard unsaved edits now uses existing eraser icon; markup assertion and native Firefox inspection. |
| 5 | PASS | Original-ratio remains hidden for fixed-ratio formats, including crop mode. Utility hover/focus/active use opaque button colour rather than transparent cmsHover. Native Firefox and responsive screenshots inspected; real hover and keyboard-focus background alpha asserted fully opaque in all four viewport fixtures. |
| 6 | PASS | Format button and image-tool top/height bounds match in four-size production CSS fixtures. |
| 7 | BLOCKED | Existing theme-color meta retained; non-auth root and header now use same theme colour instead of grey root overriding it. PHP lint passes. Real iPad browser-chrome tint cannot be verified without physical device access. |
| 8 | PASS | Format, image adjustments, utilities and Apply separated; four-size screenshots visually inspected. |
| 9 | PASS | Production changeSrc handler exercised and cache-busting preview query asserted; save/cancel frame regression passes. New format metadata is loaded before switching preview. Final generated image pixels not compared. |
| 10 | PASS | Adjustment buttons use shared splitApply popover without chevron. Pointer input, outside click, selected state, mutual exclusion with Format/Apply and compatibility with Crop exercised. |

The general mobile bottom-sheet conversion now excludes only image-editor menus; they stay anchored below the whole wrapped toolbar. Other shared menus keep their existing behavior. Slider rendering reuses existing fileAdjust range styles. Native Firefox tested opening, range keyboard adjustment, discard and format switch; no real project media saved. Physical iPad remains unavailable, so this block is not fully device-verified.
