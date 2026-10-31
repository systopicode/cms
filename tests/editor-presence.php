<?php
require __DIR__ . '/../src/Pages/EditorPresence.php';
function check(string $label, bool $ok): void { if (!$ok) throw new RuntimeException($label); echo 'ok: ' . $label . PHP_EOL; }
use Systopic\Cms\Pages\EditorPresence;
$dir = sys_get_temp_dir() . '/fluxo-presence-' . bin2hex(random_bytes(8));
$path = $dir . '/presence.json';
try {
    check('first editor is alone', EditorPresence::touch($path,'9:en','a',100) === 0);
    check('second tab sees first editor', EditorPresence::touch($path,'9:en','b',101) === 1);
    check('first editor sees other tab', EditorPresence::touch($path,'9:en','a',102) === 1);
    EditorPresence::touch($path,'','a',102);
    check('reload retirement removes old document without removing other tabs', EditorPresence::touch($path,'9:en','new-a',102) === 1);
    EditorPresence::touch($path,'','new-a',102);
    EditorPresence::touch($path,'9:en','a',102);
    check('switching pages does not leave same tab active on old page', EditorPresence::touch($path,'10:en','b',103) === 0);
    check('old page has only remaining editor', EditorPresence::touch($path,'9:en','a',104) === 0);
    check('leaving removes this tab immediately', EditorPresence::touch($path,'','a',105) === 0);
    check('other client sees an empty page after leaving', EditorPresence::touch($path,'9:en','d',106) === 0);
    check('stale editors expire', EditorPresence::touch($path,'10:en','c',150) === 0);
    $users=[];
    EditorPresence::touch($path,'20:en','person-a',200,3,$users);
    EditorPresence::touch($path,'20:en','person-b',201,4,$users);
    check('other user IDs are available without client tokens', $users === [3]);
    EditorPresence::touch($path,'20:en','person-c',202,3,$users);
    check('same person is excluded from named peers', $users === [4]);
    $peers=[];
    EditorPresence::touch($path,'30:en','ipad',210,3,$users,'iPad · Safari',$peers);
    EditorPresence::touch($path,'30:en','mac',211,3,$users,'Mac · Firefox',$peers);
    check('same user in another device is named without exposing session tokens', $peers === [['user'=>3,'device'=>'iPad · Safari']]);
    EditorPresence::touch($path,'30:en','other',212,4,$users,'Windows · Chrome',$peers);
    check('multiple devices are retained separately', count($peers) === 2); 
} finally { @unlink($path); @rmdir($dir); }
