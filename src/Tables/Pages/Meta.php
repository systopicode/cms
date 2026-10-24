<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Pages;

use DateTimeImmutable;
use Systopic\Db\Type\Text;
use Systopic\Db\Scope;
use Systopic\Cms\Queries\Params;

/**
 * What holds true about a page without being its content: template,
 * visibility, the details for search engines and for sharing.
 *
 * The one answer this is really about is `published`. In the legacy code it is
 * a four-line expression inside `page::__get()`, and the same condition sits
 * again in `pages.sql`, again in `info.sql` and again in every derived query —
 * four versions of one rule. Here it is one, and the query files check the
 * same thing.
 *
 * What the lean level query does not deliver, `Scope::need()` fetches:
 * `published` needs `startPublishing` and `endPublishing`, and those arrive
 * for the **whole group** in one statement each, not per page in two.
 *
 * **Not here: `settings` and the rendered metatag markup.** `pageSettings` is
 * a data model of its own — a tree out of `cms_settings` and `cms_junctions`
 * with inheritance along `node_parent_node_id` — and
 * `pageMetatags::render()` depends on `printPublicPageTitle()`, on
 * `media::get()` and on four constants. Neither belongs in a collaborator of
 * `Page` but in a model of its own, respectively in the panel. That is not an
 * omission but the same cut as everywhere: **no verbs on shared nouns.**
 */
final class Meta
{
    public function __construct(
        private readonly Scope $scope,
        private readonly Params $params,
        private readonly Page $page,
    ) {}

    // -------------------------------------------------------------- template

    /** The name of the template, exactly as it stands in the column. */
    public ?string $templateName {
        get => $this->page->row?->templateName;
    }

    /**
     * The identity a render pass recognises this page by.
     *
     * `$page->renderId` in the legacy code. The view (`viewName`) is part of
     * it there; that belongs to the renderer and is therefore passed in.
     */
    public function renderId(string $view = 'main'): string
    {
        return 'id=' . $this->page->node->id
            . '&lang=' . $this->params->lang
            . '&tpl=' . ($this->templateName ?? '')
            . '&view=' . $view;
    }

    // ------------------------------------------------------------ visibility

    /**
     * Is the page visible right now?
     *
     * Word for word the condition from `page::__get('published')`: not in the
     * trash, published, and inside the time window — where `preview` lifts
     * each of the four.
     */
    public bool $published {
        get {
            if ($this->params->preview) {
                return true;
            }
            $page = $this->page->row;
            if ($page === null) {
                return false;
            }
            if ($this->page->node->isTrashed) {
                return false;
            }
            if (!$this->scope->value($page, 'isPublished')) {
                return false;
            }
            $now  = new DateTimeImmutable();
            $from = $this->scope->value($page, 'startPublishing');
            $to   = $this->scope->value($page, 'endPublishing');

            if ($from instanceof DateTimeImmutable && $from > $now) {
                return false;
            }
            if ($to instanceof DateTimeImmutable && $to < $now) {
                return false;
            }
            return true;
        }
    }

    /** Does the page belong into a navigation? */
    public bool $isNaviElement {
        get {
            $page = $this->page->row;
            return $page !== null && (bool) $this->scope->value($page, 'isNaviElement');
        }
    }

    /**
     * Why sitemap.xml and llms.txt leave this page out. Empty means it is listed.
     *
     * Five checks, nothing else: publishing start, publishing end, navi element,
     * the live page count of the address, and the robots setting.
     *
     * @return list<string>
     */
    public array $listBlockers {
        get {
            $page = $this->page;
            $row = $page->row;
            if ($row === null) {
                return ['No page record'];
            }
            $reasons = [];
            $now = new DateTimeImmutable();
            $from = $this->scope->value($row, 'startPublishing');
            $to = $this->scope->value($row, 'endPublishing');
            if ($from instanceof DateTimeImmutable && $from > $now) {
                $reasons[] = 'Publishing start is still ahead';
            }
            if ($to instanceof DateTimeImmutable && $to < $now) {
                $reasons[] = 'Publishing end has passed';
            }
            if (!$this->isNaviElement) {
                $reasons[] = 'Navi element is off';
            }
            $path = $page->rewritepaths->primary;
            // The live counter only moves on LIVE. On every other stage the
            // visits of this installation are the test counter, otherwise the
            // sitemap of a development site would stay empty.
            $onLive = defined('STAGE') && STAGE === 'LIVE';
            $count = $path === null ? 0 : ($onLive ? $path->livecount : $path->testcount);
            if ($count === 0) {
                $reasons[] = $onLive ? 'Live page count is 0' : 'Page count is 0';
            }
            $robots = trim((string) $this->robots);
            if (str_contains($robots, 'noindex')) {
                $reasons[] = 'Robots: ' . $robots;
            }
            return $reasons;
        }
    }

    /**
     * Should a search engine or an AI list this page?
     *
     * Publishing start, publishing end, navi element, live page count and
     * robots. sitemap.xml and llms.txt both ask this, on every request.
     */
    public bool $listed {
        get => $this->listBlockers === [];
    }

    // ------------------------------------------------- search engines, sharing

    public ?string $description {
        get => $this->text('metaDescription');
    }

    public ?string $keywords {
        get => $this->text('metaKeywords');
    }

    public ?string $robots {
        get => $this->text('metaRobots');
    }

    /** Structured data for search engines — the content of a <script type="application/ld+json">. */
    public ?string $jsonLd {
        get => $this->text('metaJsonLd');
    }

    public ?string $shareTitle {
        get => $this->text('metaShareTitle');
    }

    /**
     * The id of the image shown when sharing.
     *
     * An id and not a `Media` object: `cms_medias` has no row class yet. The
     * column is therefore a `?int` and not a relation — more honest than an
     * object that does not exist.
     */
    public ?int $shareImageId {
        get {
            $page = $this->page->row;
            $value = $page === null ? null : $this->scope->value($page, 'metaShareImage');
            return $value === null ? null : (int) $value;
        }
    }

    /** Everything a metatag renderer needs, as plain values. */
    public array $share {
        get => [
            'title'       => $this->shareTitle ?: (string) $this->page->texts->label->value,
            'description' => $this->description,
            'keywords'    => $this->keywords,
            'robots'      => $this->robots,
            'imageId'     => $this->shareImageId,
        ];
    }

    private function text(string $property): ?string
    {
        $page = $this->page->row;
        if ($page === null) {
            return null;
        }
        $value = $this->scope->value($page, $property);
        if ($value instanceof Text) {
            $value = $value->value;
        }
        return $value === null ? null : (string) $value;
    }
}
