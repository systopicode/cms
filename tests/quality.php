<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/system/tests/bootstrap.php';

use Systopic\Db\Connection;
use Systopic\Db\Scope;
use Systopic\Cms\Queries\UnusedMedia\UnusedMedia;
use Systopic\Cms\Queries\Misses\Misses;

if (($argv[1] ?? '') === 'worker') {
    $workerConnection = Connection::create(getenv('FLUXO_TEST_DSN'), getenv('FLUXO_TEST_USER') ?: 'root', getenv('FLUXO_TEST_PASSWORD') ?: '');
    check('isolated worker database', str_starts_with((string) $workerConnection->value('SELECT DATABASE()'), 'fluxo_test_'));
    for ($i = 0; $i < 25; $i++) \Systopic\Cms\Queries\Misses\Misses::note(new Scope($workerConnection), 'parallel-address', null);
    exit;
}

// Query integration without touching any existing database.
$dsn = getenv('FLUXO_TEST_DSN') ?: throw new RuntimeException('Set FLUXO_TEST_DSN to an isolated MySQL database named fluxo_test_…');
$pdo = new PDO($dsn, getenv('FLUXO_TEST_USER') ?: 'root', getenv('FLUXO_TEST_PASSWORD') ?: '');
check('isolated test database', str_starts_with((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'fluxo_test_'));
$connection = new Connection($pdo);
$created = [];
try {
$pdo->exec('CREATE TABLE cms_nodes (node_id INTEGER, node_parent_node_id INTEGER, node_original_node_id INTEGER, node_type TEXT, node_is_trashed INTEGER)');
$created[] = 'cms_nodes';
$pdo->exec('CREATE TABLE cms_medias (media_id INTEGER, media_node_id INTEGER, media_filename TEXT, media_type TEXT)');
$created[] = 'cms_medias';
$pdo->exec('CREATE TABLE cms_pages (page_id INTEGER PRIMARY KEY, page_language_iso VARCHAR(8), page_label VARCHAR(255), page_node_id INTEGER, page_meta_share_image INTEGER, page_html01 TEXT, page_html02 TEXT, page_html03 TEXT, page_html04 TEXT, page_html05 TEXT, page_html06 TEXT, page_html07 TEXT, page_html08 TEXT, page_html09 TEXT)');
$created[] = 'cms_pages';
$pdo->exec("INSERT INTO cms_nodes VALUES (10,0,0,'page',0),(20,0,0,'media',0),(21,0,20,'media',0),(22,0,0,'media',0),(23,0,0,'media',0),(24,0,23,'media',0),(25,0,24,'media',0)");
$pdo->exec("INSERT INTO cms_medias VALUES (1,20,'Referenced alias','image'),(2,22,'Unused','image'),(3,23,'Share alias chain','image')");
$pdo->exec("INSERT INTO cms_pages VALUES (1,'en','Live page',10,25,'<img src=\"media:21\">','','','','','','','','')");
$scope = new Scope($connection);
$unused = UnusedMedia::of($scope);
check('HTML alias and share-image alias chain protect their original files', array_column($unused, 'node') === [22]);
$pdo->exec('UPDATE cms_nodes SET node_original_node_id=25 WHERE node_id=24');
check('alias cycles terminate', count(UnusedMedia::of(new Scope($connection))) === 2);
$pdo->exec('UPDATE cms_nodes SET node_original_node_id=23 WHERE node_id=24');
$pdo->exec('UPDATE cms_nodes SET node_parent_node_id=10 WHERE node_id=22');
check('media placed on a page stay protected', UnusedMedia::of(new Scope($connection)) === []);

// The post-merge checker must understand both attributes and text references.
$pdo->exec('CREATE TABLE cms_rewritepaths (rewritepath_page_id INTEGER, rewritepath_string VARCHAR(191))');
$created[] = 'cms_rewritepaths';
$pdo->exec("INSERT INTO cms_rewritepaths VALUES (1,'live-page')");
$pdo->exec(<<<'SQL'
UPDATE cms_pages SET page_html01 = '<a href="page:10">live</a><img src="media:20"><a href="page:999">missing</a><img src="media:998">{media:997.filename}<a href="/live-page">legacy</a><a href="https://example.org/">external</a>' WHERE page_id = 1
SQL);
class http { public static string $root = '/'; }
$broken = \Systopic\Cms\Queries\LinkCheck\LinkCheck::broken(new Scope($connection));
check('link checker preserves page/media/text references and legacy paths', count($broken) === 3 && array_column($broken, 'target') === ['page:999', 'media:998', '{media:997.filename}']);
check('external links are collected without network requests', isset(\Systopic\Cms\Queries\LinkCheck\LinkCheck::external($scope)['https://example.org/']));

$pdo->exec("INSERT INTO cms_nodes VALUES (30,0,0,'page',1),(31,0,0,'media',0)");
$pdo->exec("INSERT INTO cms_pages (page_id,page_language_iso,page_label,page_node_id) VALUES (2,'en','Trashed',30),(3,'en','Wrong type',31)");
\Systopic\Db\Connection::setDefault($connection);
\Systopic\Db\Scope::setDefault($scope);
check('redirect target can be a live page', Misses::pageOf($scope, 10) !== null);
check('redirect target cannot be a trashed page', Misses::pageOf($scope, 30) === null);
check('redirect target cannot be a media node', Misses::pageOf($scope, 31) === null);
for ($i = 0; $i < 105; $i++) Misses::note($scope, 'test-address-' . $i, null);
check('dashboard counts beyond the 100-row panel limit', Misses::count($scope) === 105 && count(Misses::list($scope)) === 100);
Misses::note($scope, 'test-address-0', null);
check('repeated unknown address increments one row', Misses::one($scope, 'test-address-0', null)->hits === 2);
foreach (Misses::list($scope) as $miss) $scope->remove($miss);
foreach (Misses::list($scope) as $miss) $scope->remove($miss);
check('quality fixtures removed', Misses::count($scope) === 0);

$workers = [];
for ($i = 0; $i < 2; $i++) {
    $workers[] = proc_open([PHP_BINARY, __FILE__, 'worker'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/stderr', 'w']], $pipes);
}
foreach ($workers as $worker) check('concurrent worker completed', is_resource($worker) && proc_close($worker) === 0);
$parallel = Misses::one(new Scope($connection), 'parallel-address', null);
check('concurrent first visits create one row and preserve all 50 hits', $parallel?->hits === 50 && Misses::count(new Scope($connection)) === 1);
$scope->remove($parallel);

echo "All quality checks passed.\n";
} finally {
    foreach (array_reverse($created) as $table) $pdo->exec('DROP TABLE `' . $table . '`');
}
