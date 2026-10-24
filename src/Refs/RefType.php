<?php

declare(strict_types=1);

namespace Systopic\Cms\Refs;

/**
 * One kind of target a reference can name - `page`, `media`, a project's own.
 *
 * It loads its targets in bulk (one statement for every reference of its
 * kind in a field), says which loaded target a reference means, and reads
 * from it: the url for an attribute, a property for text. Only what text()
 * lists can be read - content is written by editors, not developers, so
 * there is no access to arbitrary properties or methods.
 */
interface RefType
{
    /** the scheme: page, media */
    public function name(): string;

    /**
     * @param list<Ref> $refs every reference of this kind in the content
     * @return array<string, object> key() => target, only the ones found
     */
    public function load(array $refs): array;

    /** which loaded target a reference means - a page by id and language */
    public function key(Ref $ref): string;

    /** the url for href / src / poster - NULL when the target has none for it */
    public function url(object $target, Ref $ref, string $attribute): ?string;

    /** the property path as HTML, escaped - NULL when the path is not readable */
    public function text(object $target, Ref $ref): ?string;

    /**
     * What the CMS editor needs to know about a target beyond its url -
     * written as data-ref-<name>, never saved (Resolver::normalize()).
     *
     * @return array<string, string>
     */
    public function editorData(object $target): array;
}
