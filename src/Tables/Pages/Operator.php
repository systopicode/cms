<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Pages;

use DateTimeImmutable;
use InvalidArgumentException;
use Systopic\Db\Extractor;
use Systopic\Db\Schema\Schema;
use Systopic\Db\Type\ColumnType;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Users\Model as User;

/**
 * What `cms_pages` knows how to do, whichever view brought the row.
 *
 * Two jobs the panel used to spread over its actions: turning what a form
 * sends into a typed value, and copying the content of one page row into
 * another.
 */
final class Operator
{
    /**
     * The columns a form may write, by the names the markup uses.
     *
     * The markup speaks column names without the prefix — `meta_description`,
     * `is_published`, `html03` — because the legacy record did. The list is
     * the guard: `updateParam` used to write whatever name the request
     * carried into the row.
     */
    public const EDITABLE = [
        'label', 'template_name',
        'meta_description', 'meta_keywords', 'meta_share_title', 'meta_robots', 'meta_json_ld', 'meta_share_image',
        'is_published', 'is_navi_element', 'start_publishing', 'end_publishing',
        'text01', 'text02', 'text03', 'text04', 'text05', 'text06', 'text07', 'text08', 'text09',
        'html01', 'html02', 'html03', 'html04', 'html05', 'html06', 'html07', 'html08', 'html09',
    ];

    /**
     * What `copy()` takes over: the content, not the identity.
     *
     * Node, language, the dates and users of creation, publishing state and
     * search text stay with the target.
     */
    public const CONTENT = [
        'label', 'templateName', 'isNaviElement',
        'metaDescription', 'metaKeywords', 'metaShareImage', 'metaShareTitle', 'metaRobots', 'metaJsonLd',
        'text01', 'text02', 'text03', 'text04', 'text05', 'text06', 'text07', 'text08', 'text09',
        'html01', 'html02', 'html03', 'html04', 'html05', 'html06', 'html07', 'html08', 'html09',
    ];

    /** `meta_share_title` → `metaShareTitle`; NULL for a name that may not be written. */
    public static function propertyOf(string $field): ?string
    {
        if (!in_array($field, self::EDITABLE, true)) {
            return null;
        }
        return lcfirst(str_replace('_', '', ucwords($field, '_')));
    }

    /**
     * Write one form value into a row, converted to the column's type.
     *
     * An empty string is NULL for everything but text: an emptied date field
     * means "no date", an emptied headline means an empty headline.
     *
     * @throws InvalidArgumentException for a field that may not be written
     */
    public static function assign(Model $row, string $field, mixed $input): void
    {
        $property = self::propertyOf($field)
            ?? throw new InvalidArgumentException("pages: '$field' is not an editable field");
        $row->$property = self::convert($property, $input);
    }

    /** The typed value for a property, from what a form sends. */
    public static function convert(string $property, mixed $input): mixed
    {
        $type = (new \ReflectionProperty(Model::class, $property))->getType();
        $name = $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed';

        if (is_string($input)) {
            $input = trim($input) === '' && $name !== 'string' && !is_a($name, ColumnType::class, true)
                ? null
                : $input;
        }
        if ($input === null) {
            return null;
        }

        return match (true) {
            $name === 'bool'   => filter_var($input, FILTER_VALIDATE_BOOLEAN),
            $name === 'int'    => (int) $input,
            $name === 'string' => (string) $input,
            $name === DateTimeImmutable::class => $input instanceof DateTimeImmutable
                ? $input
                : new DateTimeImmutable((string) $input),
            default => Extractor::fromDb(Model::class, $property, $input),
        };
    }

    /** Copy the content of one row into another; `$override` wins. */
    public static function copy(Model $from, Model $to, array $override = []): Model
    {
        foreach (self::CONTENT as $property) {
            $rp = new \ReflectionProperty(Model::class, $property);
            if ($rp->isInitialized($from)) {
                $to->$property = $from->$property;
            }
        }
        foreach ($override as $property => $value) {
            $to->$property = $value;
        }
        return $to;
    }

    /** A fresh row for a node and a language, stamped with who made it. */
    public static function create(Node $node, string $lang, ?int $userId, string $label = ''): Model
    {
        $row = new Model();
        $row->node = $node;
        $row->languageIso = $lang;
        $row->label = $label;
        $now = new DateTimeImmutable();
        $row->createdDate = $now;
        $row->modifiedDate = $now;
        if ($userId !== null && $userId > 0) {
            $row->createdUser = User::withId($userId);
            $row->modifiedUser = User::withId($userId);
        }
        return $row;
    }

    /** Stamp a change: date and user. */
    public static function touch(Model $row, ?int $userId): void
    {
        $row->modifiedDate = new DateTimeImmutable();
        if ($userId !== null && $userId > 0) {
            $row->modifiedUser = User::withId($userId);
        }
    }

    /** Guard against a schema drift the lists above would not notice. */
    public static function assertColumns(): void
    {
        $columns = Schema::of(Model::class)->propertyToColumn;
        foreach (self::CONTENT as $property) {
            if (!isset($columns[$property])) {
                throw new \LogicException("Pages\\Operator::CONTENT names \$$property, which is not a column");
            }
        }
    }
}
