<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Builder;

/**
 * A resolved view template: which folder it lives in and which file it is.
 *
 * Counterpart of Panels\Builder\FolderHit. Both paths are absolute in fs
 * internal form (/V/www/…); PageNavigator turns them into a CWD-relative
 * path before fs::openDir(), the same way PanelNavigator does.
 */
readonly class ViewHit
{
    public function __construct(
        /** Absolute folder containing the template, no trailing slash. */
        public string $folder,

        /** Absolute path of the <view>.tpl.php file. */
        public string $file,

        /** Which root produced the hit — 'template' or 'views'. Debug only. */
        public string $source,
    ) {}
}
