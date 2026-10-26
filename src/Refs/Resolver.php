<?php

declare(strict_types=1);

namespace Systopic\Cms\Refs;

use Systopic\Cms\Queries\Params;
use Systopic\Db\Scope;

/**
 * References in content, resolved - the only place a stored text turns into
 * addresses and values.
 *
 * Content stores no paths: `href="page:43"`, `src="media:293?format=x"`,
 * `{page:43.label}` (see Ref). Three passes over a text, each one regex pass
 * plus one load per type for every reference in it:
 *
 *   render()           the site: urls and values in place; a missing target
 *                      drops its <img>/<video>/<iframe>, turns its <a> into
 *                      text, and its text reference into nothing
 *   renderForEditor()  the CMS editor: urls in place so the browser shows the
 *                      images, the reference kept beside it (data-ref-src …),
 *                      text references as a non-editable box with their value
 *   normalize()        what the editor saves, back to references - no lookups
 *
 * Types: page and media; a project adds its own with register().
 */
final class Resolver
{
    /** the attributes whose value may be a reference */
    public const URL_ATTRIBUTES = ['href', 'src', 'poster'];

    /** tags that are nothing without their target */
    private const DROPPED_WHEN_MISSING = ['img', 'video', 'audio', 'iframe', 'source', 'embed'];

    private const TAG = '~<([a-zA-Z][a-zA-Z0-9]*)(\s[^<>]*)?>~';

    /** @var array<string, \Closure(Scope, Params): RefType> */
    private static array $factories = [];

    /** @var array<string, RefType> */
    private array $types = [];

    public function __construct(Scope $scope, Params $params)
    {
        $this->types['page'] = new PageRefs($scope, $params);
        $this->types['media'] = new MediaRefs($scope);
        foreach (self::$factories as $name => $factory) {
            $this->types[$name] = $factory($scope, $params);
        }
    }

    /** a project's own kind of reference: Resolver::register('event', fn($scope, $params) => new EventRefs($scope)) */
    public static function register(string $name, \Closure $factory): void
    {
        self::$factories[$name] = $factory;
    }

    // ------------------------------------------------------------------ site

    public function render(string $html): string
    {
        return $this->resolve($html, false);
    }

    // ---------------------------------------------------------------- editor

    public function renderForEditor(string $html): string
    {
        return $this->resolve($html, true);
    }

    /**
     * What the editor sends, back to references: data-ref-<attribute> wins
     * over the url the browser saw, a reference box becomes {…} again.
     */
    public static function normalize(string $html): string
    {
        if (!str_contains($html, 'data-ref')) {
            return $html;
        }
        $html = (string) preg_replace_callback(
            '~<span\b[^>]*\sdata-ref="([^"]*)"[^>]*>.*?</span>~s',
            static fn(array $m): string => '{' . html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '}',
            $html,
        );
        return (string) preg_replace_callback(self::TAG, static function (array $m): string {
            $attrs = $m[2] ?? '';
            if (!str_contains($attrs, 'data-ref')) {
                return $m[0];
            }
            foreach (self::URL_ATTRIBUTES as $name) {
                $ref = Attributes::get($attrs, "data-ref-$name");
                if ($ref !== null) {
                    $attrs = Attributes::set(Attributes::remove($attrs, "data-ref-$name"), $name, html_entity_decode($ref, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
            }
            // what was there for the editor only (data-ref-missing, data-ref-mimetype …)
            return '<' . $m[1] . Attributes::remove($attrs, 'data-ref-[a-z-]+', false) . '>';
        }, $html);
    }

    // ------------------------------------------------------------- internals

    private function resolve(string $html, bool $editor): string
    {
        // every reference has "type:digits" - most texts have none at all
        if (!preg_match('~[a-z]:\d~', $html)) {
            return $html;
        }
        $refs = [];
        preg_match_all(self::TAG, $html, $tags, PREG_SET_ORDER);
        foreach ($tags as $tag) {
            foreach (self::URL_ATTRIBUTES as $name) {
                $ref = $this->refIn($tag[2] ?? '', $name);
                if ($ref !== null) {
                    $refs[] = $ref;
                }
            }
        }
        preg_match_all('~\{(' . Ref::CORE . '(?:\.[a-zA-Z][a-zA-Z0-9]*)*)\}~', $html, $texts);
        foreach ($texts[1] as $inner) {
            $ref = Ref::fromText($inner);
            if ($ref !== null && isset($this->types[$ref->type])) {
                $refs[] = $ref;
            }
        }
        if ($refs === []) {
            return $html;
        }
        $targets = $this->load($refs);

        $html = (string) preg_replace_callback(self::TAG, function (array $m) use ($targets, $editor): string {
            $tagName = strtolower($m[1]);
            $attrs = $m[2] ?? '';
            foreach (self::URL_ATTRIBUTES as $name) {
                $ref = $this->refIn($attrs, $name);
                if ($ref === null) {
                    continue;
                }
                $target = $targets[$ref->type . '|' . $this->types[$ref->type]->key($ref)] ?? null;
                $url = $target === null ? null : $this->types[$ref->type]->url($target, $ref, $name);
                if ($name === 'poster' && ($url === null || $url === '')) {
                    $attrs = Attributes::remove($attrs, 'poster');
                    $attrs = Attributes::remove($attrs, 'data-ref-poster');
                    continue;
                }
                if ($editor) {
                    $attrs = Attributes::set($attrs, $name, (string) $url);
                    $attrs = Attributes::set($attrs, "data-ref-$name", $ref->toAttribute());
                    if ($url === null) {
                        $attrs = Attributes::set($attrs, 'data-ref-missing', $ref->toAttribute());
                    } else {
                        foreach ($this->types[$ref->type]->editorData($target) as $key => $value) {
                            $attrs = Attributes::set($attrs, "data-ref-$key", $value);
                        }
                    }
                } elseif ($url !== null) {
                    $attrs = Attributes::set($attrs, $name, $url);
                } elseif (in_array($tagName, self::DROPPED_WHEN_MISSING, true)) {
                    return '';
                } else {
                    $attrs = Attributes::remove($attrs, $name); // an <a> without href is its text
                }
            }
            // A film in the text keeps its still. Older tags only stored src.
            if ($tagName === 'video' && Attributes::get($attrs, 'poster') === null && Attributes::get($attrs, 'data-ref-poster') === null) {
                $srcRef = $this->refIn($m[2] ?? '', 'src');
                if ($srcRef !== null && isset($this->types[$srcRef->type])) {
                    $still = $targets[$srcRef->type . '|' . $this->types[$srcRef->type]->key($srcRef)] ?? null;
                    $poster = $still === null ? null : $this->types[$srcRef->type]->url($still, $srcRef, 'poster');
                    if ($poster !== null && $poster !== '') {
                        $attrs = Attributes::set($attrs, 'poster', $poster);
                        if ($editor) {
                            $attrs = Attributes::set($attrs, 'data-ref-poster', $srcRef->toAttribute());
                        }
                    }
                }
            }
            return '<' . $m[1] . $attrs . '>';
        }, $html);

        return (string) preg_replace_callback('~\{(' . Ref::CORE . '(?:\.[a-zA-Z][a-zA-Z0-9]*)*)\}~', function (array $m) use ($targets, $editor): string {
            $ref = Ref::fromText($m[1]);
            if ($ref === null || !isset($this->types[$ref->type])) {
                return $m[0];
            }
            $type = $this->types[$ref->type];
            $target = $targets[$ref->type . '|' . $type->key($ref)] ?? null;
            $value = $target === null ? null : $type->text($target, $ref);
            if (!$editor) {
                return (string) $value;
            }
            $data = htmlspecialchars($ref->toText(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $value === null
                ? "<span class=\"ref\" contenteditable=\"false\" data-ref=\"$data\" data-ref-missing=\"$data\">{" . $data . '}</span>'
                : "<span class=\"ref\" contenteditable=\"false\" data-ref=\"$data\">$value</span>";
        }, $html);
    }

    /**
     * Every target, one load per type.
     *
     * @param list<Ref> $refs
     * @return array<string, object> 'type|key' => target
     */
    private function load(array $refs): array
    {
        $byType = [];
        foreach ($refs as $ref) {
            $byType[$ref->type][] = $ref;
        }
        $targets = [];
        foreach ($byType as $name => $ofType) {
            foreach ($this->types[$name]->load($ofType) as $key => $target) {
                $targets["$name|$key"] = $target;
            }
        }
        return $targets;
    }

    /** the reference in attribute $name, when there is one of a known type */
    private function refIn(string $attrs, string $name): ?Ref
    {
        $value = Attributes::get($attrs, $name);
        $ref = $value === null ? null : Ref::fromAttribute($value);
        return $ref !== null && isset($this->types[$ref->type]) ? $ref : null;
    }

}
