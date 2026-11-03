<?php
namespace Systopic\Cms\Panels\Root\Admin\Tools\Maintenance\Rewrite;
class Panel extends \Systopic\System\Panels\PanelNode {
    public function getNaviItem(): ?\htmlElement { return null; }
    public function onLoad() {
        if ($this->inPath()) \http::redirect(\http::$root . 'admin/tools/website/paths/' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
    }
}
