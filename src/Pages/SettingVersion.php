<?php

declare(strict_types=1);
namespace Systopic\Cms\Pages;

use Systopic\Db\Scope;

/** Version only the junctions belonging to one visible setting, not the entire page. */
final class SettingVersion
{
    public static function hash(object $connection, int $page, array $options): string
    {
        $options = array_values(array_unique(array_map('intval', $options)));
        sort($options);
        if (!$options) return hash('sha256', '[]');
        $rows = $connection->all('SELECT junction_setting_node_id, junction_varchar, junction_int, junction_decimal FROM cms_junctions WHERE junction_node_id = ? ORDER BY junction_setting_node_id', [$page]);
        return self::signature($rows, $options);
    }

    private static function signature(array $rows, array $options): string
    {
        $rows = array_values(array_filter($rows, static fn($row) => in_array((int) $row['junction_setting_node_id'], $options, true)));
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public static function attributes(object $page, int $field, array $options): string
    {
        $e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES);
        static $rows=[];
        $node=(int) $page->id;
        if (!isset($rows[$node])) $rows[$node]=Scope::default()->connection->all('SELECT junction_setting_node_id, junction_varchar, junction_int, junction_decimal FROM cms_junctions WHERE junction_node_id = ? ORDER BY junction_setting_node_id', [$node]);
        return 'data-field="setting:' . $field . '" data-field_version="' . self::signature($rows[$node], array_map('intval', $options)) . '" data-node_id="' . (int) $page->id . '" data-language_iso="' . $e($page->language) . '"';
    }
}
