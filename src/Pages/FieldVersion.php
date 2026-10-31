<?php

declare(strict_types=1);
namespace Systopic\Cms\Pages;

use Systopic\Cms\Tables\Pages\Operator;
use Systopic\Db\Scope;

/** Sidebar controls carry the same optimistic version as inline editors. */
final class FieldVersion
{
    /** Page-level metadata only: no claim to a field history we do not store. */
    public static function latestMetadata(object $connection, int $id): array
    {
        $row = $connection->all('SELECT page_modified_date, user_fullname, user_name FROM cms_pages LEFT JOIN usm_users ON user_id = page_modified_user_id WHERE page_id = ?', [$id])[0] ?? [];
        return ['at' => (string) ($row['page_modified_date'] ?? ''), 'author' => (string) (($row['user_fullname'] ?? '') ?: ($row['user_name'] ?? ''))];
    }

    public static function attributes(object $page, string $field): string
    {
        if (Operator::propertyOf($field) === null || !$page->row) return '';
        static $records = [];
        $id = (int) $page->row->id;
        if (!array_key_exists($id, $records)) {
            $records[$id] = Scope::default()->connection->all('SELECT * FROM cms_pages WHERE page_id = ?', [$id])[0] ?? [];
        }
        $value = $records[$id]['page_' . $field] ?? '';
        $e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES);
        return 'data-field="' . $e($field) . '" data-field_version="' . hash('sha256', (string) $value) . '" data-node_id="' . (int) $page->id . '" data-language_iso="' . $e($page->language) . '"';
    }
}
