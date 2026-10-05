<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Pages;

/**
 * The languages an installation offers — out of `LANGUAGES` in its index.php.
 *
 * Not a table, but the axis every page row sits on, so it lives with them.
 * `page::getLanguages()` / `page::getDefaultLanguage()` in the legacy code,
 * without the page class around them.
 */
final class Languages
{
    /**
     * @return array<string, array{label: string, code: string, activated: bool}> iso => definition
     */
    public static function all(): array
    {
        if (defined('LANGUAGES')) {
            return (array) LANGUAGES;
        }
        return ['en' => ['label' => 'English', 'code' => 'en', 'activated' => true]];
    }

    /** @return array<string, array{label: string, code: string, activated: bool}> */
    public static function activated(): array
    {
        return array_filter(self::all(), static fn(array $l): bool => !empty($l['activated']));
    }

    /** The first activated language — the one a fresh panel state starts in. */
    public static function default(): string
    {
        $first = array_key_first(self::activated()) ?? array_key_first(self::all());
        return (string) ($first ?? 'en');
    }
}
