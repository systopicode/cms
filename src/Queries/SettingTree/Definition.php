<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\SettingTree;

use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Junctions\Model as NodeJunction;
use Systopic\Cms\Tables\Settings\Model as Setting;

/**
 * One declared setting, with its options — the successor of `setting`.
 *
 * Three rows meet here and none of them is this class: `Setting` says what the
 * setting is, `Node` says where it sits in the tree, and a `NodeJunction` on
 * the template row carries its default. The rows hold values; this holds the
 * question a panel actually asks — "what is this setting, and what may it be?"
 *
 * **A setting is two levels, not one.** That is the part of the legacy model
 * worth keeping and easy to miss:
 *
 *     node 79  blueColumn         type select     <- the setting
 *       node 81  center  "mitte"                  <- an option
 *       node 82  right   "rechts"
 *       node 243 all     "alle"
 *       node 314 left    "links"
 *
 *     node 83  complete_line_blue type checkbox   <- the setting
 *       node 84  true                             <- its one option
 *
 * A checkbox has exactly one child and stores 0 or 1 in `junction_int` on it.
 * A select has one child per option and stores **nothing**: which option was
 * chosen is said by which child the junction points at. That is why a value
 * and a choice are two different questions here, and why `SettingValues` answers both
 * (`valueOf()` and `chosenOf()`).
 */
final class Definition
{
    /** @var list<Definition> the options, in tree order */
    public array $options = [];

    public ?Definition $parent = null;

    public function __construct(
        public readonly Setting $setting,
        public readonly Node $node,
        /**
         * The junction on the template row (node 1), when there is one.
         *
         * Not readonly, and it is the only one here that is not: a setting
         * whose default nobody has filled in has no such row, and the moment
         * somebody types one it comes into being. Writing it back is what
         * keeps this object the same object afterwards — the alternative is
         * to throw the tree away and read it again, which is the thing this
         * model exists to stop doing.
         */
        public ?NodeJunction $defaultJunction = null,
    ) {}

    public int $nodeId {
        get => (int) $this->node->id;
    }

    /** The name a template writes: `$settings->blueColumn`. */
    public ?string $name {
        get => $this->setting->name;
    }

    /** What a human reads. Falls back to the name, which beats an empty label. */
    public string $label {
        get => ($this->setting->label ?? '') !== '' ? (string) $this->setting->label : (string) $this->setting->name;
    }

    /** `checkbox`, `select`, `group` — what the panel renders. */
    public ?string $type {
        get => $this->setting->type;
    }

    /** `varchar`, `int` or `decimal` — which of the three value columns is meant. */
    public string $datatype {
        get => ($this->setting->datatype ?? '') !== '' ? (string) $this->setting->datatype : 'varchar';
    }

    public bool $isCheckbox {
        get => $this->type === 'checkbox';
    }

    public bool $isSelect {
        get => $this->type === 'select';
    }

    /**
     * The value that applies when a page has set nothing.
     *
     * Read out of the junction on the template row, and out of **the column
     * the datatype names** — the junction has three and the setting decides
     * which one it means.
     */
    public mixed $default {
        get => self::valueIn($this->defaultJunction, $this->datatype);
    }

    /** The option with this name, or NULL. */
    public function option(string $name): ?self
    {
        foreach ($this->options as $option) {
            if ($option->name === $name) {
                return $option;
            }
        }
        return null;
    }

    public function optionByNodeId(int $nodeId): ?self
    {
        foreach ($this->options as $option) {
            if ($option->nodeId === $nodeId) {
                return $option;
            }
        }
        return null;
    }

    /**
     * Read one of the three value columns of a junction.
     *
     * Here rather than on `NodeJunction`, and that is the cut this model is
     * built on: the junction row genuinely does not know what its value means.
     * Which column counts is said by the **declaration** on the other side, so
     * the question belongs to the declaration.
     *
     * @internal also used by SettingValues
     */
    public static function valueIn(?NodeJunction $junction, string $datatype): mixed
    {
        if ($junction === null) {
            return null;
        }
        return match ($datatype) {
            'int'     => $junction->int,
            'decimal' => $junction->decimal,
            default   => $junction->varchar,
        };
    }
}
