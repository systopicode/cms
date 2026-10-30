# Icon migration

CMS uses the central `systopic/system` 1.3 icon registry, local Tabler Outline and a small Solar Line Duotone subset. It no longer supplies Font Awesome classes or loads a Pro font. Main Pages navigation uses Solar documents; other controls use Tabler. The website edit bar loads `css/11_icons.css` inside its shadow root without document-level font rules.

The coordinated migration, licences, exact mappings, upgrade/rollback instructions and validation live in the sibling System package under `docs/icon-migration/`. CMS now requires System `^1.3` for the registry API. Neither package has been committed, tagged or published by this migration.

The prebuilt SketchUp viewer's three application toolbar icon literals were updated surgically. Preserve those changes when rebuilding its bundle; the bundled Three.js/parser code was not rewritten. Source/build provenance for the full viewer remains an existing packaging concern.
