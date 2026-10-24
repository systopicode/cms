# fluxo GUI — how to work on it

This is the hand-over for anyone (human or AI) who continues the admin GUI
("fluxo cms") — the panels behind `https://<host>/cms/`. Read it once before
you change a panel, a stylesheet or a script.

## 1. The two packages

| Package | Role |
|---|---|
| `systopic/system` | The core: boot, database, auth, users, media, panels engine, assets. For now it also carries most of the admin GUI (header, menu, users, settings, tools, shared CSS/JS). |
| `systopic/cms` | Pages and websites: page tree, page editor, preview, sites, frontend rendering. |

The rule: **a system runs without the cms, never the other way round.**
`systopic/cms` may use everything in `systopic/system`; `systopic/system`
must not need `systopic/cms`. Some places still break that rule (see
section 11). Do not add new ones.

Projects that use only `systopic/system` (and so the GUI in it):
`pwk.systopic.net` (loads `system/public/css/` and `system/public/js/cms/`
directly and uses `copyToClipboard`) and `spaceticker.app.src` (its panels
extend `Systopic\System\Panels\Root\Cms\Panel`). Check both before you move
or rename anything in `system/public` or `system/panels/cms`.

In a project, `vendor/systopic/*` are symlinks to `~/Sites/packages/*`, so an
edit in the package is live in every project at once.

## 2. How packages plug in

Each package registers itself in its `autoload.php` through
`Systopic\System\Sys\Packages` and `Systopic\System\Sys\Hooks\Hooks`:

```php
Packages::addLib('systopic/cms');              // lib/ folders, classes
Packages::addPanels(__DIR__ . '/panels/');     // panel tree, merged by folder name
Packages::addPanelAssets(__DIR__ . '/public/'); // public/css/ and public/js/ in every panel head
Packages::addFrontend(new \Systopic\Cms\Frontend());
Hooks::on('afterConnect', static fn() => ...);
```

- Panel trees of all packages are merged by folder name. `cms/panels/cms/010_pages`
  and `system/panels/cms/080_users` both end up under `/cms/`.
- `addPanelAssets()` is how a package brings GUI CSS/JS that must be there
  on every panel page (example: `cms/public/css/pages.css` for the page
  header and preview). `system/panels/cms/head.tpl.php` loads them after the
  system CSS/JS.

## 3. Panels

A panel is a folder. Folder names map to URLs after the sort prefix is
stripped: `080_users/010_users/` → `/cms/users/users/`.

| File | Purpose |
|---|---|
| `panel.php` | Class `Panel` in the namespace of its path, e.g. `Systopic\System\Panels\Root\Cms\Users\Users`. Extends `Systopic\System\Panels\Root\Cms\Panel`. State, computed properties (`get =>` hooks), defaults (`getDefaultState()`). |
| `actions.php` | `switch ($this->action)` — one `case` per action. Ends with `$this->updateView('main')` (or `updateViews([...])`) for what must be re-rendered. |
| `*.tpl.php` | Views. `main.tpl.php` is the content, `controls.tpl.php` the header bar, `pylon.tpl.php` the left column, `sidebar.tpl.php` the right column, `lightbox.tpl.php` a dialog, `footer`/`status` the bottom. |
| `panel.js` | Client code for the panel (`sys.lib.add(setup, fn)`, `route: 'panels.cms.users.users'`). |
| `*.css` | Styles only this panel needs (see section 5). |

Layout (`system/panels/cms/body.tpl.php`): menu → `pylon` → content
(`controls` header, `main`, `footer`) → `sidebar` → status bar → `lightbox`.
A parent hands a slot down with `$this->withChildSelected('controls')`,
`$this->withChildSelected()` (main) etc. `withView('users')` includes a view
of the same panel, `withChild('user', 'signetmenu')` a view of a named child.

Useful members of `PanelNode`: `$this->state` (persisted per user),
`$this->action`, `$this->scope` (database), `child('edit')`,
`updateView()`, `updateViews()`, `onLoadSelected()` (runs when the panel is
opened by GET — read query filters there), `loadJS()`.

Request helpers: `\http::get('user_id')` (query), `\http::dataset('user_id')`
(the `data-*` of the clicked element and its ancestors), `\http::dragdata()`
(drop payload), `\http::redirect('edit/?user_id=1')`.

Messages: `\message::error('...')`, `\message::flush()` in the view that
should show them.

**A lightbox** is a child panel with `public bool $inNavi = false;` and a
`lightbox.tpl.php`:

```php
<div class="cms cmslightbox" data-size="2">   <!-- 1, 2 or 3 -->
	<section>
		<header><h1>…</h1><ul class="barmenu"><li><a class="ajax" href="../" title="Close"><i class="fa-regular fa-xmark"></i></a></li></ul></header>
		<main>…</main>
	</section>
</div>
```

Open it with a plain link `<a class="ajax" href="edit/?user_id=3">`, close it
with `href="../"`. Example: `system/panels/cms/080_users/010_users/edit/`.

## 4. Everything is AJAX

No full page loads inside the GUI. The client sends the action, the server
answers with the views to replace.

- Links: `class="ajax"` → loads the panel without reload.
- Clicks: `data-on_click="addUser"` calls the action `addUser` of the panel
  the element is in (`"../hijackUser"` or `"sidebar/updateParam"` addresses
  another panel relative to it). If `panel.js` defines a method with that
  name, the method runs instead (e.g. `filterUsers`).
- Also `data-on_change`, `data-on_blur`, `data-on_input`, `data-on_drop`
  (+ `data-drop_accept`), `data-on_draginit`.
- Ids travel as `data-*` on the element or an ancestor: `<tr data-user_id="3">`
  → `\http::dataset('user_id')`.
- Confirm before destructive actions:
  `data-on_click="userConfirm" data-action="removeUser" data-confirm="Delete this user? This cannot be undone."`
- Inputs inside a form that save on change use `name="value"` and an
  `data-on_change`/`data-on_blur` action.

## 5. CSS

Layers, declared in the panel head: `@layer cms, project, preview;`.
GUI CSS is in `cms`, project overrides in `project`, the page preview in
`preview`.

Where styles live:

| Path | Content |
|---|---|
| `system/public/css/ui.css` | Tokens and basic GUI rules. |
| `system/public/css/theme.css` | Colours a project may override. |
| `system/public/css/system/NN_*.css` | Shared GUI building blocks, in load order: base, pylon, table, widgets, spinner, tree (`27_tree.css`), layout, nav, sidebar, forms, lightbox, components, … |
| `system/public/css/dropdown.css`, `HTMLeditor.css`, `imageEditor.css` | Dropdown/context menu, WYSIWYG editor, image editor — generic, any GUI may use them. |
| `cms/public/css/pages.css` | Page header (crumbs, title, language and device switch) and the page preview. |
| `<panel>/*.css` | Only rules for that one panel. |

Rules:

- **Module CSS is for module-specific rules only.** If two panels need it,
  it belongs in the shared system CSS.
- **Use tokens, never raw colours**: `--cmsBgColor`, `--cmsTextColor`,
  `--cmsMainColor`, `--cmsHighlightColor`, `--cmsLine`, `--cmsMuted`,
  `--cmsSuccess`, `--cmsError`, `--cmsWarning`, `--cmsRadius`,
  `--cmsShadow`, … (all in `ui.css`).
- **There are two button styles, no third**: `.button` (grey,
  `--cmsButtonBg`, derived from `--cmsBgColor`) and `.button.submit`
  (highlight). `.button.icononly` is the square icon variant. Need a
  different look? Change the token, not the markup.
- Building blocks to reuse before writing new CSS:
  `section.widget` (with `header > h2`, optional `.is-list` for a table,
  `.is-wide`), `.widgets` (grid of widgets), `label.field > span + input`,
  `td.icons > ul > li > a.button.icononly`, `.barmenu`, `.cmslightbox[data-size]`,
  `.tree`, `.dropdown`/`.contextmenu`, chips/tags (`tags.css`).
- Icons: Font Awesome 6 (`fa-regular`, `fa-duotone fa-regular`).
- Flags: `<img src="https://flagcdn.com/{code}.svg">` (see
  `cms/panels/cms/010_pages/controls.tpl.php`).

Fonts in use (`system/public/fonts/`): `InterVariable.woff2` (GUI),
`fontawesome/fa-regular-400.woff2`, `fontawesome/fa-duotone-regular-400.woff2`,
`Lato-Regular.ttf` (text drawn into images server-side). Nothing else.

## 6. JavaScript

- `system/public/js/` — core (`sys.js`, `lib.js`, `panelNode.js`,
  `panelTree.js`, `core/`), loaded on every page.
- `system/public/js/cms/` — GUI scripts on every panel page:
  `ui.js`, `dropdownMenus.js` (dropdowns, split buttons), `HTMLeditor.*.js`
  (WYSIWYG + its context menu), `imageEditor.js`, `jobs.js`, `portrait.js`.
- `<package>/public/js/` — registered with `addPanelAssets()`.
- `<panel>/panel.js` — loaded with the panel, also on AJAX navigation.
  **Other `.js` files in a panel folder only load on a full page render**;
  put shared code in a public folder instead.
- Shared panel methods (`toggleMenu`, `inputPrompt`, `getContextmenu`,
  `userConfirm`) live on `classes.panelNode` (`system/public/js/panelNode.js`).

## 7. Texts and header

- All GUI texts are **English**, short and plain ("Delete this user? This
  cannot be undone.", "Add user", "Search").
- The panel header (`controls`) holds **only global buttons** for the
  panel. Row actions sit in the row (`td.icons`), section actions in the
  widget header.
- Every list page has a one-line hint under the `h1`
  (`<p class="tagsHint">The pen opens the user.</p>`).

## 8. `_legacy/`

Nothing is deleted outright. Dead files and rules move to `_legacy/` in the
same package, under their original path, and are no longer loaded:

- `system/_legacy/public/css/dead-rules.css` — CSS rules whose classes no
  template or script uses anymore (grouped by source file).
- `system/_legacy/public/fonts/`, `_legacy/public/js/cms/`, `_legacy/lib/…`.

Before you call a class dead, search **all** packages and projects
(`system`, `cms`, `db`, `relay`, `pwk`, `spaceticker`, the project) and look
for built class names (`'is-' + state`, `type + 'Dragging'`). Known built
ones: `*Dragging`, `*Dragged`, `cmsPane-*`, `dz-*`, `is-unknown/online/offline`.

## 9. Assets and URLs

Never write `/vendor/...` or package paths into templates. Use the helpers:

```php
fsLoader::includeCSSFilesInLayer(fs::toInternal($dir . 'css/'), 'cms');
fsLoader::includeScriptFiles(fs::toInternal(__DIR__ . '/public/site/pagesRenderer.js'));
\http::$sysRoot . 'public/img/system/signet.svg';
```

They resolve the right URL for the asset mode (source or published
`/assets/<package>/`). Tools → Assets shows what is served from where.

## 10. Checking your work

Every GUI change is checked in the browser before it counts as done:
open the panel, use the feature, look at a screenshot. At minimum go through
Pages (tree, page header, language menu, preview, sidebar), Settings, Users
(list + user lightbox), Tools (logs + a lightbox). Test with the session
language set to one that has pages — an Italian session shows an empty tree.

Commit only when asked. Commit `system` and `cms` separately, each in its own
repository.

## 11. Open work

Known places where the core still knows the cms (fix step by step):

- `system/loader.php` and `public/js/core/server.js` — login URL `cms/auth/`.
- `public/js/core/event.js` — checks `body.cms`.
- `server.command.js`, `clientDocument.php` — call `insertHtmlAtCursor`,
  `openContextMenu`.
- `site/head.system.tpl.php` — `findByName(['cms','pages'])`.
- `Mail/Letter.php`, `Auth/Portrait.php` — read `theme.css` and the logo.
- `app.php` — `$renderStyle = 'cms'`; `messageGuide.php` links to `cms/pages/…`.
- `HTMLeditor.funcs.js` — endpoints `cms/pages/editor/…`;
  `imageEditor.js` — `cms/pages/getImageEditor`, `updateMediaCrop`.
- `panels/cms/head.tpl.php` — inline `<style>` for `article.cmsContent
  [contenteditable]` (unlayered on purpose) belongs to the cms.

Decided, not done yet: **the remaining GUI moves to `systopic/cms`**
(`system/public/css`, `public/js/cms`, `public/img/system`, fonts, and
`system/panels/cms` with a namespace change to `Systopic\Cms\Panels\…`).
Before that, pwk and spaceticker must require `systopic/cms` and be adapted
(paths in pwk's `panels/head.tpl.php`, base class in spaceticker's panels,
stale copies in spaceticker's `public/assets`). Note that `cms/autoload.php`
then also boots sites and the frontend for those projects — split the
GUI part from the pages part first, or make the page parts optional.
