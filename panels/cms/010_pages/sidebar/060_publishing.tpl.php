<?php
/**
 * Visibility of the page: navigation, published, time window, trash.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */
$page = $this->parent->page;
$row = $page->row;
$e = static fn($v): string => htmlspecialchars((string) ($v instanceof \Systopic\Db\Type\Text ? $v->value : $v), ENT_QUOTES);
$datetime = static fn(?DateTimeImmutable $d): string => $d?->format('Y-m-d\TH:i:s') ?? '';
?>
<h3>Publishing</h3>
<ul>
	<li>
		<label class="checkbox">
			<span>Navi Element</span>
			<input type=checkbox class=switch data-on_change='sidebar/updateParam'
				   name=is_navi_element <?php if ($row->isNaviElement) { ?> checked='checked' <?php } ?> />
			<div>
				<div></div>
			</div>
		</label>
		<label class="checkbox"><span>Published</span><input data-on_change='sidebar/updateParam'
															 type="checkbox" <?php if ($row->isPublished) { ?> checked='checked' <?php } ?>
															 value='1' name='is_published' class="switch tinyswitch"/>
			<div>
				<div></div>
			</div>
		</label>
		<div class="group">
			<a class="ajax put" href="../pages/publishAll"><i style="margin-right:10px" class="fa-light fa-eye"></i>Self + Children</a>
		</div><br>
		<div class="group">
			<a class="ajax put" href="../pages/unPublishAll"><i  style="margin-right:10px" class="fa-light fa-eye-slash"></i>Self + Children</a>
		</div>
	</li>
	<li>
		<?php
		$startPublishing = $datetime($row->startPublishing);
		$endPublishing = $datetime($row->endPublishing);
		?>
		<div class="inputGroup">
			<input name="start_publishing" placeholder=' ' type="datetime-local"
				   data-on_change='sidebar/updateParam' class="date" value="<?= $startPublishing ?>"
				   autocomplete="off"/>
			<span class="bar"></span>
			<label>Publishing start</label>
		</div>
		<div class="inputGroup">
			<input name="end_publishing" placeholder=' ' type="datetime-local" data-on_change='sidebar/updateParam'
				   class="date" value="<?= $endPublishing ?>" autocomplete="off"/>
			<span class="bar"></span>
			<label>Publishing end</label>
		</div>
	</li>
	<?php if ($page->isAlias && !$page->hasChildren) { ?>
		<li title="Delete Alias" >
			<a data-confirm="Do you really want to delete this Alias Page?"
			   class='alias'
			   data-on_click='userConfirm'
			   data-action="deleteAlias&node_id=<?= $page->id ?>&node_original_node_id=<?= $page->contentNodeId ?>">
				<i style="margin-right:10px"  class="fa-regular fa-trash"></i>
				Remove Alias Page
			</a>
		</li>
	<?php } ?>
	<?php if (!$page->isAlias && !$page->node->isTrashed) { ?>
		<li title="Delete Page" >
			<a data-confirm="All langauges, children and versions will be removed!"
			   data-on_click='userConfirm'
			   data-action="deletePage"
			   data-node_id="<?= $page->id ?>">
				<i style="margin-right:10px"  class="fa-regular fa-trash"></i>
				Remove Page
			</a>
		</li>
	<?php } else { ?>
		<li title="Recover Page" >
			<a data-confirm="All langauges, children and versions will be recoverd!"
			   data-on_click='userConfirm'
			   data-action="recoverPage"
			   data-node_id="<?= $page->id ?>">
				<i style="margin-right:10px"  class="fa-regular fa-arrow-rotate-left"></i>
				Recover Page
			</a>
		</li>
	<?php } ?>
</ul>
<h3>Perimissions</h3>
