<?php
/**
 * Search engine and sharing details of the page.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */
$page = $this->parent->page;
$row = $page->row;
$e = static fn($v): string => htmlspecialchars((string) ($v instanceof \Systopic\Db\Type\Text ? $v->value : $v), ENT_QUOTES);
$robots = [
	'index, follow' => 'Index, follow links',
	'index, nofollow' => 'Index, don’t follow links',
	'noindex, nofollow' => 'Hide from search'
	]
?>

<h3>Meta</h3>
<ul>
    <li class="seperator">Description</li>
    <li>
		<p class="hint">Search results and shares. Empty uses the first sentence on the page.</p>
		<textarea data-on_input='sidebar/updateParam' autocomplete='off'
				  data-node_id="<?= $page->id ?>" maxlength="160" type="text"
				  name="meta_description"><?= $e($row->metaDescription) ?></textarea>
		<small><span><?= mb_strlen((string) $row->metaDescription) ?></span> / 160</small>
    </li>
    <li class="seperator">Share title</li>
    <li>
		<p class="hint"><?= PAGE_TITLE_SEPERATOR ?> <?= PUBLIC_PAGE_NAME ?> is added after this.</p>
		<input  data-on_blur='sidebar/updateParam' autocomplete='off'
				class="ajax countSigns" maxlength="100" type="text"
				name="meta_share_title" value="<?= $e($row->metaShareTitle) ?>" />
		<small><span><?= mb_strlen((string) $row->metaShareTitle) ?></span> / 100</small>
    </li>
	<li class="seperator">Share image <i data-on_click="sidebar/resetShareImage" class="fa-regular fa-arrow-rotate-left" title="Use the site image"></i></li>
	<li>
		<div class="mediaDrop shareImage" data-drop_accept="media" data-on_drop="sidebar/updateShareImage"><?= $this->shareImage() ?></div>
	</li>
	<li class="seperator">Robots</li>
    <li>
		<select data-on_change='sidebar/updateParam'
				 autocomplete='off'
				 class="ajax"
				 name="meta_robots">
					 <?php
					 foreach ($robots as $robots => $label) {
						 $selected = $row->metaRobots === $robots ? 'selected' : '';
						 echo "<option $selected value='$robots' />$label</option>";
					 }
					 ?>
		</select>
    </li>
	<li class="seperator">Search and AI</li>
	<li>
		<div class="cmsSeoPreview"><?= $this->seoPreview() ?></div>
	</li>
</ul>
