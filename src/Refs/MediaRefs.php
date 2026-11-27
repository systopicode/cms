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
            $media = new Media($row);
            if ($media->readable) $found[(string) $id] = $media;
        }
        return $found;
    }

    public function url(object $target, Ref $ref, string $attribute): ?string
    {
        /** @var Media $target */
        if (!$target->readable) return null;
        if ($attribute === 'poster') {
            return $target->poster !== '' ? $target->poster : null;
        }
        return $attribute === 'src' && str_starts_with((string) $target->mimetype, 'image/')
            ? self::imageSource($target, $ref->param('format', 'master'))
            : $target->setFormat($ref->param('format', 'original'))->src;
    }

    /** Display an image through its master or declared crop; downloads keep the original. */
    public static function imageSource(Media $media, string $format = 'master'): string
    {
        $ratio = \Systopic\System\Media\Variants::editorRatio($format);
        return $format === 'master' || $ratio !== null
            ? $media->getCrop($ratio?->name)->getUrl()
            : $media->setFormat($format)->src;
    }

    public static function hasImageViews(Ref $ref): bool
    {
        foreach (['desktop', 'tablet', 'mobile'] as $view) if ($ref->param($view) !== null) return true;
        return false;
    }

    /** Per occurrence, using the same project widths as the page preview buttons. */
    public static function imageViews(Media $media, Ref $ref): array
    {
        $preview = \Systopic\Cms\Pages\PreviewWidths::read(\fs::$siteRoot . 'css');
        $views = [];
        foreach (['desktop', 'tablet', 'mobile'] as $view) {
            $format = $ref->param($view, $ref->param('format', 'master'));
            $ratio = \Systopic\System\Media\Variants::editorRatio($format);
            $picture = $media->getCrop($ratio?->name);
            $views[$view] = ['format' => $ratio?->suffix ?? 'master', 'src' => $picture->getUrl(), 'width' => $picture->width, 'height' => $picture->height];
        }
        return $preview + ['views' => $views];
    }

    public static function imageViewMarkup(Media $media, Ref $ref, string $attrs, bool $editor): string
    {
        $hasViews = self::hasImageViews($ref);
        if ($editor && !$hasViews) return '<img' . $attrs . '>';
        // Ordinary images need no device queries or three identical previews.
        $data = $hasViews ? self::imageViews($media, $ref) : null;
        if ($editor) {
            $original = [];
            foreach (['width', 'height'] as $name) $original[$name] = Attributes::get($attrs, $name);
            $attrs = Attributes::set($attrs, 'data-ref-image-size', (string) json_encode($original));
            $attrs = Attributes::set($attrs, 'data-ref-image-views', (string) json_encode($data, JSON_UNESCAPED_SLASHES));
            return '<img' . $attrs . '>';
        }
        $formats = [];
        if ($hasViews) {
            foreach ($data['queries'] as $view => $query) {
                $formats[$query] = $data['views'][$view]['format'];
            }
        }
        $options = [];
        foreach (['class', 'style', 'alt', 'loading', 'decoding', 'fetchpriority', 'sizes'] as $name) {
            $value = Attributes::get($attrs, $name);
            if ($value !== null) $options[$name] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $format = $hasViews ? $data['views']['desktop']['format'] : $ref->param('format', 'master');
        $ratio = \Systopic\System\Media\Variants::editorRatio($format);
        $picture = $media->getCrop($ratio?->name);
        if (!$hasViews && $format !== 'master' && $ratio === null) {
            // Explicit originals and old fixed formats retain their exact file.
            $options = array_replace($options, ['src' => self::imageSource($media, $format), 'srcset' => null, 'sizes' => null,
                'width' => Attributes::get($attrs, 'width'), 'height' => Attributes::get($attrs, 'height')]);
        }
        $html = $picture->toPictureHtml($formats, $options);
        return (string) preg_replace_callback('~<img(\s[^<>]*)?>~', static function (array $m) use ($attrs): string {
            // Keep the renderer's native image sources, sizing and loading hints.
            // Event, link and editor-independent attributes of the occurrence stay intact.
            $generated = $m[1] ?? '';
            foreach (['srcset', 'sizes', 'width', 'height'] as $name) $attrs = Attributes::remove($attrs, $name);
            preg_match_all('~\s([\w:-]+)\s*=~', $generated, $names);
            foreach ($names[1] as $name) {
                $value = Attributes::get($generated, $name);
                if ($value !== null) $attrs = Attributes::set($attrs, $name, html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            return '<img' . $attrs . '>';
        }, $html);
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
        if (!$target->readable) return null;
        $escape = static fn(string|int $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $format = $target->setFormat($ref->param('format', 'original'));
        return match (implode('.', $ref->path)) {
            '' => $target->type === 'image'
                ? self::imageViewMarkup($target, $ref, '', false)
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
