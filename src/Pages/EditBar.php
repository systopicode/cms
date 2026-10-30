<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages;

use Systopic\System\Auth\Portrait;
use Systopic\System\Auth\Session;
use Systopic\System\Sys\Packages;
use Systopic\System\Sys\Theme;

/**
 * The bar a signed-in editor sees on the website: this page in the CMS, the
 * CMS sections, sign out. Page setting FRONTEND_EDIT_BAR 0 turns it off.
 */
final class EditBar
{
    public static function html(int $pageId, string $lang): string
    {
        if ((defined('FRONTEND_EDIT_BAR') && !FRONTEND_EDIT_BAR) || !Session::known() || !Session::hasRole('editor') || \http::ajax()) {
            return '';
        }
        $identity = Session::current();
        $config = [
            'cms' => \http::$root . 'cms/',
            'page' => ['id' => $pageId, 'lang' => $lang],
            'name' => $identity->fullname !== '' ? $identity->fullname : $identity->name,
            'portrait' => Portrait::src((int) $identity->id, $identity->user?->email),
            'signet' => \Systopic\System\Settings\ThemeAssets::href('signet'),
            'main' => '#' . Theme::color('cmsMainColor'),
            'highlight' => '#' . Theme::color('cmsHighlightColor'),
            'bg' => '#' . Theme::color('cmsBgColor'),
            'onMain' => '#' . Theme::textOn('cmsMainColor'),
            'onHighlight' => '#' . Theme::textOn('cmsHighlightColor'),
            'icons' => Packages::panelAssetHref('css/11_icons.css'),
        ];
        return '<fluxo-edit-bar></fluxo-edit-bar><script>window.fluxoEditBar = '
            . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>'
            . \fsLoader::includeScriptFiles(\fs::toInternal(dirname(__DIR__, 2) . '/public/site/editBar.js'));
    }
}
