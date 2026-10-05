<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\SettingValues;

use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\Junctions\Model as NodeJunction;
use Systopic\Cms\Queries\SettingTree\Definition;
use Systopic\Cms\Queries\SettingTree\SettingTree;

/**
 * What one node has actually set — the successor of `pageSettings`.
 *
 * Two questions that the legacy query answers in one go and this model keeps
 * apart:
 *
 *     SettingTree     what settings exist, and what may they be   — once
 *     SettingValues   what has this node set                      — per node
 *
 * `pageSettings.sql` joins both together, four levels deep with two
 * self-joins, and therefore reads the whole declaration again for every page.
 * Here the declaration is loaded once and the values come out of one flat
 * read of `cms_junctions`.
 *
 * **One query for any number of nodes.** `forNodes()` is the shape that
 * matters for a list: thirty pages in an overview cost one statement, not
 * thirty. `of()` is the single-node case and uses the same path. Asked twice
 * in a request it costs nothing the second time — until a junction is
 * written, which throws the result away.
 *
 * What is deliberately *not* here: inheritance along `node_parent_node_id`.
 * The legacy query does not do it either — it joins
 * `junction_node_id = :page_node_id` and nothing else — so a value either
 * stands on this node or the setting's default applies. If inheritance is
 * wanted it is a decision, not a restoration, and it belongs in one place
 * rather than in a JOIN nobody reads.
 */
final class SettingValues
{
    /**
     * @param array<int, NodeJunction> $bySettingNode setting node id => junction
     */
    private function __construct(
        private readonly Scope $scope,
        public readonly int $nodeId,
        private readonly array $bySettingNode,
    ) {}

    /** The values of one node. */
    public static function of(Scope $scope, int $nodeId): self
    {
        return self::forNodes($scope, [$nodeId])[$nodeId]
            ?? new self($scope, $nodeId, []);
    }

    /**
     * The values of several nodes — one statement for all of them.
     *
     * @param  list<int> $nodeIds
     * @return array<int, self> node id => values, one entry per id asked for
     */
    public static function forNodes(Scope $scope, array $nodeIds): array
    {
        $nodeIds = array_values(array_unique(array_map('intval', $nodeIds)));

        $perNode = [];
        foreach ($nodeIds as $id) {
            $perNode[$id] = [];
        }
        if ($nodeIds === []) {
            return [];
        }

        Registry::register(NodeJunction::class);
        sort($nodeIds);   // the same set in any order is the same result
        $rows = $scope->rows(__DIR__ . '/SettingValues.sql', ['nodeIds' => $nodeIds]);

        foreach ($rows->all(NodeJunction::class) as $junction) {
            // `relationId()` rather than `$junction->node`: both sides are node ids
            // here, and asking for the objects would build a lazy ghost per
            // junction only to read its id back out.
            $nodeId = $junction->relationId('node');
            $settingNodeId = $junction->relationId('settingNode');
            if ($nodeId !== null && $settingNodeId !== null && isset($perNode[$nodeId])) {
                $perNode[$nodeId][$settingNodeId] = $junction;
            }
        }

        $out = [];
        foreach ($perNode as $id => $junctions) {
            $out[$id] = new self($scope, $id, $junctions);
        }
        return $out;
    }

    // ------------------------------------------------------------ reading

    /**
     * The value of a setting — what this node set, else the default.
     *
     * For a checkbox that is the 0 or 1 in `junction_int` on its one option.
     * For a select it is the value column of the chosen option, and that is
     * usually empty: a select says what it means by **which** option the
     * junction points at, not by what it stores. `chosen()` is the question to
     * ask there.
     *
     * **The default applies when there is no row, not when the row says 0.**
     * That is a deliberate difference to `pageSetting->value`, which reads
     *
     *     return $firstJoined->$datatype ?: $firstJoined->$defaultDatatype;
     *
     * — with `?:`, so a checkbox explicitly stored as 0 falls through to its
     * default of 1 and can never read as off. Measured on `smp.de`: node 26
     * has `junction_int = 0` on setting node 84 and the legacy `value` reports
     * 1 for it.
     *
     * Nothing was broken by that, because nothing used it: the sidebar reads
     * `$setting->junction_int` straight off the record instead
     * (`030_settings.tpl.php`). Which is the actual finding — the value the
     * model computes and the value the panel renders were two different
     * things, and only one of them was right.
     */
    public function value(string $name): mixed
    {
        $definition = SettingTree::byName($this->scope, $name);
        if ($definition === null) {
            return null;
        }
        foreach ($definition->options as $option) {
            $junction = $this->bySettingNode[$option->nodeId] ?? null;
            if ($junction !== null) {
                return Definition::valueIn($junction, $definition->datatype);
            }
        }
        return $definition->default;
    }

    /**
     * What this node carries itself — no fallback to the default.
     *
     * The question the sidebar actually asks. `030_settings.tpl.php` renders
     * the checkbox from `$setting->junction_int`, which is the LEFT JOIN on
     * `:page_node_id` and nothing else: no row means unchecked, whatever the
     * setting declares as its default.
     *
     * Both answers are legitimate and they are not the same, so both are here
     * under their own name. Measured on `bad_endbach_de`: `header_img`
     * declares a default of 1 and only two nodes out of 464 carry a row for
     * it — so `value()` says "on" for 462 pages and `stored()` says "nothing
     * set". Which of the two the panel should show is a decision about the
     * product, not about the model.
     */
    public function stored(string $name): mixed
    {
        $definition = SettingTree::byName($this->scope, $name);
        if ($definition === null) {
            return null;
        }
        foreach ($definition->options as $option) {
            $junction = $this->bySettingNode[$option->nodeId] ?? null;
            if ($junction !== null) {
                return Definition::valueIn($junction, $definition->datatype);
            }
        }
        return null;
    }

    /**
     * The option this node picked, or NULL when it picked none.
     *
     * The **first** in tree order when there are several, which is what
     * `pageSetting->key` does as well: it takes `reset($this->joined)` out of a
     * result ordered by `setting_nodes.node_ordering`. For a select that is
     * the answer; for a `group` — where several are the point — `chosenAll()`
     * is.
     */
    public function chosen(string $name): ?Definition
    {
        return $this->chosenAll($name)[0] ?? null;
    }

    /**
     * Every option this node picked, in tree order.
     *
     * A select has one, a `group` has as many as it likes: in
     * `bad_endbach_de`, `page_tags` is a group and node 34 carries `baden` and
     * `gesund`. The legacy model can only ever name the first — `key` returns
     * one string — so a template that wanted them all read the result rows
     * itself.
     *
     * @return list<Definition>
     */
    public function chosenAll(string $name): array
    {
        $definition = SettingTree::byName($this->scope, $name);
        if ($definition === null) {
            return [];
        }
        $out = [];
        foreach ($definition->options as $option) {
            if (isset($this->bySettingNode[$option->nodeId])) {
                $out[] = $option;
            }
        }
        return $out;
    }

    /** The name of the chosen option — `$setting->key` in the legacy model. */
    public function key(string $name): ?string
    {
        return $this->chosen($name)?->name;
    }

    /** A checkbox, asked the way a checkbox is asked. */
    public function isChecked(string $name): bool
    {
        return (bool) $this->value($name);
    }

    /** Did this node set anything on this option at all? */
    public function has(int $settingNodeId): bool
    {
        return isset($this->bySettingNode[$settingNodeId]);
    }

    public function junction(int $settingNodeId): ?NodeJunction
    {
        return $this->bySettingNode[$settingNodeId] ?? null;
    }

    /** @return array<int, NodeJunction> setting node id => junction */
    public function all(): array
    {
        return $this->bySettingNode;
    }
}
