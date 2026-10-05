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
		<div class="dropdown addMenu">
			<ul>
				<li>
					<a title="Hinzufügen"><i class="fa-light fa-circle-plus"></i></a>
					<ul class="addMenuItems">
						<li><a data-on_click="new" title="Seite nach der gewählten anlegen"><i class="fa-light fa-file-circle-plus"></i> Seite</a></li>
						<li><a class="ajax" href="./?site=new" title="Neue Site (Domain) anlegen"><i class="fa-regular fa-globe"></i> Site</a></li>
					</ul>
				</li>
			</ul>
		</div>
		<div class='searchInput'>
			<div class="inputGroup">
				<input class='autosend' id='cmsSearchInput' data-on_input='cmsPageSearch' name='search' autocomplete='off' type="search"
				       placeholder="Search" value="<?= $this->state->cmsPageSearchstring ?>">
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
		<ul class="treeNavi">
			<?php if ($this->state->treeMode === 'trash') { ?>
				<li><a class="ajax put" href="closeAllFolds" title="fold all"><i class="fa-light fa-square-minus"></i></a></li>
				<li><a class='ajax put' href='hideTrash' title="Hide Trash"><i class="fal fa-trash-slash"></i></a></li>
			<?php } else { ?>
				<li><a class="ajax put" href="closeAllFolds" title="fold all"><i class="fa-light fa-square-minus"></i></a></li>
				<li><a onclick='scrollTree("smooth", false, false)' title="jump to selected"><i class="fal fa-list-tree"></i></a></li>
				<li><a class="ajax" href="./?site=default" title="Default-Site bearbeiten"><i class="fa-regular fa-globe"></i></a></li>
				<li><a href='../tools/maintenance/trash/' title="Show Trash"><i class="fal fa-trash"></i></a></li>
			<?php } ?>
		</ul>
		<div class='tree'>
			<?php $this->withView('tree'); ?>
		</div>
	</nav>
	<footer class="resizeMML" style="max-height:<?= $this->state->pylonFooterHeight ?>px; ">
			<div class="cms mml <?= ($this->page?->id ?? 0) ? '' : 'mml-empty' ?>">
				<div class="mmlContent">
					<label data-drag_type='none' data-on_draginit="draginitFooterHeight"><b>Media Library</b></label>
					<div>
						<?php $this->withView('mml'); ?>
					</div>
				</div>
			</div></footer>
	<div data-drag_type='none' data-on_draginit='draginitPylonwidth'></div>
</div>
