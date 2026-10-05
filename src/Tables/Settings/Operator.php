<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Settings;

use Systopic\Db\Type\Decimal;
use Systopic\Db\Type\Text;
use Systopic\Cms\Queries\SettingTree\Definition;
use Systopic\System\Tables\Junctions\Model as NodeJunction;
use Systopic\System\Tables\Nodes\Model as Node;

/**
 * What can be done to setting declarations — nothing here saves.
 *
 * A declaration is a setting row on a node of type `setting`; its default is
 * a junction hanging off node 1, the template row that belongs to no page.
 */
final class Operator
{
    /** The declaration columns the settings panel edits. */
    public const EDITABLE = ['name', 'label', 'description', 'type', 'datatype'];

    /** The value columns of a default. */
    public const DEFAULT_COLUMNS = ['varchar', 'int', 'decimal'];

    /**
     * A new declaration under `$parent` (NULL: the topmost level, whose parent
     * node is NULL, not 0), at the end of its siblings, of its parent's type.
     *
     * Saving the setting takes the new node along and inserts it first.
     *
     * @param list<Definition> $siblings
     */
    public static function create(?Definition $parent, array $siblings): Model
    {
        $node = new Node();
        $node->parentNode = $parent?->node;
        $node->type = 'setting';
        $node->ordering = array_reduce($siblings, static fn(int $max, Definition $d): int => max($max, (int) $d->node->ordering), 0) + 2;

        $setting = new Model();
        $setting->node = $node;
        $setting->type = $parent?->type;
        return $setting;
    }

    /**
     * Form values onto the declaration — only `EDITABLE` columns.
     *
     * @param array<string, mixed> $values
     */
    public static function assign(Model $setting, array $values): Model
    {
        foreach ($values as $field => $value) {
            match ($field) {
                'name'        => $setting->name = (string) $value,
                'label'       => $setting->label = (string) $value,
                'type'        => $setting->type = (string) $value,
                'datatype'    => $setting->datatype = (string) $value,
                'description' => $setting->description = new Text((string) $value),
                default       => throw new \InvalidArgumentException("setting field $field is not editable"),
            };
        }
        return $setting;
    }

    /**
     * Form values onto the default of a declaration. A declaration nobody has
     * given a default yet has no junction: it comes into being here. An
     * emptied field is NULL — 0 is a value somebody meant.
     *
     * @param array<string, mixed> $values
     */
    public static function assignDefault(Definition $definition, array $values): NodeJunction
    {
        $junction = $definition->defaultJunction;
        if ($junction === null) {
            $junction = new NodeJunction();
            $junction->node = Node::withId(1);
            $junction->settingNode = $definition->node;
        }
        foreach ($values as $field => $value) {
            $empty = $value === null || $value === '';
            match ($field) {
                'varchar' => $junction->varchar = $empty ? null : (string) $value,
                'int'     => $junction->int = $empty ? null : (int) $value,
                'decimal' => $junction->decimal = $empty ? null : Decimal::from($value),
                default   => throw new \InvalidArgumentException("default column $field is unknown"),
            };
        }
        return $junction;
    }
}
