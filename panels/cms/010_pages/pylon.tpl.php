<?php
/**
 * The pylon: search, tree, media library.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this
 */
$pylonWidth = $this->state->pylonWidth;
$pylonWidthModule = implode('/', $this->path);
if ($this->childSelected && isset($this->childSelected->state->pylonWidth)) {
	$pylonWidth = $this->childSelected->state->pylonWidth;
	$pylonWidthModule = implode('/', $this->childSelected->path);
}
?>
<div class="pylon cms" style="max-width: <?= $pylonWidth ?>px;">

	<header>
		<div class='searchInput'>
			<i class="fa-regular fa-magnifying-glass searchIcon"></i>
			<div class="inputGroup">
				<input class='autosend' id='cmsSearchInput' data-on_input='cmsPageSearch' name='search' autocomplete='off' type="search"
				       placeholder="Search pages …" value="<?= $this->state->cmsPageSearchstring ?>">
				<span class="bar"></span>
				<label></label>
				<div class="cmsSearchResults">
					<ul id='cmsSearchResults'>
						<?= $this->state->cmsPageSearchResults ?>
					</ul>
				</div>
			</div>
		</div>
	</header>
	<nav>
		<?php
		echo message::flush('pylon');
		?>
		<div class="pylonTitle">
			<h2>Pages</h2>
			<ul class="treeNavi">
				<?php if ($this->state->treeMode === 'trash') { ?>
					<li><a class="ajax put" href="closeAllFolds" title="fold all"><i class="fa-regular fa-chevrons-up"></i></a></li>
					<li><a class='ajax put' href='hideTrash' title="Hide Trash"><i class="fa-regular fa-trash-slash"></i></a></li>
				<?php } else { ?>
					<li><a class="ajax put" href="closeAllFolds" title="fold all"><i class="fa-regular fa-chevrons-up"></i></a></li>
					<li><a onclick='scrollTree("smooth", false, false)' title="jump to selected"><i class="fa-regular fa-crosshairs"></i></a></li>
					<li><a href='../tools/maintenance/trash/' title="Show Trash"><i class="fa-regular fa-trash"></i></a></li>
				<?php } ?>
			</ul>
			<a class="button submit icononly" data-on_click="new" title="Add Page"><i class="fa-regular fa-plus"></i></a>
		</div>
		<div class='tree'>
			<?php $this->withView('tree'); ?>
		</div>
	</nav>
	<footer class="resizeMML" style="max-height:<?= $this->state->pylonFooterHeight ?>px; ">
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
