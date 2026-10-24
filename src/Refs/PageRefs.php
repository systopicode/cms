<?php

declare(strict_types=1);

namespace Systopic\Cms\Refs;

use Systopic\Cms\Queries\Params;
use Systopic\Cms\Tables\Pages\Page;
use Systopic\Db\Scope;

/**
 * page:43 - a page by node id, in the request's language or ?lang=xx.
 *
 *   href="page:43"            its address (Page::$link), #anchor appended
 *   {page:43}                 a link: <a href="…">label</a>
 *   {page:43.label}           the label as text
 *   {page:43.meta.description}
 *
 * A page that is not there in that language - or not visible under the
 * parameters (unpublished, in the trash) - is not found.
 */
final class PageRefs implements RefType
{
    public function __construct(private Scope $scope, private Params $params) {}

    public function name(): string
    {
        return 'page';
    }

    public function key(Ref $ref): string
    {
        return $ref->id . '@' . $this->lang($ref);
    }

    public function load(array $refs): array
    {
        $byLang = [];
        foreach ($refs as $ref) {
            $byLang[$this->lang($ref)][] = $ref->id;
        }
        $found = [];
        foreach ($byLang as $lang => $ids) {
            $params = $lang === $this->params->lang ? $this->params : $this->params->in($lang);
            foreach (Page::byIds($this->scope, $params, $ids) as $id => $page) {
                if ($page->row !== null) {
                    $found["$id@$lang"] = $page;
                }
            }
        }
        return $found;
    }

    public function url(object $target, Ref $ref, string $attribute): ?string
    {
        /** @var Page $target */
        return $target->link . ($ref->fragment !== null ? '#' . $ref->fragment : '');
    }

    public function text(object $target, Ref $ref): ?string
    {
        /** @var Page $target */
        $escape = static fn(?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return match (implode('.', $ref->path)) {
            '' => '<a href="' . $escape($target->link) . '">' . $target->label->plain . '</a>',
            'label' => $target->label->plain,
            'link' => $escape($target->link),
            'href' => $escape($target->href),
            'language' => $escape($target->language),
            'meta.description' => $escape($target->meta->description),
            'meta.shareTitle' => $escape($target->meta->shareTitle),
            default => null,
        };
    }

    public function editorData(object $target): array
    {
        return [];
    }

    private function lang(Ref $ref): string
    {
        return $ref->param('lang', $this->params->lang);
    }
}
