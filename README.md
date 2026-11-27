# systopic/cms

Fluxo Content Management System for multi-domain websites: pages, paths,
sites, page settings, the page panels and the site rendering.

```bash
composer require systopic/cms
```

Requires PHP 8.4 with DOM, [systopic/system](https://github.com/systopicode/system)
and [systopic/db](https://github.com/systopicode/db).

## Administration

Pages opens at `/admin/pages/` relative to the site root. The legacy
`/cms/` entry redirects there. Use `panels/admin/` for project panel
overrides, `Root\Admin` in PHP panel namespaces and `panels.admin` in
JavaScript route keys. The Composer package remains `systopic/cms`.

## Documentation

Public pages send their captured head/body layers as HTML. The page client
adopts those nodes for later AJAX navigation. `appSite::headTags()` owns the
escaped page metadata, canonical URL, real public language alternatives and
page JSON-LD on both paths; project heads retain assets and site-wide data.

`sitemap.xml` and `llms.txt` use `Search\PublicPages`: published, anonymously
readable pages in activated languages, deduplicated by canonical URL. The
public original is canonical for content aliases when it has its own public
URL; organizational pages without a page template are excluded and send
noindex. Aliases keep their existing navigation and editor behavior.
The configured default language supplies `x-default` in addition to its language
link. Test environments and previews send noindex; private websites export
no page list. Search and AI in the sidebar can check the anonymous first HTML.

The system scaffold routes `robots.txt` through the frontend. Existing
installations need the updated generated `.htaccess` (preserve project rules).
Galerie Lattemann and Stefan Pohl were updated locally with this change.

- [Publishing, media and editing safety](docs/tools-and-edit-safety.md)
- [Sidebar and touch interaction](docs/pages-sidebar-touch.md)
- [Media editor and responsive picture output](docs/media-editor-and-output.md)
- [Public output for search engines and AI](docs/public-search-output.md)
- [Media/search release verification, 27 November 2026](docs/qa/media-search-release-2026-11-27.md)

© 2026 systopic GmbH. Publicly available source code under the
[systopic Software License](LICENSE). Use, modification and redistribution
are permitted only as set out in that license or a separate agreement.
