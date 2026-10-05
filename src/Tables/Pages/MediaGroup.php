<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Pages;

/**
 * A named group of a page's media ('main', 'galleryLeft' …) — successor of
 * the legacy `mediaGroup`.
 *
 *   $group->first          the first medium, or null
 *   $group[1]              the second one, or null
 *   $group->random         one of them (alias: shuffle)
 *   count($group), foreach ($group as $media)
 *
 * @implements \IteratorAggregate<int, Media>
 * @implements \ArrayAccess<int, Media>
 */
final class MediaGroup implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /** @param list<Media> $media */
    public function __construct(private array $media = []) {}

    public ?Media $first { get => $this->media[0] ?? null; }

    public ?Media $random {
        get => $this->media === [] ? null : $this->media[array_rand($this->media)];
    }

    /** alias of random */
    public ?Media $shuffle { get => $this->random; }

    /** @return list<Media> */
    public array $all { get => $this->media; }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->media);
    }

    public function count(): int
    {
        return count($this->media);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->media[$offset]);
    }

    public function offsetGet(mixed $offset): ?Media
    {
        return $this->media[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('MediaGroup is read-only');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('MediaGroup is read-only');
    }
}
