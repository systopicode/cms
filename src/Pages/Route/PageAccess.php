<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Route;

use Systopic\Db\Connection;
use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\System\Tables\Nodes\Model as NodeModel;
use Systopic\Cms\Tables\Pages\Model as PageModel;
use Systopic\Cms\Tables\Pages\Page;
use Systopic\Cms\Tables\Rewritepaths\Model as RewritepathModel;

/**
 * The page of the request: are the page tables there, may the URL's page be
 * shown, which page is it.
 *
 * Until 2026-10-04 the static part of the legacy class `pages` — which, as a
 * `records` subclass, loaded the legacy record on every request.
 */
final class PageAccess
{
    /** After check(): the selected page of the Route. */
    public static ?Page $selectedPage = null;

    public static bool $accessGranted = false;

    /** 'ok', 'page_not_available' or 'no_page_tree' — for 403/404 in loader.php and site.php. */
    public static ?string $lastQueryResult = null;

    private static ?bool $databaseReady = null;

    /**
     * Are the page tables there? Once per request, by asking them directly —
     * `db::hasTable()` read information_schema, every call, up to four per request.
     */
    /**
     * Not one page yet — the tables are missing (nothing was ever written)
     * or empty. FALSE without a connection: then nothing is known.
     */
    public static function hasNoPages(): bool
    {
        if (!Connection::isOpen()) {
            return false;
        }
        if (!self::databaseReady()) {
            return true;
        }
        return (int) Scope::default()->connection->value(
            'SELECT COUNT(*) FROM ' . Schema::tableNameOf(PageModel::class)
        ) === 0;
    }

    public static function databaseReady(): bool
    {
        if (self::$databaseReady !== null) {
            return self::$databaseReady;
        }
        if (!Connection::isOpen()) {
            return false;   // not cached: the connection may come later in the request
        }
        $tables = array_map(
            static fn(string $class) => Schema::tableNameOf($class),
            [PageModel::class, NodeModel::class, RewritepathModel::class],
        );
        try {
            Scope::default()->connection->run('SELECT 1 FROM ' . implode(', ', $tables) . ' LIMIT 0');
            return self::$databaseReady = true;
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1146) {   // a table is missing
                return self::$databaseReady = false;
            }
            throw $e;
        }
    }

    /**
     * Builds the Route for the request's page and decides whether it may be
     * shown.
     *
     * No fs::openRootDir() here. The legacy version needed it because
     * route::build() resolved templates relative to the CWD; Route resolves
     * them from absolute roots. Worse, the openDir() was never balanced by a
     * closeDir(), so calling it from the dispatcher left the dir stack
     * shifted — app.php then failed to fs::openDir() its (relative)
     * selectedRoot and rendered nothing.
     */
    public static function check(): bool
    {
        $req = \app::request();
        \Systopic\Cms\Pages\Router\UrlResolver::resolve();

        $tree = Route::getInstance();
        if ($tree === null) {
            self::$lastQueryResult = 'no_page_tree';
            self::$accessGranted = false;
            return false;
        }

        $tree->build($req->id, $req->lang);
        // The route loads only what may be shown: published (or preview), with
        // content in the language. A page that is not there leaves the root as
        // the last node, and that has no page.
        self::$selectedPage = $tree->getSelected()?->page;
        self::$lastQueryResult = self::$selectedPage?->row !== null ? 'ok' : 'page_not_available';
        self::$accessGranted = (self::$lastQueryResult === 'ok');
        return self::$accessGranted;
    }
}
