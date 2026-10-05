<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries;

use Systopic\Db\Result\Completion;

/**
 * What the page queries are read under — a value, and the cache key.
 *
 * The legacy code holds the same thing in `page::getQueryParams()` — language,
 * preview, trash — except that it comes from **globals** there:
 *
 *     global $showeditableareas, $preview, $showtrashed;
 *
 * Every query therefore hangs off three variables set somewhere in the
 * request, and two views side by side (front end and CMS preview) do not
 * exist. Here it is a value that gets passed along, next to the request's
 * `Db\Scope`.
 *
 * Plus the three things that make up an address — `root`, `siteUrl`, `inCms`.
 * The legacy code reads them from `http::$root`, `http::$siteUrl` and
 * `PanelTree::getInstance()?->inCms()`; passing them in is the difference
 * between a model that can be checked without a request and one that needs
 * half an application.
 *
 * `Completion` sits here because it is a property of the **query**: the same
 * page completes in a group inside an anchor list and one by one inside a
 * detail panel. It applies twice over — to missing columns (LoadGroup) and to
 * the missing level (PageTreeLevel).
 */
final readonly class Params
{
    public function __construct(
        public string $lang,
        public bool $preview = false,
        public bool $showTrashed = false,
        public Completion $completion = Completion::Group,
        /** Application root, e.g. `/projects/x/public/`. */
        public string $root = '/',
        /** The same root, absolute: `http://host/projects/x/public/`. */
        public string $siteUrl = '/',
        /** Inside the CMS things are edited, on the site they are rendered. */
        public bool $inCms = false,
        /**
         * Only nodes of this kind — 'page' for the page tree, NULL for all.
         *
         * `pages.sql` does not filter on it, `tree.sql` does. Whoever draws
         * the pylon tree wants no media nodes in between; whoever compares
         * against the legacy code wants exactly the same set as `pages.sql`.
         */
        public ?string $nodeType = null,
    ) {}

    /** The same parameters in another language. */
    public function in(string $lang): self
    {
        return new self($lang, $this->preview, $this->showTrashed, $this->completion,
            $this->root, $this->siteUrl, $this->inCms, $this->nodeType);
    }

    /**
     * The placeholders every page query file expects.
     *
     * @return array<string, mixed>
     */
    public function params(array $extra = []): array
    {
        return $extra + [
            'lang'        => $this->lang,
            'preview'     => $this->preview,
            'showtrashed' => $this->showTrashed,
            'nodeType'    => $this->nodeType,
        ];
    }

    /** What tells one set of parameters from another — for objects keyed by it. */
    public function key(): string
    {
        return $this->lang . '|' . (int) $this->preview . '|' . (int) $this->showTrashed
            . '|' . ($this->nodeType ?? '*') . '|' . $this->completion->name;
    }
}
