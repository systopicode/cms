<?php
// Isolated markup fixture; no project database or media files are opened.
class mediaFormats {
    static function get() { return ['tile'=>['width'=>800,'height'=>600],'shareImage'=>['width'=>1200,'height'=>630]]; }
    static function sizeLabel($format) { return $format['width'].' × '.$format['height'].' px'; }
}
class http { static $root = '/'; static function get($key,$fallback='') { return $fallback; } }
class Fixture {
    public $targetId=1;
    public $state;
    function getPath() { return 'admin/pages/editor/file/'; }
    function render() {
        $this->state=(object)['format'=>'tile'];
        $media=new class {
            public $src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22800%22 height=%22600%22%3E%3Crect width=%22800%22 height=%22600%22 fill=%22%23a8c3ce%22/%3E%3C/svg%3E';
            function setFormat($format) { return $this; }
        };
        include dirname(__DIR__).'/panels/admin/010_pages/editor/file/image.tpl.php';
    }
}
(new Fixture)->render();
