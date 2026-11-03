<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages;

use Systopic\Cms\Tables\Pages\Page;
use Systopic\System\Auth\Session;
use Systopic\System\Tables\Nodes\Model as Node;

/**
 * Who may see and change which page — the content groups of the nodes
 * (`node_acl_id`, see system `.cursor/docs/acl.md`), for the website and the
 * CMS alike.
 *
 * Only where the project has switched page permissions on (`PAGE_ACL` 1 in
 * the global settings). A node without groups is for superusers only, and
 * every installation starts with nothing but such nodes: judged before its
 * pages have groups, a site would be empty and no editor could write a line.
 * A superuser (unless viewing as a role) and the recovery user are never
 * judged.
 */
final class PageAccess
{
    /** Are pages judged by their groups in this request? */
    public static function applies(): bool
    {
        return defined('PAGE_ACL') && \PAGE_ACL && !Session::isSuperuser() && !Session::isRecovery();
    }

    /**
     * The ACLs the viewer may read — the filter of the page queries
     * (`Params::$readAcls`); NULL for one who sees every page.
     *
     * @return list<int>|null
     */
    public static function readAcls(): ?array
    {
        return self::applies() ? Session::current()->readAclIds : null;
    }

    /**
     * May the user change this node: its page, its media, what is placed
     * below it? NULL is the topmost level, which has no groups: superusers.
     */
    public static function mayWrite(?Node $node): bool
    {
        if (!self::applies()) {
            return true;
        }
        return $node !== null && Session::mayWrite($node);
    }

    /**
     * May the user change the content of this page? An alias shows its
     * original's content: writing it needs the original as well.
     */
    public static function mayEdit(?Page $page): bool
    {
        if (!self::applies()) {
            return true;
        }
        if ($page === null || !self::mayWrite($page->node)) {
            return false;
        }
        return $page->contentNodeId === $page->id || self::mayWrite($page->node->originalNode);
    }

    /**
     * Refuse with a message when the user may not change one of the nodes.
     * For the actions: `if (PageAccess::refuses($node)) break;`
     */
    public static function refuses(?Node ...$nodes): bool
    {
        foreach ($nodes === [] ? [null] : $nodes as $node) {
            if (!self::mayWrite($node)) {
                \message::error($node === null
                    ? 'Only a superuser may change the topmost level.'
                    : 'You may not change this page (its content groups).');
                return true;
            }
        }
        return false;
    }

    /** The same for the content of a page (`mayEdit()`). */
    public static function refusesEdit(?Page $page): bool
    {
        if (self::mayEdit($page)) {
            return false;
        }
        \message::error('You may not change this page (its content groups).');
        return true;
    }
}
