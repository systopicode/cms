<?php
declare(strict_types=1);
require dirname(__DIR__,2) . '/system/tests/bootstrap.php';

use Systopic\Cms\Search\HeadMarkup;
use Systopic\Cms\Tables\Pages\Languages;
use Systopic\Cms\Pages\Renderer\PagesRenderer;

define('DEFAULT_LANG', 'it');
define('LANGUAGES', ['de'=>['label'=>'Deutsch','code'=>'de','activated'=>true], 'it'=>['label'=>'Italiano','code'=>'it','activated'=>true], 'en'=>['label'=>'English','code'=>'en_GB','activated'=>true], 'fr'=>['label'=>'French','code'=>'fr','activated'=>false]]);
check('configured default is used instead of first language', Languages::default() === 'it');
check('a new route starts in the configured default language', (new Systopic\Cms\Pages\Route\Route())->getLanguage() === 'it');
check('language without a configured region stays a language', Languages::tag('it') === 'it');
check('configured region is retained and normalized', Languages::tag('en') === 'en-GB');
check('disabled and unknown language resolve to configured default', Languages::resolve('fr') === 'it' && Languages::resolve('xx') === 'it');
$html = '<title>Old &amp; title</title><meta name="description" content="old"><link rel="canonical" href="old"><link rel="alternate" hreflang="de" href="old"><meta property="og:type" content="article"><link rel="stylesheet" href="/x.css"><script>window.test="unchanged";</script>'
    . '<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"Person","name":"A"},{"@type":"WebPage","url":"old"}]}</script>'
    . '<script type="application/ld+json" data-cms-meta="1">{"@type":"WebPage"}</script>';
$clean = HeadMarkup::withoutPageMetadata($html);
check('old page metadata is removed while styles and executable scripts remain', !str_contains($clean,'<title') && !str_contains($clean,'canonical') && !str_contains($clean,'description') && str_contains($clean,'/x.css') && str_contains($clean,'window.test="unchanged";'));
check('site-wide structured data remains without stale page nodes', str_contains($clean,'"Person"') && !str_contains($clean,'"WebPage"'));
$child = (object) ['html'=>'<picture><img src="photo.jpg" alt="A &amp; B"></picture>','childLayers'=>[]];
$root = (object) ['html'=>'<main>before<template class=domLayerPlaceholder></template>after</main>','childLayers'=>[$child]];
$ssr = PagesRenderer::layerHtml($root, 'body');
check('server HTML includes real nested media and surrounding text', str_contains($ssr,'before<!--cms-layer:body.0:start--><picture>') && str_contains($ssr,'after</main>') && substr_count($ssr,'<img') === 1);
check('server anchors preserve layer position without layout wrappers', !str_contains($ssr,'domLayerPlaceholder') && str_contains($ssr,'cms-layer:body.0:end'));
$root->html = '<template class="domLayerPlaceholder"></template>';
check('normalized quoted head placeholders also render their child', str_contains(PagesRenderer::layerHtml($root,'head'),'<picture>'));
