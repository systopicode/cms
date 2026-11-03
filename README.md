# systopic/cms

Fluxo Content Management System for multi-domain websites: pages, paths,
sites, page settings, the page panels and the site rendering.

```bash
composer require systopic/cms
```

Requires PHP 8.4, [systopic/system](https://github.com/systopicode/system)
and [systopic/db](https://github.com/systopicode/db).

## Administration

Pages opens at `/admin/pages/` relative to the site root. The legacy
`/cms/` entry redirects there. Use `panels/admin/` for project panel
overrides, `Root\Admin` in PHP panel namespaces and `panels.admin` in
JavaScript route keys. The Composer package remains `systopic/cms`.

## Documentation

- [Publishing, media and editing safety](docs/tools-and-edit-safety.md)
- [Sidebar and touch interaction](docs/pages-sidebar-touch.md)

© 2026 systopic GmbH. Publicly available source code under the
[systopic Software License](LICENSE). Use, modification and redistribution
are permitted only as set out in that license or a separate agreement.
