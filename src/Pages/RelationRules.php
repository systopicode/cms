<?php

declare(strict_types=1);
namespace Systopic\Cms\Pages;

/** Shared validation for alias and redirect edges, including mixed chains. */
final class RelationRules
{
    /** @param callable(int): ?array{original?:int,redirect?:int,parent?:int,type?:string,trashed?:bool} $node */
    public static function target(int $source, int $target, callable $node): void
    {
        if (($source !== 0 && $source < 2) || $target < 2 || $source === $target) throw new \DomainException('A page cannot point to itself.');
        $visiting = []; $done = [];
        $walk = function (int $id) use (&$walk, &$visiting, &$done, $source, $node): void {
            if ($id === $source || isset($visiting[$id])) throw new \DomainException('This assignment would create an alias or redirect loop.');
            if (isset($done[$id])) return;
            $row = $node($id);
            if ($row === null || ($row['type'] ?? 'page') !== 'page' || !empty($row['trashed'])) throw new \DomainException('Choose an existing page outside the trash.');
            $visiting[$id] = true;
            foreach (['original', 'redirect'] as $edge) if (($row[$edge] ?? 0) > 1) $walk((int) $row[$edge]);
            unset($visiting[$id]); $done[$id] = true;
        };
        $walk($target);
    }

    /** An alias below its own original would recursively include itself. */
    public static function placement(int $original, int $parent, callable $node): void
    {
        self::target(0, $original, $node);
        $seen = [];
        while ($parent > 1) {
            if (isset($seen[$parent])) throw new \DomainException('The destination contains a cycle.');
            $seen[$parent] = true;
            $row = $node($parent);
            if (($row['type'] ?? null) === 'site') break;
            self::target($original, $parent, $node);
            $parent = (int) ($row['parent'] ?? 0);
        }
    }
}
