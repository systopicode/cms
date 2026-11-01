<?php
/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Cleanup\Unused\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$unused = $this->unused;
?>
<h1>Unused media</h1>
<p class="tagsHint">Files no page uses: not in a library, not in a text, not a share image.</p>
<?= \message::flush() ?>
<section class="widget is-list">
	<header>
		<h2>Files</h2>
		<?php if ($unused !== []) { ?>
			<input class="listSearch" type="search" placeholder="Search" data-on_input="filterUnused" autocomplete="off">
		<?php } ?>
	</header>
	<p class="tagsHint">Permanently removes unused media records and their files. Media aliases are excluded from this list.</p>
	<?php if ($unused === []) { ?>
		<main><p>Every file is used by a page.</p></main>
	<?php } else { ?>
		<div class="tableScroll">
			<table>
				<thead>
					<tr>
						<th>File</th>
						<th>Type</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($unused as $row) { ?>
						<tr>
							<td><?= $e($row['filename'] !== '' ? $row['filename'] : 'Untitled') ?></td>
							<td class="muted"><?= $e($row['type']) ?></td>
							<td class="icons">
								<ul>
									<li>
										<button type="button" data-cms-action class="button icononly" title="Delete for good" data-on_click="userConfirm" data-action="deleteOne" data-node_id="<?= (int) $row['node'] ?>" data-confirm="<?= $e('Delete “' . ($row['filename'] !== '' ? $row['filename'] : 'Untitled') . '” for good? This cannot be undone.') ?>"><i class="cmsIcon cmsIcon-trash-xmark"></i></button>
									</li>
								</ul>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<footer><button type="button" data-cms-action class="button" data-on_click="userConfirm" data-action="deleteAll" data-confirm="<?= $e('Delete all ' . count($unused) . ' unused files for good? This cannot be undone.') ?>"><i class="cmsIcon cmsIcon-trash-xmark"></i><span>Delete all</span></button></footer>
	<?php } ?>
</section>
