<?php
declare(strict_types=1);

namespace Systopic\Cms\Search;

/** Keeps project assets and site-wide structured data beside the shared page metadata. */
final class HeadMarkup
{
    public static function withoutPageMetadata(string $html): string
    {
        if (trim($html) === '') return $html;
        $doc = \Dom\HTMLDocument::createFromString('<!doctype html><html><head><meta charset="utf-8">' . $html . '</head><body></body></html>', LIBXML_NOERROR);
        foreach (iterator_to_array($doc->head->querySelectorAll('title,meta,link,script')) as $node) {
            $name = strtolower((string) $node->getAttribute('name'));
            $property = strtolower((string) $node->getAttribute('property'));
            $managed = $node->hasAttribute('data-cms-meta') || $node->localName === 'title'
                || ($node->localName === 'meta' && (in_array($name, ['description','abstract','robots','language'], true)
                    || str_starts_with($name, 'twitter:') || str_starts_with($property, 'og:')
                    || strtolower((string) $node->getAttribute('http-equiv')) === 'content-language'))
                || ($node->localName === 'link' && (strtolower((string) $node->getAttribute('rel')) === 'canonical'
                    || $node->hasAttribute('hreflang')));
            if ($managed) { $node->remove(); continue; }
            if ($node->localName === 'script' && strtolower((string) $node->getAttribute('type')) === 'application/ld+json') {
                $data = json_decode($node->textContent, true);
                if (!is_array($data)) continue;
                $nodes = $data['@graph'] ?? (isset($data['@type']) ? [$data] : $data);
                if (!is_array($nodes)) continue;
                $kept = array_values(array_filter($nodes, static fn($n) => is_array($n)
                    && array_intersect(array_filter((array) ($n['@type'] ?? []), 'is_string'), ['WebPage','BreadcrumbList']) === []));
                if ($kept === []) { $node->remove(); continue; }
                $node->textContent = json_encode(['@context'=>'https://schema.org','@graph'=>$kept], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            }
        }
        $result = '';
        foreach ($doc->head->childNodes as $node) {
            // Encoding belongs at the beginning of the actual document.
            if ($node instanceof \Dom\Element && $node->hasAttribute('charset')) continue;
            $result .= $doc->saveHtml($node);
        }
        return $result;
    }

    public static function layer(object $layer): object
    {
        $copy = clone $layer;
        $copy->html = self::withoutPageMetadata($layer->html ?? '');
        $copy->childLayers = array_map(self::layer(...), $layer->childLayers ?? []);
        return $copy;
    }
}
