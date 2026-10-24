<?php

declare(strict_types=1);

namespace Systopic\Cms\Refs;

/**
 * One reference in content: what it points at, and what is read from it.
 *
 *   page:43                       in an attribute (href, src, poster): the url
 *   page:43?lang=en#kontakt       with parameters and an anchor
 *   media:293?format=collection
 *   {page:43.label}               in text: a property, read through the object model
 *   {page:43.meta.description}    a path of properties
 *
 * Parameter values are words ([\w-]+) - so the dot after them is
 * unambiguous, and nothing that needs escaping travels in a reference.
 */
final class Ref
{
    /** type:id, optional ?k=v&k=v, optional #fragment (attributes) - the core every form shares */
    public const CORE = '([a-z][a-z0-9]*):(\d+)((?:\?[a-z][a-z0-9_]*=[\w-]+(?:(?:&|&amp;)[a-z][a-z0-9_]*=[\w-]+)*)?)';

    /**
     * @param array<string, string> $params
     * @param list<string>          $path   the properties, [] for the default
     */
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly array $params = [],
        public readonly ?string $fragment = null,
        public readonly array $path = [],
    ) {}

    /** 'page:43?lang=en#x' as it stands in an attribute (entities allowed); NULL when it is none */
    public static function fromAttribute(string $value): ?self
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('~^' . self::CORE . '(?:#([\w-]+))?$~', $value, $m)) {
            return null;
        }
        return new self($m[1], (int) $m[2], self::params($m[3]), isset($m[4]) && $m[4] !== '' ? $m[4] : null);
    }

    /** 'page:43.meta.description' - the inside of {…} in text; NULL when it is none */
    public static function fromText(string $inner): ?self
    {
        $inner = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('~^' . self::CORE . '((?:\.[a-zA-Z][a-zA-Z0-9]*)*)$~', $inner, $m)) {
            return null;
        }
        $path = $m[4] === '' ? [] : explode('.', substr($m[4], 1));
        return new self($m[1], (int) $m[2], self::params($m[3]), null, $path);
    }

    public function param(string $name, ?string $default = null): ?string
    {
        return $this->params[$name] ?? $default;
    }

    /** the attribute form: page:43?lang=en#x */
    public function toAttribute(): string
    {
        return $this->core() . ($this->fragment !== null ? '#' . $this->fragment : '');
    }

    /** the text form, without braces: page:43.label */
    public function toText(): string
    {
        return $this->core() . ($this->path !== [] ? '.' . implode('.', $this->path) : '');
    }

    /** the same target with another property path */
    public function withPath(array $path): self
    {
        return new self($this->type, $this->id, $this->params, $this->fragment, $path);
    }

    private function core(): string
    {
        return "{$this->type}:{$this->id}" . ($this->params !== [] ? '?' . http_build_query($this->params) : '');
    }

    /** @return array<string, string> */
    private static function params(string $query): array
    {
        if ($query === '') {
            return [];
        }
        parse_str(str_replace('&amp;', '&', substr($query, 1)), $params);
        return array_map('strval', $params);
    }
}
