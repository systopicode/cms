<?php

declare(strict_types=1);

namespace Systopic\Cms\Tables\Misses;

use DateTimeImmutable;
use Systopic\Db\Schema\Col;
use Systopic\Db\TableRow;

/**
 * An address the website was asked for and had no page for — `cms_strays`.
 *
 * The table is `strays`, not `misses`: the legacy schema reads the singular
 * from the last letter, and `misses` would become `misse`.
 *
 * The next visit of the same address only raises the count. Sending it to a
 * page creates a rewrite path; the router already redirects those to the
 * page's own address.
 */
final class Model extends TableRow
{
    public const TABLE  = 'strays';
    public const PREFIX = 'stray';

    #[Col(length: 191)]
    public string $path {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** NULL is the default site, as on a rewrite path. */
    #[Col(unsigned: true)]
    public ?int $siteNodeId = null {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    #[Col(unsigned: true)]
    public int $hits = 0 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $lastAt {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
