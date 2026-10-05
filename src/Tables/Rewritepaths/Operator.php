<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Rewritepaths;

/**
 * What `cms_rewritepaths` knows how to do without asking the database.
 *
 * Successor of the string half of `pageRewritepathFuncs`. The half that asks
 * — is a path taken, by whom — is `Queries\RewritepathLookup\RewritepathLookup`.
 */
final class Operator
{
    /**
     * A label as a path segment: umlauts spelled out, everything else that is
     * not a letter, digit, `#`, `?`, `/`, `-` or `.` becomes `_`.
     *
     * The same rules as `pageRewritepathFuncs::safeStr()`, character for
     * character — paths already in the database were made with them.
     */
    public static function safeStr(string $name): string
    {
        $path = preg_replace(
            ['/ä/', '/ö/', '/ü/', '/Ä/', '/Ö/', '/Ü/', '/ß/', "/'/", '/[^#?A-Za-z0-9\/\-\.]+/'],
            ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss', '', '_'],
            $name,
        );
        return trim((string) $path, " \t\n\r\0\x0B_-");
    }

    /** The automatic path of a label: safe and lower case. */
    public static function fromLabel(string $label): string
    {
        return strtolower(self::safeStr($label));
    }

    /**
     * One path of a page is the primary one; the others stop being it.
     *
     * @param list<Model> $paths every path of the page
     * @return list<Model> the ones that changed
     */
    public static function makePrimary(array $paths, Model $primary): array
    {
        $changed = [];
        foreach ($paths as $path) {
            $should = $path === $primary;
            if ((bool) $path->isPrimary !== $should) {
                $path->isPrimary = $should;
                $changed[] = $path;
            }
        }
        return $changed;
    }
}
