<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Pages;

use Stringable;
use Systopic\Db\Scope;
use Systopic\Cms\Queries\Params;
use Systopic\Cms\Refs\Resolver;

/**
 * The view onto **one** text column — successor of `pageText` and `pageHtml`
 * as far as they read.
 *
 * The difference to the legacy code is already in the map: access there is
 * asymmetric. `$page->text01 = 'x'` writes a string, `$x = $page->text01`
 * returns an object, and `getExportValue()` catches that again with
 * `is_object($value) ? …->toString()`. Here the **column is a string** and
 * this is a view onto it: `$page->text01` stays a value,
 * `$page->texts->get('text01')` is the view.
 *
 * Two legacy classes become one. `pageText` and `pageHtml` differ in exactly
 * two points — what counts as visible and whether there is a tag — yet each
 * had its own class with its own `__toString`, its own `renderPlaceholders`
 * (one of them with a forgotten `p($match)` inside) and its own editor.
 *
 * Rendering is **not** here: `Pages\Fields\FieldOutput` turns the field into
 * its content on the site and into the editor in the CMS. This class says what
 * the value is and how it is to be wrapped.
 *
 * In a template:
 *
 *   <?= $page->text03->tag('h1')->class('auto-fit') ?>   content, or the editor in the CMS
 *   <?= $page->label->plain ?>                           escaped text, never an editor
 *   <?php if ($page->html01->isVisible) …                anything in it?
 *   $page->text01->value                                 the raw column, for logic
 *
 * `tag()`, `class()` and `editorLabel()` return a **copy**. The legacy object
 * changed itself, and an `->tag('h1')` in one place changed the field for
 * every later output of the same page.
 */
final class TextField implements Stringable
{
    public function __construct(
        private readonly Scope $scope,
        private readonly Params $params,
        private readonly Page $page,
        public readonly string $name,
        public readonly ?string $value,
        /** `html01`… may contain markup, `text01`… may not. */
        public readonly bool $isHtml = false,
    ) {}

    /**
     * Is there anything here?
     *
     * In markup an image counts as content even without a word beside it — the
     * same rule as in `pageHtml::$isVisible`.
     */
    public bool $isVisible {
        get {
            $raw = $this->value ?? '';
            if (trim(strip_tags($raw)) !== '') {
                return true;
            }
            return $this->isHtml && preg_match('~<img.*?>~i', $raw) === 1;
        }
    }

    /**
     * The tag a text field gets wrapped in.
     *
     * `text01` through `text06` are headings, from `text07` on paragraphs;
     * `label` and the `html` fields have none. The rule comes unchanged from
     * `pageText::__construct()`.
     */
    public ?string $tag {
        get {
            if ($this->tagOverride !== null) {
                return $this->tagOverride;
            }
            if ($this->isHtml || $this->name === 'label') {
                return null;
            }
            $index = (int) str_replace('text', '', $this->name);
            if ($index < 1) {
                return null;
            }
            return $index > 6 ? 'p' : 'h' . $index;
        }
    }

    /** The plain text, without markup. */
    public string $textContent {
        get => trim(html_entity_decode(strip_tags($this->value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * The content with its references resolved - `href="page:43"`,
     * `src="media:293?format=x"`, `{page:43.label}` (Cms\Refs\Resolver):
     * every target of the field in one load per type.
     */
    public string $rendered {
        get => (new Resolver($this->scope, $this->params))->render($this->value ?? '');
    }

    /**
     * The content as the CMS editor shows it: urls in place so the browser
     * shows the images, the references kept beside them for the save
     * (Resolver::normalize()).
     */
    public string $editorContent {
        get => (new Resolver($this->scope, $this->params))->renderForEditor($this->value ?? '');
    }

    /**
     * What ends up in the markup: on the site the value in its tag, in the
     * CMS the editor. The decision is FieldOutput's, not this class's — the
     * field stays a plain value object.
     */
    public function __toString(): string
    {
        return \Systopic\Cms\Pages\Fields\FieldOutput::text($this);
    }

    // ------------------------------------------------- how it is to be wrapped

    private ?string $tagOverride = null;

    /** CSS class on the wrapping tag — set by class(). */
    public private(set) ?string $cssClass = null;

    /** The label the CMS shows on the editor — set by editorLabel(). */
    public private(set) ?string $editorLabelText = null;

    /** A copy wrapped in another tag: 'h1', 'p' … — 'plain' outputs the raw value, 'input'/'textarea' pick the editor. */
    public function tag(string $tag): static
    {
        $copy = clone $this;
        $copy->tagOverride = $tag;
        return $copy;
    }

    /** A copy with a CSS class on the wrapping tag. */
    public function class(string $class): static
    {
        $copy = clone $this;
        $copy->cssClass = $class;
        return $copy;
    }

    /** A copy whose editor in the CMS carries this label. */
    public function editorLabel(string $label): static
    {
        $copy = clone $this;
        $copy->editorLabelText = $label;
        return $copy;
    }

    // --------------------------------------------------- never an editor

    /** The text without markup, escaped — for attributes, navigation, lists. */
    public string $plain {
        get => htmlspecialchars($this->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** The column as it is, unescaped — only where that is meant. */
    public string $raw {
        get => (string) ($this->value ?? '');
    }

    /** The page this field belongs to — FieldOutput needs its node and language. */
    public function page(): Page
    {
        return $this->page;
    }
}
