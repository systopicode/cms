<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\SettingTree;

use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Nodes\Operator as NodeOperator;
use Systopic\System\Tables\Junctions\Model as NodeJunction;
use Systopic\Cms\Tables\Settings\Model as Setting;

/**
 * Every declared setting, as a tree — in one query.
 *
 * The successor of `setting::get()` / `setting::getChildren()`. There the tree
 * is one query per level: the root, then its children, then one more per
 * child. Here the whole tree arrives at once and the assembly runs over
 * `node_parent_node_id`, which every child row carries anyway — the same move
 * that took `tagGroups.sql` apart.
 *
 * Measured against `smp.de`: **1 statement** for 8 declarations on two levels,
 * against 1 + 1 + n in the legacy path.
 *
 * The tree lives in the scope, once per request: the query result is in
 * `Scope::rows()`, the assembled tree in `Scope::objectFor()`. A write to
 * `cms_nodes`, `cms_settings` or `cms_junctions` throws both away
 * (`Scope::committed()`), and the next `load()` reads afresh. That replaced
 * `attach()`, `detach()` and `forget()`: nobody has to hang a new row into
 * the tree by hand any more, and nobody can forget to.
 *
 * Why this is a query and not a method on `Setting`: loading is a verb and
 * `Setting` is a row.
 */
final class SettingTree
{
    /**
     * @param list<Definition> $roots the topmost settings, options attached
     * @param array<int, Definition> $byNodeId node id => definition, roots and options
     */
    private function __construct(
        public readonly array $roots,
        public readonly array $byNodeId,
    ) {}

    /**
     * Every setting, roots first, options hanging off them.
     *
     * @return list<Definition>
     */
    public static function load(Scope $scope): array
    {
        return self::tree($scope)->roots;
    }

    /** A root setting by the name a template uses. */
    public static function byName(Scope $scope, string $name): ?Definition
    {
        foreach (self::load($scope) as $definition) {
            if ($definition->name === $name) {
                return $definition;
            }
        }
        return null;
    }

    /**
     * Any definition by its node id — a root or an option.
     *
     * The direction the junction needs: a junction row names a node, and what
     * that node is has to be looked up here.
     */
    public static function byNodeId(Scope $scope, int $nodeId): ?Definition
    {
        return self::tree($scope)->byNodeId[$nodeId] ?? null;
    }

    /** @return array<int, Definition> every definition, options included */
    public static function all(Scope $scope): array
    {
        return self::tree($scope)->byNodeId;
    }

    /**
     * One place up (-1) or down (+1) among its siblings.
     *
     * How nodes are reordered is the table's knowledge, not this tree's —
     * `Nodes\Operator::move()`. What stays here is which list the siblings
     * are.
     *
     * Returns the nodes whose ordering actually changed — the caller writes
     * them, and the commit is what makes the tree read afresh. A query
     * loads; whether and when something is saved is not its decision.
     *
     * @return list<Node> in tree order, empty when there is nowhere to move
     */
    public static function move(Scope $scope, Definition $definition, int $direction): array
    {
        $siblings = $definition->parent === null
            ? self::load($scope)
            : $definition->parent->options;

        [, $changed] = NodeOperator::move($siblings, $definition, $direction, static fn(Definition $d): Node => $d->node);
        return $changed;
    }

    // ------------------------------------------------------------ loading

    private static function tree(Scope $scope): self
    {
        return $scope->objectFor(self::class, 'tree', static fn(): self => self::build($scope));
    }

    private static function build(Scope $scope): self
    {
        Registry::register(Node::class);
        Registry::register(Setting::class);
        Registry::register(NodeJunction::class);

        $rows = $scope->rows(__DIR__ . '/SettingTree.sql', []);

        // One pass to build, a second to hang them together. The query is
        // ordered by parent, so a parent has always been seen by the time its
        // children arrive — but relying on that would make the order part of
        // the contract, and it does not have to be.
        $byNode = [];
        $parentOf = [];
        foreach ($rows->records() as $record) {
            $node    = $record[Node::class]    ?? null;
            $setting = $record[Setting::class] ?? null;
            if ($node === null || $setting === null) {
                continue;
            }
            $nodeId = (int) $node->id;
            $byNode[$nodeId] = new Definition(
                setting:         $setting,
                node:            $node,
                defaultJunction: $record[NodeJunction::class] ?? null,
            );
            // `relationId()` and not `$node->parentNode`: the property would go
            // through the resolver and build a lazy ghost for a parent we are
            // about to look up in this very array. A root carries NULL today
            // and 0 in the older rows — both mean "no parent".
            $parent = $node->relationId('parentNode');
            $parentOf[$nodeId] = ($parent === null || $parent === 0) ? null : $parent;
        }

        $roots = [];
        foreach ($byNode as $nodeId => $definition) {
            $parentId = $parentOf[$nodeId];
            $parent = $parentId !== null ? ($byNode[$parentId] ?? null) : null;
            if ($parent === null) {
                $roots[] = $definition;
                continue;
            }
            $definition->parent = $parent;
            $parent->options[] = $definition;
        }

        return new self($roots, $byNode);
    }
}
