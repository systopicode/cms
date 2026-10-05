<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Settings;

use Systopic\Db\TableRow;
use Systopic\Db\Type\Text;
use Systopic\System\Tables\Nodes\Model as Node;

/**
 * The declaration of one setting — `cms_settings`.
 *
 * The row says what a setting *is*, not what it holds: name, data type, label.
 * The value sits in `cms_junctions` — `NodeJunction` —, keyed by node and
 * setting node.
 *
 * **A setting is two levels, not one**, and this row is only one of them: the
 * declaration sits on a node, and that node's children are the options. A
 * checkbox has one child and stores 0 or 1 on it; a select has one child per
 * option and says what it means by which child the junction points at.
 * `Setting\Definition` is where the two levels meet, and `Setting\Values` is
 * what a page has chosen.
 */
final class Model extends TableRow
{
    public const TABLE  = 'settings';
    public const PREFIX = 'setting';

    public ?Node $node {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $type {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?string $name {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** Which of the three value columns in `cms_junctions` is meant. */
    public ?string $datatype = 'varchar' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $label {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Text $description {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
