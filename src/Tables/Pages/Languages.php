<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Pages;

/**
 * The languages an installation offers, from the project configuration.
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
        if (defined('LANGUAGES') && LANGUAGES !== []) {
            return (array) LANGUAGES;
        }
        $code = self::configuredDefault();
        $label = class_exists(\Locale::class) ? \Locale::getDisplayLanguage($code, 'en') : null;
        return [$code => ['label' => $label ?: strtoupper($code), 'code' => $code, 'activated' => true]];
    }

    /** @return array<string, array{label: string, code: string, activated: bool}> */
    public static function activated(): array
    {
        return array_filter(self::all(), static fn(array $l): bool => !empty($l['activated']));
    }

    /** `DEFAULT_LANG` from the settings while it is activated, else the first activated language. */
    public static function default(): string
    {
        $configured = self::configuredDefault();
        $active = self::activated();
        if (isset($active[$configured])) {
            return $configured;
        }
        return (string) (array_key_first($active) ?? $configured);
    }

    /** Old saved editor state and old links may refer to a removed language. */
    public static function resolve(mixed $code): string
    {
        return is_string($code) && isset(self::activated()[$code]) ? $code : self::default();
    }

    private static function configuredDefault(): string
    {
        return defined('DEFAULT_LANG') && DEFAULT_LANG !== '' ? (string) DEFAULT_LANG : 'de';
    }
}
