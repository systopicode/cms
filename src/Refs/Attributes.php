<?php

declare(strict_types=1);

namespace Systopic\Cms\Refs;

/**
 * The attributes of one tag, read and changed by regex - the attribute part
 * of `<img src="…" alt="…">`, as Resolver and HtmlMigration see it. Any quote
 * style, any order; values are returned as written (entities still in) and
 * escaped when set.
 */
final class Attributes
{
    /** the value, NULL when the attribute is not there */
    public static function get(string $attrs, string $name): ?string
    {
        if (!preg_match(self::pattern(preg_quote($name, '~')), $attrs, $m)) {
            return null;
        }
        // one of the three quote styles matched; the others are '' or absent
        foreach ([1, 2, 3] as $group) {
            if (isset($m[$group]) && $m[$group] !== '') {
                return $m[$group];
            }
        }
        return '';
    }

    public static function set(string $attrs, string $name, string $value): string
    {
        $pair = $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"';
        $count = 0;
        $attrs = (string) preg_replace_callback(self::pattern(preg_quote($name, '~')), static fn(): string => $pair, $attrs, 1, $count);
        if ($count > 0) {
            return $attrs;
        }
        // before a self-closing slash, if there is one
        return preg_match('~\s*/\s*$~', $attrs)
            ? (string) preg_replace('~\s*/\s*$~', " $pair /", $attrs)
            : rtrim($attrs) . " $pair";
    }

    /** $name a literal name, or with $literal FALSE a pattern of names (data-ref-[a-z-]+) */
    public static function remove(string $attrs, string $name, bool $literal = true): string
    {
        $name = $literal ? preg_quote($name, '~') : $name;
        return (string) preg_replace('~\s+' . $name . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+)~i', '', $attrs);
    }

    private static function pattern(string $quotedName): string
    {
        return '~(?<=\s)' . $quotedName . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))~i';
    }
}
