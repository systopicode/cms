<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Pages;

/**
 * The media of a page, by group — successor of `medias::get($page)`.
 *
 *   $page->medias->group('main')->first        the first medium of 'main', or null
 *   $page->medias->group('galleryLeft')[1]     the second one
 *   count($page->medias->group('main'))
 *   $page->medias->has('main')                 does the page carry that group?
 *   $page->medias->main                        shorthand for group('main'), as before
 *
 * An unknown or empty group is an empty collection, never an error. The
 * media are `Media` objects (src, setFormat(), width, height, `<?= $media ?>`)
 * over `Tables\Medias\Model` rows.
 *
 * Loaded for the whole tree level the page came with, in one statement
 * (`PageTreeLevel::mediasOf()`): a list of twenty teasers costs one query, not
 * twenty. An alias shows the media of its original — the same rule as the
 * content.
 */
final class Medias
{
    /** @var array<string, MediaGroup>|null */
    private ?array $groups = null;

    /** @var list<Media> */
    private array $all = [];

    public function __construct(private readonly Page $page) {}

    /** One group — empty when the page has none in it. */
    public function group(string $name): MediaGroup
    {
        $this->load();
        return $this->groups[$name] ?? new MediaGroup();
    }

    public function has(string $name): bool
    {
        $this->load();
        return isset($this->groups[$name]) && count($this->groups[$name]) > 0;
    }

    /** @return list<Media> every medium of the page, in node order */
    public function all(): array
    {
        $this->load();
        return $this->all;
    }

    /** `$page->medias->main` — the group by name, as in the legacy surface. */
    public function __get(string $name): MediaGroup
    {
        return $this->group($name);
    }

    private function load(): void
    {
        if ($this->groups !== null) {
            return;
        }
        $byGroup = [];
        foreach ($this->page->generationFor()->mediasOf($this->page->node) as $row) {
            $media = new Media($row);
            // a medium may carry no group; the legacy collection filed it under ''
            $byGroup[(string) $media->group][] = $media;
            $this->all[] = $media;
        }
        $this->groups = array_map(static fn(array $list) => new MediaGroup($list), $byGroup);
    }
}
