<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages;

/**
 * The preview widths a site defines: read from the @media width conditions in
 * its own stylesheets. A layout that is not in the CSS gets no button.
 *
 * - mobile:  the widest breakpoint up to 640px
 * - tablet:  the widest breakpoint from 641px to 1100px
 * - desktop: one pixel above the widest of the two, so the desktop rules apply
 *
 * `min-width: 961px` counts as a breakpoint at 960px. Minified files are
 * libraries (a player, animations) whose breakpoints say nothing about the site.
 */
final class PreviewWidths
{
    /** @return array{desktop: ?int, tablet: ?int, mobile: ?int} */
    public static function of(string $cssDir): array
    {
        $breakpoints = [];
        foreach (glob(rtrim($cssDir, '/') . '/*.css') ?: [] as $file) {
            $css = (string) file_get_contents($file);
            if ($css === '' || substr_count($css, "\n") < strlen($css) / 400) {
                continue;
            }
            preg_match_all('~@media([^{]+)\{~', $css, $queries);
            foreach ($queries[1] as $query) {
                preg_match_all('~(max|min)-width:\s*(\d+)px~', $query, $widths, PREG_SET_ORDER);
                foreach ($widths as [, $kind, $px]) {
                    $breakpoints[] = $kind === 'max' ? (int) $px : (int) $px - 1;
                }
            }
        }
        $widest = static fn(int $from, int $to): ?int => array_reduce(
            $breakpoints,
            static fn(?int $carry, int $px): ?int => $px >= $from && $px <= $to ? max($carry ?? 0, $px) : $carry,
        );
        $mobile = $widest(1, 640);
        $tablet = $widest(641, 1100);
        $below = $tablet ?? $mobile;
        return ['desktop' => $below === null ? null : $below + 1, 'tablet' => $tablet, 'mobile' => $mobile];
    }
}
