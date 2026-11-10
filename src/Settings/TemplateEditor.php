<?php

declare(strict_types=1);

namespace Systopic\Cms\Settings;

use Systopic\Cms\Queries\SettingTree\Definition;
use Systopic\Cms\Queries\SettingTree\SettingTree;
use Systopic\Cms\Tables\Settings\Operator;
use Systopic\Db\Scope;

/** Human-facing field kinds; storage details belong here, not in the form. */
final class TemplateEditor
{
    public const KINDS = [
        'text' => ['Text field', 'Enter a text, such as a caption.', 'input', 'varchar'],
        'number' => ['Number field', 'Enter a whole number, such as a column count.', 'input', 'int'],
        'decimal' => ['Number field', 'Enter a whole number or a decimal, such as 2.5.', 'input', 'decimal'],
        'switch' => ['On / off switch', 'Enable or disable something, such as showing an image.', 'checkbox', 'int'],
        'choice' => ['Choice', 'Choose one option from a list.', 'select', 'varchar'],
        'group' => ['Field group', 'Keep related fields together.', 'group', 'varchar'],
    ];

    public static function kind(Definition $d): string
    {
        return match ($d->type) {
            'checkbox' => 'switch', 'select' => 'choice', 'group' => 'group',
            default => match ($d->datatype) { 'int' => 'number', 'decimal' => 'decimal', default => 'text' },
        };
    }

    /** Creates a complete, usable definition, including invisible storage children. */
    public static function create(Scope $scope, ?Definition $parent, string $kind, string $label, string $description = ''): Definition
    {
        $label = trim($label);
        if ($label === '') {
            throw new \InvalidArgumentException('Give this setting a name.');
        }
        if (!isset(self::KINDS[$kind])) {
            throw new \InvalidArgumentException('Choose what people should be able to enter.');
        }
        if ($parent !== null && $kind === 'group') {
            throw new \InvalidArgumentException('Groups must stay at the top level.');
        }
        if ($parent !== null && !in_array($parent->type, ['group', 'select'], true)) {
            throw new \InvalidArgumentException('This setting cannot contain more fields.');
        }
        return $scope->connection->transactional(static function () use ($scope, $parent, $kind, $label, $description): Definition {
            $siblings = $parent?->options ?? SettingTree::load($scope);
            $row = Operator::create($parent, $siblings);
            $row->label = $label;
            Operator::assign($row, ['description' => trim($description)]);
            $row->name = self::key($label, $siblings);
            $row->type = $parent?->type === 'select' ? 'option' : self::KINDS[$kind][2];
            $row->datatype = self::KINDS[$kind][3];
            $scope->save($row);
            $definition = SettingTree::byNodeId($scope, (int) $row->node->id);
            if ($parent?->type !== 'select' && in_array($row->type, ['input', 'checkbox'], true)) {
                $value = Operator::create($definition, []);
                $value->type = 'input';
                $value->name = $row->type === 'checkbox' ? 'true' : 'value';
                $value->label = $label;
                $value->datatype = $row->datatype;
                $scope->save($value);
            }
            return SettingTree::byNodeId($scope, (int) $row->node->id);
        });
    }

    /** Move a complete field/group, preserving IDs, options and saved page values. */
    public static function place(Scope $scope, Definition $item, ?Definition $parent, ?Definition $before = null): void
    {
        if ($parent && $item->type === 'group') throw new \InvalidArgumentException('Groups must stay at the top level.');
        if ($parent && $parent->type !== 'group') throw new \InvalidArgumentException('Drop fields into a group.');
        if ($item->parent && $item->parent->type !== 'group') throw new \InvalidArgumentException('Options cannot be moved out of their field.');
        for ($p = $parent; $p; $p = $p->parent) {
            if ($p->nodeId === $item->nodeId) throw new \InvalidArgumentException('A group cannot contain itself.');
        }
        $siblings = $parent?->options ?? SettingTree::load($scope);
        foreach ($siblings as $sibling) {
            if ($sibling->nodeId !== $item->nodeId && $sibling->name === $item->name) throw new \InvalidArgumentException('A field with this template key already exists in that group.');
        }
        $scope->connection->transactional(static function () use ($scope, $item, $parent, $before, $siblings): void {
            $ordered = array_values(array_filter($siblings, static fn($d) => $d->nodeId !== $item->nodeId));
            $at = count($ordered);
            foreach ($ordered as $index => $d) if ($before?->nodeId === $d->nodeId) $at = $index;
            array_splice($ordered, $at, 0, [$item]);
            $item->node->parentNode = $parent?->node;
            foreach ($ordered as $index => $d) { $d->node->ordering = ($index + 1) * 2; $scope->save($d->node); }
        });
    }

    /** Keys are stable once created. Labels can change without breaking templates. */
    private static function key(string $label, array $siblings): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        $key = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $ascii ?: $label), '_'));
        if ($key === '' || ctype_digit($key[0])) {
            $key = 'setting_' . $key;
        }
        $used = array_map(static fn(Definition $d) => $d->name, $siblings);
        $candidate = $key;
        for ($n = 2; in_array($candidate, $used, true); ++$n) {
            $candidate = $key . '_' . $n;
        }
        return $candidate;
    }
}
