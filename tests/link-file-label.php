<?php
// Exercise the actual template's file row with an unlabeled download.
require __DIR__ . '/../../system/src/Media/Presentation.php';
$source = file_get_contents(__DIR__ . '/../panels/cms/010_pages/editor/editLink/lightbox.tpl.php');
$start = strpos($source, '<?php foreach ($files as $i => $file) {');
$end = strpos($source, '</ul>', $start);
$files = [(object) ['label' => ' ', 'filename' => 'Archive & report.zip', 'mimetype' => 'application/zip']];
$faces = [['icon' => 'file-zipper']]; $thumbs = ['']; $fileHrefs = ['media:123']; $href = '';
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES);
ob_start(); eval('?>' . substr($source, $start, $end - $start)); $html = ob_get_clean();
if (substr_count($html, 'Archive &amp; report.zip') !== 3 || str_contains($html, '<img')) {
    throw new RuntimeException('Unlabeled file must use its escaped filename for title, link label and visible text.');
}
echo "ok: actual file-row template uses escaped filename fallback, preserves icon without thumbnail\n";
