<?php

declare(strict_types=1);

namespace Systopic\Cms\Refs;

use Systopic\Db\Scope;
use Systopic\System\Queries\MediaNodes\MediaNodes;
use Systopic\System\Tables\Medias\Media;

/**
 * media:293 - a medium by node id, in a format with ?format=xx (original
 * without). The address comes from Media::$src at render time - nothing of
 * a path is stored, so another root, folder layout or a CDN changes every
 * reference at once.
 *
 *   src="media:293?format=collection"   the format's url
 *   href="media:85"                     the original (a download)
 *   poster="media:85"                   the still of a video
 *   {media:85}                          <img> for an image, a link for anything else
 *   {media:85.label}  .src  .width  .height  .filename
 */
final class MediaRefs implements RefType
{
    public function __construct(private Scope $scope) {}

    public function name(): string
    {
        return 'media';
    }

    public function key(Ref $ref): string
    {
        return (string) $ref->id;
    }

    public function load(array $refs): array
    {
        $found = [];
        $ids = array_values(array_unique(array_map(static fn(Ref $ref): int => $ref->id, $refs)));
        foreach (MediaNodes::byNodeIds($this->scope, $ids) as $id => $row) {
            $found[(string) $id] = new Media($row);
        }
        return $found;
    }

    public function url(object $target, Ref $ref, string $attribute): ?string
    {
        /** @var Media $target */
        if ($attribute === 'poster') {
            return $target->poster !== '' ? $target->poster : null;
        }
        return $target->setFormat($ref->param('format', 'original'))->src;
    }

    /** the image context menu offers formats for raster images only */
    public function editorData(object $target): array
    {
        /** @var Media $target */
        return ['mimetype' => (string) $target->mimetype];
    }

    public function text(object $target, Ref $ref): ?string
    {
        /** @var Media $target */
        $escape = static fn(string|int $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $format = $target->setFormat($ref->param('format', 'original'));
        return match (implode('.', $ref->path)) {
            '' => $target->type === 'image'
                ? '<img src="' . $escape($format->src) . '" alt="' . $escape($target->label) . '">'
                : '<a href="' . $escape($format->src) . '">' . $escape($target->label) . '</a>',
            'label' => $escape($target->label),
            'src' => $escape($format->src),
            'filename' => $escape($target->filename),
            'width' => $escape($target->width),
            'height' => $escape($target->height),
            default => null,
        };
    }
}
