<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
use Systopic\System\Media\Presentation;
use Systopic\Cms\Media\EditorTag;
foreach(['zip'=>'file-zipper','docx'=>'file-word','xlsx'=>'file-excel','pptx'=>'file-powerpoint','csv'=>'file-csv','txt'=>'file-lines','vcf'=>'address-card','skp'=>'cube'] as $ext=>$icon) {
 check('shared icon '.$ext,Presentation::icon('sample.'.$ext)===$icon);
}
foreach(['application/zip','application/vnd.openxmlformats-officedocument.wordprocessingml.document','text/plain','text/csv'] as $mime) {
 check('download-only file does not request an image thumbnail',!Presentation::hasThumbnail($mime));
 $html=EditorTag::stored(42,'archive.zip','My & <Archive>',$mime);
 check('download inserts editable label link with media reference',$html==='<a href="media:42" download>My &amp; &lt;Archive&gt;</a>');
}
check('empty label falls back to filename',str_contains(EditorTag::stored(42,'archive.zip','','application/zip'),'>archive.zip</a>'));
check('PDF keeps document preview',str_starts_with(EditorTag::stored(42,'sample.pdf','Sample','application/pdf'),'<iframe'));
check('image keeps image preview',str_starts_with(EditorTag::stored(42,'photo.png','Photo','image/png'),'<img'));
check('audio gets an audio player',str_starts_with(EditorTag::stored(42,'sound.mp3','Sound','audio/mpeg'),'<audio'));
$normalized=\Systopic\Cms\Refs\Resolver::normalize('<a href="/var/original/archive.zip" data-ref-href="media:42" download>Archive</a>');
check('saving resolved link preserves anchor and stable media reference',str_contains($normalized,'href="media:42"')&&str_contains($normalized,'>Archive</a>')&&!str_contains($normalized,'data-ref-href'));

check('page drop inserts label and stable reference',EditorTag::page(123,'About & Contact','de')==='<a href="page:123?lang=de">About &amp; Contact</a>');
