<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages;

/** Project CSS owns both the editor's device choices and the picture sources. */
final class PreviewWidths
{
    /** @return array{desktop: ?int, tablet: ?int, mobile: ?int} */
    public static function of(string $cssDir): array
    {
        return self::read($cssDir)['widths'];
    }

    /**
     * Label the actual project rule: cms-preview: mobile (or tablet) in a
     * comment immediately before @media. An optional width, e.g. tablet 900px,
     * chooses the editor width. The complete query goes to picture unchanged.
     * Unlabelled projects retain legacy choices, using x_responsive.css when
     * present so player/lightbox breakpoints cannot change the devices.
     *
     * @return array{widths: array{desktop: ?int, tablet: ?int, mobile: ?int}, queries: array<string, string>}
     */
    public static function read(string $cssDir): array
    {
        $files = [];
        foreach (glob(rtrim($cssDir, '/') . '/*.css') ?: [] as $file) {
            $files[$file] = (string) file_get_contents($file);
        }
        $profiles = [];
        foreach ($files as $css) {
            preg_match_all('~/\*\s*cms-preview:\s*(mobile|tablet)(?:\s+(\d+)px)?\s*\*/\s*@media\s*([^{}]+)\{~i', $css, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $query = trim($match[3]);
                $upper = self::upperWidth($query);
                $width = ($match[2] ?? '') !== '' ? (int) $match[2] : $upper;
                if ($width !== null && $width > 0) $profiles[strtolower($match[1])] = ['width' => $width, 'upper' => $upper ?? $width, 'query' => $query];
            }
        }
        if ($profiles === []) {
            $responsive = rtrim($cssDir, '/') . '/x_responsive.css';
            $css = $files[$responsive] ?? implode("\n", $files);
            $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? $css;
            preg_match_all('~@media\s*([^{}]+)\{~i', $css, $matches);
            $upper = [];
            foreach ($matches[1] as $query) {
                $width = self::upperWidth($query);
                // Legacy mobile-first styles describe the same boundaries with
                // min-width. Keep their device choices during the transition.
                if ($width === null && preg_match('~min-width\s*:\s*(\d+(?:\.\d+)?)(px|em|rem)~i', $query, $min)) {
                    $width = (int) ceil((float) $min[1] * (strtolower($min[2]) === 'px' ? 1 : 16)) - 1;
                }
                if ($width !== null && $width <= 1100) $upper[$width] = $width;
            }
            sort($upper);
            $mobile = array_values(array_filter($upper, static fn(int $w): bool => $w <= 640));
            $tablet = array_values(array_filter($upper, static fn(int $w): bool => $w > 640));
            if (count($upper) === 1) { $mobile = $upper; $tablet = []; }
            foreach (['mobile' => $mobile, 'tablet' => $tablet] as $device => $values) {
                if ($values !== []) {
                    $width = max($values);
                    $profiles[$device] = ['width' => $width, 'upper' => $width, 'query' => '(max-width: ' . $width . 'px)'];
                }
            }
        }
        $widths = ['desktop' => null, 'tablet' => $profiles['tablet']['width'] ?? null, 'mobile' => $profiles['mobile']['width'] ?? null];
        if ($profiles !== []) $widths['desktop'] = max(array_column($profiles, 'upper')) + 1;
        $queries = [];
        foreach (['mobile', 'tablet'] as $device) if (isset($profiles[$device])) $queries[$device] = $profiles[$device]['query'];
        return ['widths' => $widths, 'queries' => $queries];
    }

    /** Converted by the existing project @media-to-container pipeline. */
    public static function previewCss(string $cssDir): string
    {
        $queries = self::read($cssDir)['queries'];
        $css = ':root { --cms-image-device: desktop; }';
        // First picture source wins; mobile therefore comes last in CSS.
        foreach (['tablet', 'mobile'] as $device) {
            if (isset($queries[$device])) $css .= "\n@media " . $queries[$device] . ' { :root { --cms-image-device: ' . $device . '; } }';
        }
        return $css;
    }

    /** A preview size; the complete query is still evaluated by the browser. */
    private static function upperWidth(string $query): ?int
    {
        $unit = '(\d+(?:\.\d+)?)(px|em|rem)';
        $values = [];
        foreach (['~max-width\s*:\s*' . $unit . '~i', '~width\s*(<=|<)\s*' . $unit . '~i', '~' . $unit . '\s*(>=|>)\s*width~i'] as $index => $pattern) {
            preg_match_all($pattern, $query, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                [$number, $units] = $index === 1 ? [$match[2], $match[3]] : [$match[1], $match[2]];
                $exclusive = $index === 1 ? $match[1] === '<' : ($index === 2 && $match[3] === '>');
                $px = (float) $number * (strtolower($units) === 'px' ? 1 : 16);
                $values[] = $exclusive ? (int) ceil($px) - 1 : (int) floor($px);
            }
        }
        return $values === [] ? null : min($values);
    }
}
