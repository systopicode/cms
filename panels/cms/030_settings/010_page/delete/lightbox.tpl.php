<?php /** @var \Systopic\Cms\Panels\Root\Cms\Settings\Page\Delete\Panel $this */ ?>
<div class='cmslightbox cms' data-size="2">
	<section data-doc=box>
		<header>
			<h1>Delete Setting</h1>
			<?= \menu::create('barmenu')->link('../')->icon('far fa-times') ?>
		</header>
		<main>
			<div class="layout">
				<p>This will remove The Setting item and all related page values, are you sure?</p>
				<?= \message::flush() ?>
			</div>
		</main>
		<footer>
			<?php if ($this->done) { ?>
				<a class='button' href='../'>Ok</a>
			<?php } else { ?>
				<a class='button submit' data-on_click='delete'>Delete Setting</a>
			<?php } ?>
		</footer>
	</section>
</div>
