<?php
require __DIR__ . '/../src/Queries/Duplicates/IdenticalFiles.php';
use Systopic\Cms\Queries\Duplicates\IdenticalFiles;
$dir = sys_get_temp_dir() . '/fluxo-duplicates-' . bin2hex(random_bytes(6));
mkdir($dir);
try {
    $files = [];
    foreach (['a.jpg'=>'same', 'b.zip'=>'same', 'c.mp4'=>'diff', 'empty.txt'=>'', 'empty.csv'=>''] as $name=>$content) {
        file_put_contents($dir.'/'.$name, $content);
        $files[] = ['id'=>count($files)+1, 'label'=>$name, 'path'=>$dir.'/'.$name];
    }
    $files[] = ['id'=>6,'label'=>'missing','path'=>$dir.'/missing'];
    $result = IdenticalFiles::scan($files);
    if (count($result['groups']) !== 2 || $result['unreadable'] !== 1 || $result['remaining'] !== 0) throw new RuntimeException(json_encode($result));
    if (IdenticalFiles::scan($files, 0)['remaining'] !== 5) throw new RuntimeException('Deadline not enforced');
    foreach ($files as $file) if ($file['id'] !== 6 && !is_file($file['path'])) throw new RuntimeException('Original modified');
    echo "PASS identical originals across extensions, different bytes, empty files, missing original, deadline; no writes/deletes by scanner\n";
} finally {
    foreach (glob($dir.'/*') as $file) unlink($file);
    rmdir($dir);
}
