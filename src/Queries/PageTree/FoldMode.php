<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\PageTree;

/**
 * What should happen to a folder.
 *
 * In the legacy tree this is the second parameter of `tree::fold($id, $mode)`,
 * a string with three permitted values — and `actions.php` passes the panel
 * there. That behaves like `'toggle'`, because every value other than `'open'`
 * does, but it is obviously not what was meant. An enum leaves no room for a
 * panel.
 */
enum FoldMode
{
    case Toggle;
    case Open;
    case Close;
}
