<?php

declare(strict_types=1);

namespace Systopic\Cms\Refs;

use Systopic\Cms\Queries\PageContents\PageContents;
use Systopic\Cms\Queries\RewritepathLookup\RewritepathLookup;
use Systopic\Cms\Tables\Pages\Model as PageRow;
use Systopic\Cms\Tables\Pages\Operator as PageOperator;
use Systopic\Db\Scope;
use Systopic\System\Queries\MediaNodes\MediaNodes;
use Systopic\System\Tables\Medias\Model as MediaRow;

/**
 * Content written before references (Cms\Refs) turned into references - a
 * one-off per installation, run from tools/migration. Nothing in the
 * rendering knows the old forms; whatever this does not convert stays as
 * it is and shows up as "open" in the report.
 *
 *   <img data-node_id="293" data-format="collection" src="/x/var/…">  →  <img src="media:293?format=collection">
 *   <img src="https://smp.de/var/formats/collection/001/53/x.jpg">     →  the medium of folder 001/53 (id 153), if the file name is its
 *   <video data-node_id="85" …>, <iframe data-node_id="85" …>          →  src="media:85" (and poster)
 *   href="###http::siteRoot###?id=42&amp;lang=en", href="?id=42"       →  href="page:42?lang=en"
 *   href="/smp.de/Kontakt/", href="https://smp.de/Kontakt/"            →  href="page:9" (rewritepath lookup)
 *   href="/smp.de/var/original/000/85/x.pdf"                          →  href="media:…"
 *   ##BEGINMAILTOHREF##a@b.de##ENDMAILTOHREF##                        →  mailto:a@b.de
 *   ###_IMAGE_DROPPABLE_CLASSNAMES_### and the other class markers    →  removed
 *
 * A link's language is kept only when it is not the language of the page
 * the link is on - there the request language is what it means anyway.
 */
final class HtmlMigration
{
    /** the page columns that may hold markup */
    public const FIELDS = [
        'label',
        'text01', 'text02', 'text03', 'text04', 'text05', 'text06', 'text07', 'text08', 'text09',
        'html01', 'html02', 'html03', 'html04', 'html05', 'html06', 'html07', 'html08', 'html09',
    ];

    /** a file of the media folders: var/formats/<format>/ddd/dd/<file> or var/original/ddd/dd/<file> */
    private const MEDIA_PATH = '~(?:^|/)var/(?:formats/([\w-]+)|original)/(\d{3})/(\d{2})/([^/]+)$~';

    private const MEDIA_TAGS = ['img', 'video', 'audio', 'iframe', 'source', 'embed'];

    private const TAG = '~<([a-zA-Z][a-zA-Z0-9]*)(\s[^<>]*)?>~';

    /** @var array<int, MediaRow> media id => row, for the paths in the content */
    private array $media = [];

    /** @param list<string> $ownHosts the host names this site answers or answered to */
    public function __construct(private Scope $scope, private array $ownHosts) {}

    /**
     * Every page row, every field: converted, and with $write saved.
     *
     * @return array{rows: int, changed: int, entries: list<array{node: int, lang: string, field: string, done: list<string>, open: list<string>}>}
     */
    public function run(bool $write): array
    {
        $rows = PageContents::all($this->scope);
        $this->loadMedia($rows);
        $entries = [];
        $changed = [];
        foreach ($rows as $row) {
            $lang = (string) $row->languageIso;
            $rowChanged = false;
            foreach (self::FIELDS as $field) {
                $property = (string) PageOperator::propertyOf($field);
                $value = (string) ($row->$property ?? '');
                if ($value === '') {
                    continue;
                }
                $done = [];
                $open = [];
                $converted = $this->convert($value, $lang, $done, $open);
                if ($done === [] && $open === []) {
                    continue;
                }
                $entries[] = ['node' => (int) $row->relationId('node'), 'lang' => $lang, 'field' => $field, 'done' => $done, 'open' => $open];
                if ($converted !== $value) {
                    $rowChanged = true;
                    if ($write) {
                        PageOperator::assign($row, $field, $converted);
                    }
                }
            }
            if ($rowChanged) {
                $changed[] = $row;
            }
        }
        if ($write && $changed !== []) {
            $this->scope->save(...$changed);
        }
        return ['rows' => count($rows), 'changed' => count($changed), 'entries' => $entries];
    }

    /**
     * One text, converted. $done and $open collect what happened, for the report.
     *
     * @param list<string> $done
     * @param list<string> $open
     */
    public function convert(string $html, string $lang, array &$done, array &$open): string
    {
        $before = $html;
        $html = (string) preg_replace_callback('~##BEGINMAILTOHREF##(.*?)##ENDMAILTOHREF##~', static fn(array $m): string => 'mailto:' . $m[1], $html);
        $html = str_replace(['###_IMAGE_DROPPABLE_CLASSNAMES_###', '###_ANCHOR_DROPPABLE_CLASSNAMES_###', '###_AUTO_EMAIL_ANCHOR_CLASSNAMES_###'], '', $html);
        $html = str_replace(['###http::varRoot###', '###http::siteRoot###'], ['/var/', '/'], $html);
        if ($html !== $before) {
            $done[] = 'legacy markers';
        }
        return (string) preg_replace_callback(self::TAG, function (array $m) use ($lang, &$done, &$open): string {
            $tag = strtolower($m[1]);
            $attrs = $m[2] ?? '';
            if (in_array($tag, self::MEDIA_TAGS, true)) {
                $attrs = $this->mediaTag($tag, $attrs, $done, $open);
            }
            if (Attributes::get($attrs, 'href') !== null) {
                $attrs = $this->link($attrs, $lang, $done, $open);
            }
            return '<' . $m[1] . $attrs . '>';
        }, $html);
    }

    // ------------------------------------------------------------- media

    private function mediaTag(string $tag, string $attrs, array &$done, array &$open): string
    {
        $src = Attributes::get($attrs, 'src');
        if ($src !== null && Ref::fromAttribute($src) !== null) {
            return $attrs; // a reference already
        }
        $nodeId = Attributes::get($attrs, 'data-node_id');
        if ($nodeId !== null && ctype_digit($nodeId)) {
            $format = Attributes::get($attrs, 'data-format');
        } elseif ($src !== null && ($found = $this->ownMedia($src)) !== null) {
            [$nodeId, $format] = $found;
        } else {
            if ($src !== null && $this->looksOwn($src)) {
                $open[] = "$tag src=$src";
            }
            return $attrs;
        }
        $ref = "media:$nodeId" . ($tag === 'img' && $format !== null && $format !== '' && $format !== 'original' ? "?format=$format" : '');
        $attrs = Attributes::set($attrs, 'src', $ref);
        if ($tag === 'video' && Attributes::get($attrs, 'poster') !== null) {
            $attrs = Attributes::set($attrs, 'poster', "media:$nodeId");
        }
        foreach (['data-node_id', 'data-id', 'data-format', 'data-mimetype'] as $old) {
            $attrs = Attributes::remove($attrs, $old);
        }
        $done[] = "$tag $ref";
        return $attrs;
    }

    /**
     * [node id, format|null] of a url that is a file of this site's media -
     * its folders name the medium, its file name must be the medium's.
     */
    private function ownMedia(string $url): ?array
    {
        $path = rawurldecode((string) parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH));
        if (!preg_match(self::MEDIA_PATH, $path, $m)) {
            return null;
        }
        $media = $this->media[(int) ($m[2] . $m[3])] ?? null;
        $nodeId = $media?->relationId('node');
        if ($media === null || $nodeId === null) {
            return null;
        }
        $filename = (string) $media->filename;
        if ($m[4] !== $filename && !str_starts_with($m[4], $filename . '.')) {
            return null; // a format file is name + .webp/.jpg
        }
        return [$nodeId, $m[1] !== '' ? $m[1] : null];
    }

    /** the media rows of every media path in the content, one statement */
    private function loadMedia(array $rows): void
    {
        $ids = [];
        foreach ($rows as $row) {
            foreach (self::FIELDS as $field) {
                $value = (string) ($row->{(string) PageOperator::propertyOf($field)} ?? '');
                if (preg_match_all('~var/(?:formats/[\w-]+|original)/(\d{3})/(\d{2})/~', $value, $m)) {
                    foreach (array_keys($m[0]) as $i) {
                        $ids[(int) ($m[1][$i] . $m[2][$i])] = true;
                    }
                }
            }
        }
        $this->media = MediaNodes::byMediaIds($this->scope, array_keys($ids));
    }

    // ------------------------------------------------------------- links

    private function link(string $attrs, string $lang, array &$done, array &$open): string
    {
        $href = html_entity_decode((string) Attributes::get($attrs, 'href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($href === '' || Ref::fromAttribute($href) !== null || preg_match('~^(#|mailto:|tel:|javascript:|data:)~i', $href)) {
            return $attrs;
        }
        if (!$this->looksOwn($href)) {
            return $attrs;
        }
        if (preg_match('~[?&]id=(\d+)(?:&lang=([a-z]{2}))?~', $href, $m)) {
            $ref = 'page:' . $m[1] . (isset($m[2]) && $m[2] !== $lang ? '?lang=' . $m[2] : '');
        } elseif (($media = $this->ownMedia($href)) !== null) {
            $ref = 'media:' . $media[0];
        } elseif (($page = $this->pageOfPath($href)) !== null) {
            [$nodeId, $pageLang] = $page;
            $fragment = (string) parse_url($href, PHP_URL_FRAGMENT);
            $ref = "page:$nodeId" . ($pageLang !== $lang ? "?lang=$pageLang" : '')
                . (preg_match('~^[\w-]+$~', $fragment) ? "#$fragment" : '');
        } else {
            $open[] = "a href=$href";
            return $attrs;
        }
        $done[] = "a $ref";
        return Attributes::set($attrs, 'href', $ref);
    }

    /**
     * [node id, language] of the page a path of this site addresses. The
     * path may carry an old root in front (/smp.de/, /projects/x/public/):
     * up to three leading segments are tried away.
     */
    private function pageOfPath(string $url): ?array
    {
        $path = trim(rawurldecode((string) parse_url($url, PHP_URL_PATH)), '/');
        $root = trim((string) \http::$root, '/');
        if ($root !== '' && str_starts_with($path . '/', $root . '/')) {
            $path = trim(substr($path, strlen($root)), '/');
        }
        $segments = $path === '' ? [] : explode('/', $path);
        for ($drop = 0; $drop <= min(3, count($segments)); $drop++) {
            $candidate = implode('/', array_slice($segments, $drop));
            $found = RewritepathLookup::resolve($this->scope, $candidate, RewritepathLookup::ANY_SITE);
            $nodeId = $found?->page?->relationId('node');
            if ($nodeId !== null) {
                return [$nodeId, (string) $found->page->languageIso];
            }
        }
        return null;
    }

    /** a relative url, or one to a host of this site */
    private function looksOwn(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return !preg_match('~^[a-z][a-z0-9+.-]*:~i', $url); // no scheme: relative
        }
        return in_array(strtolower(preg_replace('~^www\.~i', '', $host)), $this->ownHosts, true);
    }
}
