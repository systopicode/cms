<div class="cmslightbox cms" data-size="1">
    <section>
		<header>
			<h1>Default Meta Data how can be overwritten</h1>
			<ul class="barmenu">
				<li><a class="ajax" href="../../"><i class="fa-light fa-xmark-large"></i></a></li>
			</ul>
		</header>

		<main>
			<h2 style="margin-bottom: 0px;">Keywords</h2>
			<p><?= defined('META_KEYWORDS') ? META_KEYWORDS : '' ?></p>
			<br>
			<?php
			$descr = defined('META_DESCRIPTION') ? META_DESCRIPTION : '';
			?>
			<h2 style="margin-bottom: 0px;">Description (<?= strlen($descr) ?>/160)</h2>
			<p><?= $descr ?></p>
			<br>
			<h2 style="margin-bottom: 0px;">Page & Share Title</h2>
			<small>Will be added to Page Label at the end</small>
			<p><?= defined('PUBLIC_PAGE_NAME') ? PUBLIC_PAGE_NAME : '' ?></p>
			<br>
			<h2 style="margin-bottom: 0px;">Share Image</h2>
			<p>img/share_default.jpg <small>Best resolution 1200x630px</small></p> 
			<img src="<?= http::$root ?>img/share_default.jpg" style="width:50%">
		</main>
    </section>
</div>