<?php
/**
 * The pylon: search, tree, media library.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this
 */
$pylonWidth = (int) ($this->state->pylonWidth ?? 0) ?: 250;
$pylonWidthModule = implode('/', $this->path);
if ($this->childSelected && isset($this->childSelected->state->pylonWidth)) {
	$pylonWidth = (int) $this->childSelected->state->pylonWidth ?: $pylonWidth;
	$pylonWidthModule = implode('/', $this->childSelected->path);
}
?>
<div class="pylon cms" style="--pylonWidth: <?= (int) $pylonWidth ?>px;">

	<header></header>
	<nav>
		<?php
		echo message::flush('pylon');
		?>
		<div class="pylonTitle">
			<div class='searchInput'>
				<input class='autosend' id='cmsSearchInput' data-on_input='cmsPageSearch' name='search' autocomplete='off' type="search"
				       placeholder="Search" aria-label="Search pages" value="<?= htmlspecialchars((string) $this->state->cmsPageSearchstring) ?>">
			</div>
			<ul class="treeNavi">
				<?php if ($this->state->treeMode === 'trash') { ?>
					<li><a class="ajax put" href="closeAllFolds" title="Close all folders"><i class="fa-regular fa-folder-minus"></i></a></li>
					<li><a class='ajax put' href='hideTrash' title="Hide Trash"><i class="fa-regular fa-trash-slash"></i></a></li>
				<?php } else { ?>
					<li><a class="ajax put" href="closeAllFolds" title="Close all folders"><i class="fa-regular fa-folder-minus"></i></a></li>
					<li><a onclick='scrollTree("smooth", false, false)' title="Show the open page"><i class="fa-regular fa-crosshairs"></i></a></li>
					<li><a href='../tools/maintenance/trash/' title="Show Trash"><i class="fa-regular fa-trash"></i></a></li>
				<?php } ?>
			</ul>
			<a class="button submit icononly" data-on_click="new" title="Add Page"><i class="fa-regular fa-plus"></i></a>
		</div>
		<?php $searching = trim((string) $this->state->cmsPageSearchstring) !== ''; ?>
		<div class="tree<?= $searching ? ' is-results' : '' ?>">
			<ul class="tree searchHits" id="cmsSearchResults"><?= $this->state->cmsPageSearchResults ?></ul>
			<?php $this->withView('tree'); ?>
		</div>
	</nav>
	<footer class="resizeMML" style="max-height:<?= (int) $this->state->pylonFooterHeight ?: 300 ?>px; ">
			<div class="cms mml <?= ($this->page?->id ?? 0) ? '' : 'mml-empty' ?>">
				<div class="mmlContent">
					<label data-drag_type='none' data-on_draginit="draginitFooterHeight">
						<b>Media Library</b>
						<i class="mmlResize fa-regular fa-grip-lines" title="Resize"></i>
					</label>
					<div>
						<?php $this->withView('mml'); ?>
					</div>
				</div>
			</div></footer>
	<div data-drag_type='none' data-on_draginit='draginitPylonwidth'></div>
</div>
