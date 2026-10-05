<?php
/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Rewrite\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$flag = static fn(?bool $b): string => $b === NULL ? '' : ($b ? '1' : '0');
?>
<table class="keycolumn">
	<tr>
		<th></th><th>page</th><th>parent</th><th>node</th><th>lang</th><th>template</th>
		<th>path</th><th>auto index</th><th>primary</th><th>published</th>
	</tr>
	<?php foreach ($this->paths as ['path' => $path, 'page' => $page, 'node' => $node]) { ?>
		<tr>
			<td><a data-on_click="deletePath" data-rewritepath_id="<?= $path->id ?>"> <i class="far fa-trash"></i></a></td>
			<td><?php if ($node && $page) { ?><a href="../../../pages/?id=<?= $node->id ?>&lang=<?= $e($page->languageIso) ?>"> <i class="far fa-page"></i></a><?php } ?></td>
			<td><?= $node?->relationId('parentNode') ?></td>
			<td><?= $node?->id ?></td>
			<td><?= $e($page?->languageIso) ?></td>
			<td><?= $e($page?->templateName) ?></td>
			<td><?= $e($path->string) ?></td>
			<td><?= $path->autoIndex ?></td>
			<td><?= $flag($path->isPrimary) ?></td>
			<td><?= $flag($page?->isPublished) ?></td>
		</tr>
	<?php } ?>
</table>
