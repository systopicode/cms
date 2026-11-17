<?php
declare(strict_types=1);

namespace Systopic\Cms\Panels\Root\Admin\Pages\Mml\GroupInfo;

use Systopic\System\Panels\Root\Admin\Panel as CmsPanel;

class Panel extends CmsPanel
{
    public function onLoadSelected()
    {
        // Another group uses the same route with different upload rules.
        $this->updateView('lightbox', true);
    }
}
