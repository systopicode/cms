<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages;

/**
 * One root folder that contributes view templates to the pages renderer.
 *
 * Pages have two kinds of view sources and both are modelled as roots:
 *
 *  - a *views* root — a flat folder of site-wide views (head, body, navi,
 *    footer, …). Typically <project>/pages/ and <sys>/site/.
 *  - a *templates* root — a folder of per-template subfolders, one per value
 *    of cms_pages.page_template_name (100_ausstellungen/, 700_galerie/, …).
 *    Typically <project>/templates/. The subfolder comes from the page record,
 *    so this root is only ever consulted through the selected page's template.
 *
 * Roots are registered in order and resolved LAST-WINS, mirroring
 * PanelRootFolder: a project can override a view the system ships.
 *
 * $namespaceRoot is reserved for per-template RouteNode subclasses
 * (templates/<x>/template.php); it is unused while no site needs one.
 *
 * $site binds a views root to one site (multi-site, config/ `sites`):
 * <project>/pages/<siteName>/ is consulted only for nodes of that site and
 * wins over <project>/pages/ (registered after it, last-wins). That is how a
 * site gets its own head, body or navigation without touching the others.
 */
readonly class PageRootFolder
{
    public const VIEWS     = 'views';
    public const TEMPLATES = 'templates';

    public function __construct(
        /** Filesystem directory object pointing to the root folder. */
        public \fsDir $dir,

        /** self::VIEWS or self::TEMPLATES. */
        public string $kind = self::VIEWS,

        /** Root namespace for per-template classes, e.g. 'Galerie\Pages'. */
        public string $namespaceRoot = '',

        /** Name of the site this root serves, null for every site. */
        public ?string $site = null,
    ) {}
}
