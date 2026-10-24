<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Fields;

/**
 * An html field as source to read and edit in the HTML lightbox: every block
 * on its own line, indented by nesting, a line after each <br> and one for each
 * image or video; the rest of the inline markup stays as it is.
 *
 * Line breaks in the source are layout only and go on save: between two words
 * a break becomes the space it renders as, beside a tag it is dropped.
 */
final class HtmlSource
{
    private const BLOCK = '~(</?(?:p|div|h[1-6]|ul|ol|li|dl|dt|dd|table|caption|thead|tbody|tfoot|tr|td|th|blockquote|figure|figcaption|section|article|header|footer|nav|aside|hr)\b[^>]*>)~i';

    public static function pretty(string $html): string
    {
        if (self::keepsWhitespace($html)) {
            return $html;
        }
        $out = '';
        $depth = 0;
        $sameLine = false;
        foreach (preg_split(self::BLOCK, self::compact($html), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
            $indent = "\n" . str_repeat("\t", $depth);
            if (!preg_match(self::BLOCK, $part)) {
                $part = trim((string) preg_replace(
                    ['~(<br\b[^>]*>)\s*~i', '~(\s?)(<(?:img|video|iframe|picture)\b)~i'],
                    ['$1' . $indent, '$1' . $indent . '$2'],
                    trim($part),
                ));
                if ($part !== '') {
                    $out .= ($sameLine ? '' : $indent) . $part;
                    $sameLine = true;
                }
                continue;
            }
            if ($part[1] === '/') {
                $depth = max(0, $depth - 1);
                $out .= ($sameLine ? '' : "\n" . str_repeat("\t", $depth)) . $part;
                $sameLine = false;
                continue;
            }
            $out .= $indent . $part;
            $void = stripos($part, '<hr') === 0 || str_ends_with($part, '/>');
            $depth += $void ? 0 : 1;
            $sameLine = !$void;
        }
        return ltrim($out, "\n");
    }

    public static function compact(string $html): string
    {
        if (self::keepsWhitespace($html)) {
            return trim($html);
        }
        return trim((string) preg_replace(
            ['~(?<=[^\s>])[\t ]*\r?\n\s*(?=[^\s<])~', '~\r?\n[\t ]*~'],
            [' ', ''],
            $html,
        ));
    }

    private static function keepsWhitespace(string $html): bool
    {
        return preg_match('~<(pre|textarea)\b~i', $html) === 1;
    }
}
