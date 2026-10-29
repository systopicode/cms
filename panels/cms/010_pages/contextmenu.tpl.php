<?php

/**
 * The context menu of a tree entry.
 *
 * Comes from `lib/class/tree/node/contextmenu.tpl.php` and now sits where it
 * belongs: with the panel. Every line in it calls an action from
 * `actions.php` next door — `editLabel`, `duplicate`, `publish`,
 * `redirectHere`, `createAlias`, `deletePage`. This is panel markup, not
 * model.
 *
 * What else changed are the **names**. The legacy code reaches through a row
 * onto columns fifteen times over — `$this->record->node_is_trashed`,
 * `$this->record->page_is_published`,
 * `$this->record->node_original_node_id` — so every rename in the database
 * goes through the markup. Here it reads `$node->isTrashed`,
 * `$node->isPublished`, `$node->originalId`.
 *
 * @var \Systopic\Cms\Queries\PageTree\PageTreeNode      $node
 * @var \Systopic\Cms\Queries\PageTree\PageTree          $tree
 * @var \Systopic\Cms\Queries\PageTree\PageTreeNode|null $active
 */
$canAliasAfter = $canAliasChild = false;
if ($active !== null) {
    try { \Systopic\Cms\Pages\RelationRules::placement($active->contentId, (int) ($node->parent?->id ?? 0), $this->relationNode(...)); $canAliasAfter = true; } catch (\DomainException) {}
    try { \Systopic\Cms\Pages\RelationRules::placement($active->contentId, $node->id, $this->relationNode(...)); $canAliasChild = true; } catch (\DomainException) {}
}
?>
<ul class="contextmenu" data-id="<?= $node->id ?>">
	<?php if ($node->isTrashed) { ?>
		<li title="Recover Page" class="iconOnly">
			<a title="Recover Page" aria-label="Recover Page" data-confirm="All langauges, children and versions will be recoverd!"
			   data-on_click='userConfirm'
			   data-action="recoverPage"
			   data-node_id="<?= $node->id ?>">
				<i class="fa-regular fa-arrow-rotate-left"></i>
			</a>
		</li>
		<li class="seperator"></li>
		<li>
			<a href='<?= http::$siteRoot ?>?id=<?= $node->id ?>&lang=<?= $node->language ?>&showtrashed'
			   target='_blank'>
				<i class="fa-regular fa-user-hat-tie-magnifying-glass"></i>
				<span>Preview</span>
			</a>
		</li>
	<?php } else { ?>
		<li class="iconsonly">
			<ul>
				<li title="Rename" class="iconOnly">
					<a title="Rename" aria-label="Rename" data-on_click="editLabel">
						<i class="fa-regular fa-pen"></i>
					</a>
				</li>
				<li title="Duplicate" class="iconOnly">
					<a title="Duplicate" aria-label="Duplicate" data-on_click="duplicate?node_id=<?= $node->id ?>">
						<i class="fa-regular fa-copy"></i>
					</a>
				</li>
				<?php if ($node->isPublished) { ?>
					<li title="Hide" class="iconOnly">
						<a title="Hide" aria-label="Hide" data-on_click="unpublish?id=<?= $node->id ?>">
							<i class="fa-regular fa-eye-slash"></i>
						</a>
					</li>
                    <?php if ($node->hasChildren) { ?>
					<li title="Hide with children" class="iconOnly">
						<a title="Hide with children" aria-label="Hide with children"  data-on_click="unPublishAll?id=<?= $node->id ?>">
							<span class="layerEyeIcon" aria-hidden="true"><i class="fa-regular fa-layer-group"></i><i class="fa-regular fa-eye-slash"></i></span>
						</a>
					</li>
                    <?php } ?>
				<?php } else { ?>
					<li title="Publish" class="iconOnly">
						<a title="Publish" aria-label="Publish"  data-on_click="publish?id=<?= $node->id ?>">
							<i class="fa-regular fa-eye"></i>
						</a>
					</li>
                    <?php if ($node->hasChildren) { ?>
					<li title="Publish with children" class="iconOnly">
						<a title="Publish with children" aria-label="Publish with children"  data-on_click="publishAll?id=<?= $node->id ?>">
							<span class="layerEyeIcon" aria-hidden="true"><i class="fa-regular fa-layer-group"></i><i class="fa-regular fa-eye"></i></span>
						</a>
					</li>
                    <?php } ?>
				<?php } ?>
				<li title="New subpage" class="iconOnly">
					<a title="New subpage" aria-label="New subpage" data-node_id="<?= $node->id ?>" data-on_click="newChild">
						<i class="fa-regular fa-layer-plus"></i>
					</a>
				</li>
				<?php if ($node->isAlias && !$node->hasChildren) { ?>
					<li style='margin-left: auto;' title="Delete Alias" class="iconOnly">
						<a title="Delete Alias" aria-label="Delete Alias" data-confirm="Do you really want to delete this Alias Page?"
						   class='removeAlias'
						   data-on_click='userConfirm'
						   data-action="deleteAlias&node_id=<?= $node->id ?>&node_original_node_id=<?= $node->originalId ?>">
							<i class="fa-regular fa-trash"></i>
						</a>
					</li>
				<?php } ?>
				<?php if (!$node->isAlias && !$node->isTrashed) { ?>
					<li style='margin-left: auto;' title="Delete Page" class="iconOnly">
						<a title="Delete Page" aria-label="Delete Page" data-confirm="All langauges, children and versions will be removed!"
						   data-on_click='userConfirm'
						   data-action="deletePage"
						   data-node_id="<?= $node->id ?>">
							<i class="fa-regular fa-trash"></i>
						</a>
					</li>
				<?php } ?>
			</ul>
		</li>
		<li class="seperator"></li>
		<?php if ($active !== null) { ?>
			<?php if ($node->id !== $active->id) { ?>
				<li>
					<a class=" redirect"
					   data-on_click="redirectHere?node_id=<?= $active->id ?>&node_redirect_node_id=<?= $node->id ?>">
						<i class="fa-regular fa-route"></i>
						<span>"<?= $active->label ?>" redirect here</span>
					</a>
				</li>
			<?php } ?>
			<?php if ($node->redirectId) { ?>
				<li>
					<a class=" redirect" data-on_click="redirectHere?node_id=<?= $node->id ?>&node_redirect_node_id=0">
						<i class="fa-regular fa-route"></i>
						<span>Remove redirect</span>
					</a>
				</li>
			<?php } ?>
            <?php if ($canAliasAfter) { ?>
			<li>
				<a class=" alias"
				   data-on_click="createAlias?node_id=<?= $node->id ?>&node_parent_node_id=<?= $node->parent?->id ?>&node_original_node_id=<?= $active->contentId ?>">
					<i class="fa-regular fa-file-import"></i>
					<span>Alias of "<?= $active->label ?>" after this</span>
				</a>
			</li>
            <?php } if ($canAliasChild) { ?>
			<li>
				<a class=" alias"
				   data-on_click="createAlias?node_id=<?= $node->id ?>&node_parent_node_id=<?= $node->id ?>&node_original_node_id=<?= $active->contentId ?>">
					<i class="fa-regular fa-file-import"></i>
					<span>Alias as child</span>
				</a>
			</li>
            <?php } ?>
		<?php } ?>

		<li class="seperator"></li>
		<li>
			<a href='<?= http::$siteRoot ?>?id=<?= $node->id ?>&lang=<?= $node->language ?>&preview'
			   target='_blank'>
				<i class="fa-regular fa-user-hat-tie-magnifying-glass"></i>
				<span>Preview</span>
			</a>
		</li>
        <?php $live = $this->liveHref($node->id, $node->language); if ($live !== null) { ?>
        <li><a href="<?= htmlspecialchars($live, ENT_QUOTES) ?>" target="_blank" rel="noopener"><i class="fa-regular fa-arrow-up-right-from-square"></i><span>Show live</span></a></li>
        <?php } ?>
	<?php } ?>

</ul>
<?php
