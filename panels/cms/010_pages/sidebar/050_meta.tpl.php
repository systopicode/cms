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
	'index, follow' => 'Indexierung, Links verfolgen',
	'index, nofollow' => 'Indexierung, Links nicht verfolgen',
	'noindex, nofollow' => 'keine Indexierung, Links nicht verfolgen'
	]
?>

<h3>Meta</h3>
<ul>
    <li><a
			class="ajax icon" style="float:right" href="sidebar/defaultMeta/">
			<i style='margin-right:0;' class="fa-light fa-circle-info"></i>
		</a>
		If empty the default Page Meta-Tags will be used </li>
    <li class="seperator">Description</li>
    <li>
		<p><a style="float:right" class="icon" href="https://emojipedia.org/"
			  target="_blank">
				<i style="margin:0; color:#fff;" class="fal fa-smile filled"></i>
			</a>Search Results & Social Media Shares
		</p>
		<textarea data-on_input='sidebar/updateParam' autocomplete='off' style="padding:10px"
				  data-node_id="<?= $page->id ?>" maxlength="160" type="text"
				  name="meta_description"><?= $e($row->metaDescription) ?></textarea>
		<small><span><?= mb_strlen((string) $row->metaDescription) ?></span> / 160</small>
    </li>
    <li class="seperator">Keywords</li>
    <li>
		<p>Separated by a comma</p>
		<textarea data-on_input='sidebar/updateParam' autocomplete='off' style="padding:10px"
				  data-node_id="<?= $page->id ?>" type="text"
				  name="meta_keywords"><?= $e($row->metaKeywords) ?></textarea>
    </li>
    <li class="seperator">Page & Share Title</li>
    <li>
		<p><a style="float:right" class="icon" href="https://emojipedia.org/"
			  target="_blank">
				<i style="margin:0; color:#fff;" class="fal fa-smile filled"></i>
			</a><?= PAGE_TITLE_SEPERATOR ?> <?= PUBLIC_PAGE_NAME ?> - will be added
		</p>
		<input  data-on_blur='sidebar/updateParam' autocomplete='off' style="padding: 10px;"
				class="ajax countSigns" maxlength="100" type="text"
				name="meta_share_title" value="<?= $e($row->metaShareTitle) ?>" />
		<small><span><?= mb_strlen((string) $row->metaShareTitle) ?></span> / 100</small>
    </li>
	<li class="seperator">Share Image <i data-on_click="sidebar/resetShareImage" class='floatRight fal fa-arrow-rotate-left' title='Reset Image'></i></li> 
	<li>
		<div class="mediaDrop shareImage" data-drop_accept="media" data-on_drop="sidebar/updateShareImage"><?= app::site()->shareImage ?></div>
	</li>
	<li class="seperator">Robots</li>
    <li>
		<p>Search engine indexing</p>
		<select  data-on_change='sidebar/updateParam' 
				 autocomplete='off'
				 class="ajax" style="padding: 10px;" 
				 name="meta_robots" 
				 >
					 <?php
					 foreach ($robots as $robots => $label) {
						 $selected = $row->metaRobots === $robots ? 'selected' : '';
						 echo "<option $selected value='$robots' />$label</option>";
					 }
					 ?>
		</select>
    </li>
	<li class="seperator">KI</li>
	<li>
		<p>JSON LD <a style="float:right" class="icon" href="https://de.wikipedia.org/wiki/JSON-LD"
			  target="_blank">
				<i style='margin-right:0;' class="fa-light fa-circle-info"></i>
			</a>
		</p>
		<p>Additional to, not replacing the main entry</p>
		<textarea data-on_input='sidebar/updateParam' autocomplete='off' style="padding:10px"
				  data-node_id="<?= $page->id ?>" type="text"
				  name="meta_json_ld"><?= $e($row->metaJsonLd) ?></textarea>
	</li>
</ul>
