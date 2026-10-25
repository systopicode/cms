<?php
/** @var \Systopic\Cms\Panels\Root\Cms\Tools\Maintenance\Misses\Panel $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$misses = $this->misses;
$pages = $this->pages;
?>
<h1>Unknown addresses</h1>
<p class="tagsHint">Addresses that were opened and belong to no page. Send one to a page and the next visit goes there.</p>
<?= \message::flush() ?>
<section class="widget is-list">
	<header>
		<h2>Addresses</h2>
		<?php if ($misses !== []) { ?>
			<input class="listSearch" type="search" placeholder="Search" data-on_input="filterMisses" autocomplete="off">
		<?php } ?>
	</header>
	<p class="tagsHint">The page keeps its own address. The old one stays as a second way in and sends visitors on.</p>
	<?php if ($misses === []) { ?>
		<main><p>No unknown addresses yet.</p></main>
	<?php } else { ?>
		<div class="tableScroll">
			<table>
				<thead>
					<tr>
						<th>Address</th>
						<th>Hits</th>
						<th>Page</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($misses as $miss) {
						$when = $miss->lastAt?->format('d.m.Y H:i') ?? '';
						?>
						<tr>
							<td>&#47;<?= $e($miss->path) ?><?php if ($when !== '') { ?><small class="muted"><?= $e($when) ?></small><?php } ?></td>
							<td class="muted"><?= (int) $miss->hits ?></td>
							<td>
								<select>
									<option value="">Choose a page</option>
									<?php foreach ($pages as $page) { ?>
										<option value="<?= (int) $page['node'] ?>"><?= $e($page['label'] !== '' ? $page['label'] : 'Untitled') ?></option>
									<?php } ?>
								</select>
							</td>
							<td class="icons">
								<ul>
									<li>
										<a class="button icononly" title="Send to this page" data-on_click="redirectMiss" data-miss_id="<?= (int) $miss->id ?>"><i class="fa-regular fa-diamond-turn-right"></i></a>
									</li>
									<li>
										<a class="button icononly" title="Forget" data-on_click="userConfirm" data-action="dismiss" data-miss_id="<?= (int) $miss->id ?>" data-confirm="<?= $e('Forget “/' . $miss->path . '”?') ?>"><i class="fa-regular fa-xmark"></i></a>
									</li>
								</ul>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
</section>
