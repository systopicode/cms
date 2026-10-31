# Image ratios and shared controls — 2026-10-31

Numbers A1/A2 refer to the first list; B1–B6 to the second list in the request.

## Behaviour

- A1: Shared WebKit/Gecko range track and circular thumb in forms.css. Editor-specific native thumb rules removed. Keyboard focus remains visible.
- A2: Root/body use the main theme colour, including dark appearance. Content/pylons retain their own surfaces. This gives Safari a consistent underlying canvas; browser chrome remains browser-controlled.
- B1/B4: Unified edit mode zooms/pans the whole element, including empty space around the source. A fitted element cannot pan. Cropping retains image-only dragging.
- B2/B3: Free MP crops resize freely. Original ratio adopts the source ratio without implicitly locking it. The adjacent lock/unlock action fixes the current ratio. Pixel formats and configured MP ratios remain constrained on every handle.
- B5: `aspect_ratio` is optional for MP formats (`16:9`, `4/3`, or a positive numeric ratio). Empty means free. Pixel mode removes MP/ratio metadata. `mediaFormats::fixedRatio` is shared by editor and central output sizing. MP dimensions obey the pixel budget and configured ratio, independent of source shape. Changing sizing clears generated files for that format; originals, saved crop and tuning metadata remain.
- B6: Crop thirds use dark/light/dark lines for contrast on both bright and dark images.

## Verification

- Running Dev app in Firefox: Media Settings field, MP editor, Crop, ratio lock/unlock, brightness slider opened. No saves to real project data.
- Safari and Firefox native Mac: disposable HTML with production CSS; range changed, circular thumb visually checked. Temporary tabs closed.
- Chrome production-component fixtures at desktop, tablet/touch and narrow widths: `image-ratio-ui.mjs`, `media-ratio-settings-ui.mjs`, `unified-image-editor-ui.mjs`, `crop-frame-ui.mjs`. Includes all eight grips, frame wheel/pinch, free/fixed ratios, manual lock, dropdowns/sliders, responsive table stacking, light/dark, no viewport overflow, and JS error assertions.
- PHP: system `media-group-policy.php` verifies persisted ratio validation/clearing and central sizes; `image-ratio-render.php` uses real Imagick to render free MP, ratio MP and fixed pixels in a disposable directory. `unified-image-save.php` covers existing save actions. No real project data changed.
- PHP syntax and diff whitespace checked. Project PHP error log contained only older entries, no new errors from this run.

## Remaining physical-device check

A2 is not fully verified: actual iPad Safari browser-chrome tint cannot be proven by desktop viewport emulation. Open a fresh page on the iPad in light and dark appearance and inspect the upper/lower browser bars. Physical iPad touch is also additional validation beyond synthetic touch/pinch fixtures.

## Follow-up 1–8 — navigation, labels and camera orientation

1. Editor listens to popstate without creating a history entry; removes its scrim, menus and listeners and restores the preview. Pending open responses are ignored after navigation. Verified with live Firefox Back/Forward and production-component navigation tests.
2. Grid uses white/highlight duotone like the outer frame. Visually checked at tablet/narrow sizes.
3. Frame resizing snaps in both directions to image bounds and enabled grid thirds. Ratio constraints remain; dragging can continue beyond the snap. Geometry tests cover grow/shrink, edges, grid and fixed ratio; UI regression covers grips.
4. Selected crop has transparent border/no box-shadow, matching other selected tools. Keyboard focus remains available.
5. Image-format table uses the shared stacked table, additionally triggered by its available container width (900px). Shared fold interactions work for this trigger too. Its sizing fields wrap within stacked rows. Production markup tested at 1440/1024/390, light/dark, with JS errors and overflow checked.
6. Optional `label` on image formats. Central `mediaFormats::label` falls back to the key. Media editor, WYSIWYG format map, image-link dialog and media repair picker use it. Technical keys/URLs remain unchanged. JSON persistence and fallback tested with disposable fixtures; actual Dev Settings fields inspected without saving.
7. The existing checkerboard loading spinner now uses the highlight variable. No new loading component.
8. The Imagick operator normalizes EXIF rotation and mirroring before measuring/resampling/converting, while keeping the uploaded original unchanged. Editor fallback reads use auto-orientation; uploaded JPEG dimension metadata swaps width/height where required. `image-orientation.php` processes real JPEG blobs with all eight EXIF orientations, verifying dimensions and corner colours. Existing generated aliases are not rewritten automatically by this change; rebuild the affected generated files when checking older uploads.

Physical iPad testing remains unavailable. Automated touch uses synthetic browser events; no actual camera upload was made into real project data. PHP log checked: no new errors from these tests.

Additional follow-up checks: `image-upload-orientation.php` exercises actual Files::store/describe in a disposable directory, checks oriented dimensions and byte-identical original. `crop-frame-magnet.mjs` tests inverse snapping. `image-ratio-ui.mjs` additionally asserts selected crop border/box-shadow, highlight loading spinner and popstate cleanup. A competing button border rule was found by the computed-style assertion and corrected, then retested. JSON format-label persistence and WYSIWYG label-map fallback pass in the media-policy test. Crop animation regression waits for actual hidden state rather than assuming completion after a fixed 210ms.

## Follow-up: video labels, Cancel, responsive cards, ordering and sliders (1–5)

1. Video formats expose an optional Label field even for legacy rows. File-editor, WYSIWYG and media-repair pickers use the shared label fallback. Verified the fields in running Firefox; disposable JSON tests verify persistence, fallback and video option order.
2. Cancel now has an X icon and label beside Apply in a single wrapping unit. Production UI fixtures verify exact row alignment at 1920, 1194, 1024 and 390 pixels, close/dirty confirmation and Apply behavior; screenshots inspected at 1194 and 390.
3. Foldable table styles follow the shared stacked state, including when the available content width is narrow inside a wide viewport. Removed desktop row padding from stacked image-format cards. Production settings markup tested at 1440/1194/1024/390 in light and dark: initially folded, actual toggle opens fields, validation and no viewport overflow. No project configuration changed.
4. Removed explicit reversal from file-editor and link format selectors. WYSIWYG already consumes the configured map directly. Disposable tests verify non-alphabetical order and labels; existing system formats retain their positions.
5. A delayed menu focusout no longer closes a menu that has since moved to another opener. Repeated pointer-based Brightness/Contrast/Saturation changes pass across all four editor viewports. Running Firefox: opened Brightness, switched to Contrast, moved slider to 101, then discarded changes without saving. UI fixture console: no errors; save-payload and crop-magnet regressions pass. PHP error log has no new entries (last existing entry: 29 October).

Physical iPad/Safari touch was not available; touch checks above use Chrome emulation. Fixtures use the production components with disposable media, not real project writes.
