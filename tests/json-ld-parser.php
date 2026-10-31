<?php
/** The optional supplement parser, not a new JSON-LD editor. */
declare(strict_types=1);
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
require dirname(__DIR__).'/lib/class/app/site/appSite.php';
$site=new appSite();$parser=new ReflectionMethod(appSite::class,'extraJsonLd');
$parse=static fn(?string $raw)=>$parser->invoke($site,$raw);
check('empty and invalid JSON-LD supplements are ignored',$parse(null)===[]&&$parse('{broken')===[]&&$parse('42')===[]);
check('one typed object is accepted without replacing global context',$parse('{"@context":"https://schema.org","@type":"Article","headline":"Example"}')===[['@type'=>'Article','headline'=>'Example']]);
check('a typed node list is accepted',count($parse('[{"@type":"Article"},{"@type":"Person"}]'))===2);
check('a graph supplement filters untyped entries',count($parse('{"@graph":[{"@type":"Article"},{"name":"No type"},false]}'))===1);
$code=file_get_contents(dirname(__DIR__).'/lib/class/app/site/appSite.php');
check('automatic graph remains built from the selected page',str_contains($code,"'@type' => 'WebPage'")&&str_contains($code,'$this->record($page)')&&str_contains($code,'$this->breadcrumb($page)'));
check('script-sensitive characters remain escaped during serialization',str_contains($code,'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS'));
