<?php

declare(strict_types=1);

namespace Systopic\Cms\Media;

use Systopic\System\Http\Assets;
use Systopic\System\Sys\Packages;

/**
 * The SketchUp viewer shipped with the CMS, so every project can show a
 * .skp in the file lightbox without bringing its own copy.
 */
final class SkpViewer
{
    public static function moduleUrl(): string
    {
        $base = Packages::urls()['systopic/cms'] ?? '';
        $dir = Assets::rootDir('systopic/cms');
        if ($base === '' || $dir === null) {
            return '';
        }
        $rel = 'public/modules/skp-viewer.js';
        return Assets::href(rtrim($base, '/') . '/' . $rel, $dir . $rel);
    }
}
