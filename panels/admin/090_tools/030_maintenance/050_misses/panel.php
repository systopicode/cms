<?php
namespace Systopic\Cms\Panels\Root\Admin\Tools\Maintenance\Misses;
class Panel extends \Systopic\System\Panels\PanelNode {
    public function getNaviItem(): ?\htmlElement { return null; }
    public function onLoad() {
        if ($this->inPath()) \http::redirect(\http::$root . 'admin/tools/website/misses/' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
    }
}
