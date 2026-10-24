<?php /** @var \Systopic\Cms\Panels\Root\Cms\Settings\Page\Delete\Panel $this */ ?>
<div class="cmslightbox cms pageSettingDialog">
	<section data-doc="box">
		<header>
			<h1>Delete setting</h1>
			<?php
			$back = '../';
			if ($this->definition?->parent) {
				$back = '../?id=' . (int) $this->definition->parent->nodeId;
			}
			echo \menu::create('barmenu')->link($back)->icon('fa-regular fa-xmark-large');
			?>
		</header>
		<main>
			<div class="layout">
				<p>This removes the setting and the values stored on pages. A setting that still has children cannot be deleted.</p>
				<?= \message::flush() ?>
			</div>
		</main>
		<footer>
			<?php if ($this->done) { ?>
				<a class="ajax button" href="<?= htmlspecialchars($back, ENT_QUOTES) ?>">Ok</a>
			<?php } else { ?>
				<a class='button submit' data-on_click='delete'>Delete Setting</a>
			<?php } ?>
		</footer>
	</section>
</div>
