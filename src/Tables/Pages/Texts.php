<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Pages;

use RuntimeException;
use Systopic\Db\Type\Text;
use Systopic\Cms\Tables\Pages\Model as PageRow;
use Systopic\Db\Scope;
use Systopic\Cms\Queries\Params;

/**
 * The text fields of a page — `label`, `text01`…`text09`,
 * `html01`…`html09`.
 *
 * The collaborator holds no values: those are columns on `Page`. It makes sure
 * they are **there** — the lean level query only delivers `label`, everything
 * else `Scope::need()` fetches on access, for the whole group at once. A
 * catalogue with 50 entries showing `text01` therefore costs one extra
 * statement, not fifty.
 *
 * Which fields exist is not stated here but on `Page`. The list is read from
 * the class — a second list would be one that eventually drifts from the
 * first.
 */
final class Texts
{
    /** @var array<string, TextField> */
    private array $fields = [];

    /** @var list<string>|null */
    private static ?array $fieldNames = null;

    public function __construct(
        private readonly Scope $scope,
        private readonly Params $params,
        private readonly Page $page,
    ) {}

    /** @return list<string> every text column, as `Page` declares them */
    public static function names(): array
    {
        if (self::$fieldNames !== null) {
            return self::$fieldNames;
        }
        $names = [];
        foreach ((new \ReflectionClass(PageRow::class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if ($name === 'label' || preg_match('~^(text|html)\d\d$~', $name) === 1) {
                $names[] = $name;
            }
        }
        sort($names);
        return self::$fieldNames = $names;
    }

    public function has(string $name): bool
    {
        return in_array($name, self::names(), true);
    }

    /** @return list<string> */
    public array $names {
        get => self::names();
    }

    /**
     * The view onto one field.
     *
     * If the column was not in the query it gets fetched here — before anybody
     * reads it. Without that, access to a typed property fails with "must not
     * be accessed before initialization", which is correct but explains
     * nothing.
     */
    public function get(string $name): TextField
    {
        if (!$this->has($name)) {
            throw new RuntimeException("Page has no text field \$$name");
        }
        if (isset($this->fields[$name])) {
            return $this->fields[$name];
        }

        $page = $this->page->row;
        if ($page === null) {
            return $this->fields[$name] = new TextField(
                $this->scope, $this->params, $this->page, $name, null, str_starts_with($name, 'html'),
            );
        }

        $value = $this->scope->value($page, $name);
        if ($value instanceof Text) {
            $value = $value->value;
        }

        return $this->fields[$name] = new TextField(
            $this->scope,
            $this->params,
            $this->page,
            $name,
            $value === null ? null : (string) $value,
            str_starts_with($name, 'html'),
        );
    }

    /** Every field that holds something. @return array<string, TextField> */
    public array $filled {
        get {
            $filled = [];
            foreach (self::names() as $name) {
                $field = $this->get($name);
                if ($field->isVisible) {
                    $filled[$name] = $field;
                }
            }
            return $filled;
        }
    }

    /** The heading of the page — the one field every page has. */
    public TextField $label {
        get => $this->get('label');
    }
}
