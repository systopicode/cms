<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Route;

use Systopic\Cms\Tables\Pages\Page;
use Systopic\Db\Scope;
use Systopic\System\Auth\Session;

/**
 * Two site settings (page.settings.php) asked once a URL has found its page:
 *
 *   PAGE_IS_PUBLIC 0           only signed-in visitors see the site
 *   AUTO_LANGUAGE_DETECTION 1  the home page opens in the browser's language
 */
final class SiteAccess
{
    private const DETECTED_KEY = 'systopic_language_detected';

    /**
     * A site that is not public sends a visitor without a session to sign in:
     * the project's own sign-in (panels/auth) if it has one, the CMS's
     * otherwise. Either brings them back to the page (Session::takeReturn()).
     */
    public static function guard(): void
    {
        if (!defined('PAGE_IS_PUBLIC') || PAGE_IS_PUBLIC || PHP_SAPI === 'cli' || Session::known()) {
            return;
        }
        Session::rememberReturn((string) (\http::ajax() ? ($_SERVER['HTTP_REFERER'] ?? '') : ($_SERVER['REQUEST_URI'] ?? '')));
        $own = is_dir(rtrim((string) \fs::$projectRoot, '/') . '/panels/auth');
        $target = \http::$root . ($own ? 'auth/' : 'cms/auth/');
        // nothing of the page may follow, also not in an ajax answer
        if (\http::ajax()) {
            header('Content-type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode([['command' => 'redirect', 'data' => ['path' => $target, 'action' => '', 'method' => 'load']]]);
            exit;
        }
        header('Location: ' . $target);
        exit;
    }

    /**
     * The home page, on the first visit of a session: in the language the
     * browser prefers, when the site has it and the page has content in it.
     * Once per session, so the language menu can take the visitor back.
     */
    public static function detectLanguage(): void
    {
        $request = \app::request();
        if (!defined('AUTO_LANGUAGE_DETECTION') || !AUTO_LANGUAGE_DETECTION || PHP_SAPI === 'cli' || \http::ajax()
            || trim((string) $request->rawpath, '/') !== '' || \http::params('lang') || (string) $request->id === '') {
            return;
        }
        \http::sessionStartOnce();
        if (!empty($_SESSION[self::DETECTED_KEY])) {
            return;
        }
        $_SESSION[self::DETECTED_KEY] = true;
        $lang = self::preferred((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        if ($lang === null || $lang === $request->lang) {
            return;
        }
        $page = Page::byId(Scope::default(), Route::paramsFor($lang), (int) $request->id);
        if ($page?->row !== null && $page->link !== '') {
            \http::redirect($page->link);
        }
    }

    /** The first language of the Accept-Language header the site has switched on. */
    private static function preferred(string $header): ?string
    {
        $active = array_keys(array_filter(defined('LANGUAGES') ? LANGUAGES : [], static fn($l): bool => !empty($l['activated'])));
        preg_match_all('~([a-z]{2})(?:-[a-z0-9]+)?\s*(?:;\s*q=([\d.]+))?~i', $header, $hits, PREG_SET_ORDER);
        $ranked = [];
        foreach ($hits as $hit) {
            $code = strtolower($hit[1]);
            $ranked[$code] = max($ranked[$code] ?? 0.0, ($hit[2] ?? '') !== '' ? (float) $hit[2] : 1.0);
        }
        arsort($ranked);
        foreach (array_keys($ranked) as $code) {
            if (in_array($code, $active, true)) {
                return $code;
            }
        }
        return null;
    }
}
