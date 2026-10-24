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
?>
<ul class="contextmenu" data-id="<?= $node->id ?>">
	<?php if ($node->isTrashed) { ?>
		<li title="Recover Page" class="iconOnly">
			<a data-confirm="All langauges, children and versions will be recoverd!"
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
				<i class="fa-regular fa-eye"></i>
				<span>Preview</span>
			</a>
		</li>
	<?php } else { ?>
		<li class="iconsonly">
			<ul>
				<li title="Rename" class="iconOnly">
					<a data-on_click="editLabel">
						<i class="fa-regular fa-pen"></i>
					</a>
				</li>
				<li title="Duplicate" class="iconOnly">
					<a data-on_click="duplicate?node_id=<?= $node->id ?>">
						<i class="fa-regular fa-copy"></i>
					</a>
				</li>
				<?php if ($node->isPublished) { ?>
					<li title="Hide" class="iconOnly">
						<a data-on_click="unpublish?id=<?= $node->id ?>">
							<i class="fa-regular fa-eye-slash"></i>
						</a>
					</li>
					<li title="Hide with children" class="iconOnly">
						<a  data-on_click="unPublishAll?id=<?= $node->id ?>">
							<i style='font-size: 14px;  transform: translateY(-3px);' class="fa-regular fa-eye-slash"></i>
							<i style='font-size: 12px; transform: translateX(-10px) translateY(2px);' class="fa-regular fa-eye-slash"></i>
						</a>
					</li>
				<?php } else { ?>
					<li title="Publish" class="iconOnly">
						<a  data-on_click="publish?id=<?= $node->id ?>">
							<i class="fa-regular fa-eye"></i>
						</a>
					</li>
					<li title="Publish with children" class="iconOnly">
						<a  data-on_click="publishAll?id=<?= $node->id ?>">
							<i style='font-size: 14px;  transform: translateY(-3px);' class="fa-regular fa-eye"></i>
							<i style='font-size: 12px; transform: translateX(-10px) translateY(2px);' class="fa-regular fa-eye"></i>
						</a>
					</li>
				<?php } ?>
				<li title="New subpage" class="iconOnly">
					<a data-node_id="<?= $node->id ?>" data-on_click="newChild">
						<i class="fa-regular fa-diagram-subtask"></i>
					</a>
				</li>
				<?php if ($node->isAlias && !$node->hasChildren) { ?>
					<li style='margin-left: auto;' title="Delete Alias" class="iconOnly">
						<a data-confirm="Do you really want to delete this Alias Page?"
						   class='alias'
						   data-on_click='userConfirm'
						   data-action="deleteAlias&node_id=<?= $node->id ?>&node_original_node_id=<?= $node->originalId ?>">
							<i class="fa-regular fa-trash"></i>
						</a>
					</li>
				<?php } ?>
				<?php if (!$node->isAlias && !$node->isTrashed) { ?>
					<li style='margin-left: auto;' title="Delete Page" class="iconOnly">
						<a data-confirm="All langauges, children and versions will be removed!"
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
						<i class="fa-regular fa-arrow-turn-down-right"></i>
						<span>"<?= $active->label ?>" redirect here</span>
					</a>
				</li>
			<?php } ?>
			<?php if ($node->redirectId) { ?>
				<li>
					<a class=" redirect" data-on_click="redirectHere?node_id=<?= $node->id ?>&node_redirect_node_id=0">
						<i class="fa-regular fa-arrow-turn-down-right"></i>
						<span>Remove redirect</span>
					</a>
				</li>
			<?php } ?>
			<li>
				<a class=" alias"
				   data-on_click="createAlias?node_id=<?= $node->id ?>&node_parent_node_id=<?= $node->parent?->id ?>&node_original_node_id=<?= $active->contentId ?>">
					<i class="fa-regular fa-file-import"></i>
					<span>Alias of "<?= $active->label ?>" after this</span>
				</a>
			</li>
			<li>
				<a class=" alias"
				   data-on_click="createAlias?node_id=<?= $node->id ?>&node_parent_node_id=<?= $node->id ?>&node_original_node_id=<?= $active->contentId ?>">
					<i class="fa-regular fa-file-import"></i>
					<span>Alias as child</span>
				</a>
			</li>
		<?php } ?>
		<?php if ($node->isPublished) { ?>
			<li class="seperator"></li>

			<li>
				<a onclick="navigator.clipboard.writeText('?id=<?= $node->id ?>&lang=<?= $node->language ?>')">
					<i class="fa-regular fa-link"></i>
					<span>Copy ID link</span>
				</a>
			</li>
		<?php } ?>
		<li class="seperator"></li>
		<li>
			<a href='<?= http::$siteRoot ?>?id=<?= $node->id ?>&lang=<?= $node->language ?>&preview'
			   target='_blank'>
				<i class="fa-regular fa-eye"></i>
				<span>Preview</span>
			</a>
		</li>
	<?php } ?>

</ul>
<?php
