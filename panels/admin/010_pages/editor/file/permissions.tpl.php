<?php
$permissionNode = $this->mediaRow?->node;
if ($permissionNode === null) return;
$permissionScope = $this->pages()->scope;
$permissionAction = '';
$permissionQuery = '?target_id=' . (int) $this->targetId;
$permissionEntity = 'file';
include dirname(__DIR__, 2) . '/sidebar/010_content/040_permissions/main.tpl.php';
