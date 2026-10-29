<?php
/** Exercise the actual sidebar save action without touching project data. */
declare(strict_types=1);
class http {
    public static array $data = ['source'=>10,'target'=>20,'kind'=>'redirectFrom'];
    public static function dataset(string $key): mixed { return self::$data[$key] ?? null; }
}
class client {
    public static array $calls = [];
    public static function callFunction(string $name, array $args): void { self::$calls[] = [$name,$args]; }
}
class message {
    public static function confirm(string $text): void {}
    public static function error(string $text): void { throw new RuntimeException($text); }
}
$pages = new class {
    public array $saved = [];
    public function setPageRelation(int $source,int $target,string $kind): void { $this->saved[] = [$source,$target,$kind]; }
    public function updateView(string $name): void {}
};
$sidebar = new class {
    public string $action = 'chooseRelation';
    public function updateView(string $name): void {}
    public function run(object $pages): void {
        $page = (object)['id'=>10]; $row = true;
        include dirname(__DIR__) . '/panels/cms/010_pages/sidebar/actions.relations.php';
    }
};
$sidebar->run($pages);
if ($pages->saved !== [[20,10,'redirect']]) throw new RuntimeException('Redirect must be saved on selected source, pointing to open page.');
if (!in_array(['clearPageRelationSearch',[10]],client::$calls,true)) throw new RuntimeException('Successful save must clear the search.');
echo "ok: actual sidebar action saves source-to-current redirect and clears search after success\n";
