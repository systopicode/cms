<?php

declare(strict_types=1);

namespace Systopic\Cms\Media;

use Systopic\Cms\Pages\Route\PageAccess;
use Systopic\Cms\Pages\Route\Route;
use Systopic\Cms\Queries\RewritepathLookup\RewritepathLookup;
use Systopic\Cms\Tables\Pages\Page;
use Systopic\Db\Scope;
use Systopic\System\Tables\Medias\External;

/**
 * A YouTube or Vimeo film as a poster and a sentence, until the visitor
 * allows it. The iframe is loaded by public/site/embedConsent.js.
 *
 * The markup has no look of its own. A project styles `.extVideo`.
 * German and English are built in. Another language is another entry in COPY.
 */
final class EmbedConsent
{
    /** @var array<string, array<string, mixed>> */
    private const COPY = [
        'de' => [
            'privacy' => 'Datenschutzerklärung',
            'panelTitle' => 'Video-Einstellungen',
            'panelLead' => 'Hier können Sie die Einwilligung zum Laden externer Videos ändern oder widerrufen.',
            'youtube' => [
                'hint' => 'Beim Aktivieren werden Daten an YouTube/Google übertragen. Die Freigabe gilt für alle YouTube-Videos auf dieser Website und kann jederzeit in der {privacy} widerrufen werden.',
                'activate' => 'YouTube-Videos aktivieren →',
                'revoke' => 'YouTube deaktivieren →',
                'title' => 'YouTube-Video',
                'name' => 'YouTube-Videos',
                'off' => 'YouTube-Videos sind derzeit nicht aktiviert.',
                'on' => 'YouTube-Videos sind derzeit aktiviert und werden automatisch geladen.',
            ],
            'vimeo' => [
                'hint' => 'Beim Aktivieren werden Daten an Vimeo übertragen. Die Freigabe gilt für alle Vimeo-Videos auf dieser Website und kann jederzeit in der {privacy} widerrufen werden.',
                'activate' => 'Vimeo-Videos aktivieren →',
                'revoke' => 'Vimeo deaktivieren →',
                'title' => 'Vimeo-Video',
                'name' => 'Vimeo-Videos',
                'off' => 'Vimeo-Videos sind derzeit nicht aktiviert.',
                'on' => 'Vimeo-Videos sind derzeit aktiviert und werden automatisch geladen.',
            ],
        ],
        'en' => [
            'privacy' => 'Privacy policy',
            'panelTitle' => 'Video settings',
            'panelLead' => 'You can change or withdraw your choice to load external videos here.',
            'youtube' => [
                'hint' => 'Allowing this sends data to YouTube/Google. The choice applies to every YouTube video on this website and can be withdrawn any time in the {privacy}.',
                'activate' => 'Allow YouTube videos →',
                'revoke' => 'Turn YouTube off →',
                'title' => 'YouTube video',
                'name' => 'YouTube videos',
                'off' => 'YouTube videos are off.',
                'on' => 'YouTube videos are on and load on their own.',
            ],
            'vimeo' => [
                'hint' => 'Allowing this sends data to Vimeo. The choice applies to every Vimeo video on this website and can be withdrawn any time in the {privacy}.',
                'activate' => 'Allow Vimeo videos →',
                'revoke' => 'Turn Vimeo off →',
                'title' => 'Vimeo video',
                'name' => 'Vimeo videos',
                'off' => 'Vimeo videos are off.',
                'on' => 'Vimeo videos are on and load on their own.',
            ],
        ],
    ];

    /**
     * The poster, the sentence and the button. Empty when the medium is not
     * a YouTube or Vimeo address.
     *
     * `poster` is a ready URL. Without it, an image medium uses `poster_format`.
     *
     * @param array{class?:string, poster?:string, poster_format?:string, poster_loading?:string} $opts
     */
    public static function html(object $media, array $opts = []): string
    {
        $raw = trim((string) ($media->url ?? $media->media_url ?? ''));
        $info = External::parse($raw);
        if ($info === null || ($info['provider'] !== 'youtube' && $info['provider'] !== 'vimeo')) {
            return '';
        }
        $provider = $info['provider'];
        $copy = self::provider($provider);
        $poster = trim((string) ($opts['poster'] ?? ''));
        if ($poster === '' && method_exists($media, 'setFormat')) {
            $format = (string) ($opts['poster_format'] ?? 'background');
            try {
                $poster = (string) $media->setFormat($format === '' ? 'background' : $format)->src;
            } catch (\Throwable) {
                $poster = '';
            }
        }
        $class = trim('extVideo ' . (string) ($opts['class'] ?? ''));
        $alt = trim((string) ($media->label ?? $media->media_label ?? ''));
        $loading = (string) ($opts['poster_loading'] ?? 'lazy');
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $html = '<div class="' . $e($class) . '" data-ext-video'
            . ' data-provider="' . $e($provider) . '"'
            . ' data-embed-src="' . $e($info['embedSrc']) . '"'
            . ' data-embed-title="' . $e($copy['title']) . '">';
        if ($poster !== '') {
            $html .= '<img class="extVideo__poster" src="' . $e($poster) . '" alt="' . $e($alt) . '"'
                . ' decoding="async" loading="' . $e($loading) . '">';
        }
        $html .= '<div class="extVideo__overlay"><div class="extVideo__copy">';
        $html .= '<span class="extVideo__brand" aria-hidden="true"></span>';
        $html .= '<p class="extVideo__hint">' . self::hint($copy['hint']) . '</p>';
        $html .= '<button type="button" class="extVideo__btn" data-ext-activate="' . $e($provider) . '">'
            . $e($copy['activate']) . '</button>';
        $html .= '</div></div></div>';
        return $html;
    }

    /** The block a privacy page prints, so a visitor can withdraw the choice. */
    public static function panel(): string
    {
        $copy = self::COPY[self::lang()];
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $html = '<section class="videoConsent" data-video-consent-panel aria-labelledby="video-consent-title">';
        $html .= '<h2 id="video-consent-title" class="videoConsent__title">' . $e($copy['panelTitle']) . '</h2>';
        $html .= '<p class="videoConsent__lead">' . $e($copy['panelLead']) . '</p>';
        foreach (['youtube', 'vimeo'] as $provider) {
            $row = $copy[$provider];
            $html .= '<div class="videoConsent__provider" data-consent-provider="' . $e($provider) . '">';
            $html .= '<h3 class="videoConsent__name">' . $e($row['name']) . '</h3>';
            $html .= '<p class="videoConsent__status" data-consent-off>' . $e($row['off']) . '</p>';
            $html .= '<p class="videoConsent__status" data-consent-on hidden>' . $e($row['on']) . '</p>';
            $html .= '<button type="button" class="extVideo__btn" data-ext-activate="' . $e($provider) . '" data-consent-off>'
                . $e($row['activate']) . '</button>';
            $html .= '<button type="button" class="extVideo__btn" data-ext-revoke="' . $e($provider) . '" data-consent-on hidden>'
                . $e($row['revoke']) . '</button>';
            $html .= '</div>';
        }
        $html .= '</section>';
        return $html;
    }

    /** @return array{hint:string, activate:string, revoke:string, title:string, name:string, off:string, on:string} */
    private static function provider(string $provider): array
    {
        return self::COPY[self::lang()][$provider];
    }

    private static function hint(string $text): string
    {
        $label = self::COPY[self::lang()]['privacy'];
        $href = self::privacyHref();
        $linked = $href !== ''
            ? '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" rel="noopener">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>'
            : htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        return str_replace('{privacy}', $linked, $text);
    }

    private static function lang(): string
    {
        $code = PageAccess::$selectedPage?->language ?? '';
        if ($code === '' || $code === '*') {
            $code = defined('DEFAULT_LANG') ? (string) DEFAULT_LANG : 'en';
        }
        $code = strtolower(substr($code, 0, 2));
        return isset(self::COPY[$code]) ? $code : 'en';
    }

    /** The privacy page of this site, by its address. Empty when there is none. */
    private static function privacyHref(): string
    {
        try {
            $scope = Scope::default();
            foreach (['datenschutz', 'datenschutzerklaerung', 'privacy'] as $alias) {
                $path = RewritepathLookup::resolve($scope, $alias);
                $nodeId = $path?->page?->relationId('node');
                if ($nodeId === null) {
                    continue;
                }
                $lang = (string) ($path->page->languageIso ?? '');
                if ($lang === '' || $lang === '*') {
                    $lang = defined('DEFAULT_LANG') ? (string) DEFAULT_LANG : 'de';
                }
                $page = Page::byId($scope, Route::paramsFor($lang), $nodeId);
                $href = (string) ($page->href ?? '');
                if ($href !== '') {
                    return $href;
                }
            }
        } catch (\Throwable) {
            return '';
        }
        return '';
    }
}
