<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Pages;

use DateTimeImmutable;
use Systopic\Db\Schema\Col;
use Systopic\Db\Type\Text;
use Systopic\Db\TableRow;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Users\Model as User;

/**
 * A page in one language — `cms_pages`.
 *
 * **Columns only.** What sits in `page.php` today — 463 lines, 84 of them in
 * `__get()` with 24 cases — is seven responsibilities in one class. They move
 * into collaborators of their own in phase 5 (`Pageamily`,
 * `pagerewritepaths`, `pagetexts`, `pagemeta`), and `page` becomes their
 * surface.
 *
 * until then this class is exactly what its name says: a row.
 *
 * two things differ from the legacy code:
 *
 * `$label` is a `string`, not a `pagetext` object. access is asymmetric today
 * — `$page->text01 = 'x'` writes a string, `$x = $page->text01` returns an
 * object, and `getexportvalue()` catches that again with
 * `is_object($value) ? …->tostring()`. with typed columns that no longer
 * works: the column is a string, the editor a view onto it.
 *
 * `$language` is called `page_language_iso` in the database. the mapping
 * therefore does not live in the name but in the declaration — see below.
 */
final class Model extends TableRow
{
    public const TABLE  = 'pages';
    public const PREFIX = 'page';

    public ?Node $node {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    /** ISO code of the language, '*' for language-neutral. */
    public ?string $languageIso = '*' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?string $label = '' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    /**
     * The plain text of the rendered page, for searching over.
     *
     * Empty in all 136 rows today — whatever once filled it from the renderer
     * is gone. The column and its width are declared here anyway, because the
     * class describes the table as it is, not as it ought to be. Where this
     * belongs in the long run is its own table: it is derived data with a
     * different lifecycle than the row, invalid the moment a page changes.
     */
    #[Col(length: 4096)]
    public ?string $fulltextsearch {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** 160 characters is the search-result snippet, not an arbitrary bound. */
    #[Col(length: 160)]
    public ?string $metaDescription {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?Text $metaKeywords {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    /**
     * A media id, and an `int` rather than a `?Media`.
     *
     * The column carries no foreign key and the picture may be gone; a
     * relation would promise a row that is not always there.
     */
    #[Col(unsigned: true)]
    public ?int    $metaShareImage {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    /** og:title — 100 characters is what the platforms show. */
    #[Col(length: 100)]
    public ?string $metaShareTitle {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public string  $metaRobots = 'index, follow' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?Text $metaJsonLd {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?string $templateName = '' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?bool $isPublished   = false { set => $this->__set__(__PROPERTY__, $value); }
    public ?bool $isNaviElement = true {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?DateTimeImmutable $startPublishing {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?DateTimeImmutable $endPublishing {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?User $createdUser {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?User $modifiedUser {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?DateTimeImmutable $createdDate {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?DateTimeImmutable $modifiedDate {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?DateTimeImmutable $dateRecommended {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?DateTimeImmutable $datePublished {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * The nine single-line fields a template labels for itself — headline,
     * date, caption. The body of a page is `html01..09` and is `Text`; these
     * are not long text and are deliberately not.
     *
     * 1023 is not arbitrary and not ours: 1023 × 3 bytes (utf8mb3) = 3069, just
     * under InnoDB's 3072-byte index key limit. Somebody chose the widest
     * column that could still carry a full index — a calculation that collapses
     * under utf8mb4, where the same 1023 characters are 4092 bytes.
     *
     * Declared because `string` would otherwise claim 255 and this connection
     * runs without STRICT_TRANS_TABLES: MySQL would truncate an over-long
     * headline silently. Now `Constraint` rejects it at the assignment, where
     * it can still be reported.
     */
    #[Col(length: 1023)]
    public ?string $text01 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 1023)]
    public ?string $text02 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 1023)]
    public ?string $text03 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 1023)]
    public ?string $text04 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 1023)]
    public ?string $text05 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 1023)]
    public ?string $text06 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 1023)]
    public ?string $text07 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 1023)]
    public ?string $text08 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    #[Col(length: 1023)]
    public ?string $text09 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Text $html01 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?Text $html02 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?Text $html03 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?Text $html04 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?Text $html05 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?Text $html06 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?Text $html07 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?Text $html08 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?Text $html09 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    // ---- virtual: computed, not a column -----------------------------------

    /** Language-neutral, so it applies to every language. */
    public bool $isNeutral { get => $this->languageIso === '*'; }
}
