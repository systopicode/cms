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
 * Only what the user may do (`PageAccess`): change the page itself, place
 * something beside it (under its parent), or change the page on screen.
 * The actions ask again.
 *
 * @var \Systopic\Cms\Queries\PageTree\PageTreeNode      $node
 * @var \Systopic\Cms\Queries\PageTree\PageTree          $tree
 * @var \Systopic\Cms\Queries\PageTree\PageTreeNode|null $active
 */

use Systopic\Cms\Pages\PageAccess;

$may = PageAccess::mayWrite($node->node);
$mayBeside = PageAccess::mayWrite($node->node->parentNode);
$mayActive = $active !== null && PageAccess::mayWrite($active->node);
$canAliasAfter = $canAliasChild = false;
if ($active !== null) {
    try { \Systopic\Cms\Pages\RelationRules::placement($active->contentId, (int) ($node->parent?->id ?? 0), $this->relationNode(...)); $canAliasAfter = true; } catch (\DomainException) {}
    try { \Systopic\Cms\Pages\RelationRules::placement($active->contentId, $node->id, $this->relationNode(...)); $canAliasChild = true; } catch (\DomainException) {}
}
?>
<ul class="contextmenu" data-id="<?= $node->id ?>">
	<?php if ($node->isTrashed) { ?>
		<?php if ($may) { ?>
		<li title="Recover Page" class="iconOnly">
			<button type="button" data-cms-action title="Recover Page" aria-label="Recover Page" data-confirm="All langauges, children and versions will be recoverd!"
			   data-on_click='userConfirm'
			   data-action="recoverPage"
			   data-node_id="<?= $node->id ?>">
				<i class="cmsIcon cmsIcon-arrow-rotate-left"></i>
			</button>
		</li>
		<li class="seperator"></li>
		<?php } ?>
		<li>
			<a href='<?= http::$siteRoot ?>?id=<?= $node->id ?>&lang=<?= $node->language ?>&showtrashed'
			   target='_blank'>
				<i class="cmsIcon cmsIcon-user-hat-tie-magnifying-glass"></i>
				<span>Preview</span>
			</a>
		</li>
	<?php } else { ?>
		<?php if ($may || $mayBeside) { ?>
		<li class="iconsonly">
			<ul>
				<?php if ($may) { ?>
				<li title="Rename" class="iconOnly">
					<button type="button" data-cms-action title="Rename" aria-label="Rename" data-on_click="editLabel">
						<i class="cmsIcon cmsIcon-pen"></i>
					</button>
				</li>
				<?php } ?>
				<?php if ($mayBeside) { ?>
				<li title="Duplicate" class="iconOnly">
					<button type="button" data-cms-action title="Duplicate" aria-label="Duplicate" data-on_click="duplicate?node_id=<?= $node->id ?>">
						<i class="cmsIcon cmsIcon-copy"></i>
					</button>
				</li>
				<?php } ?>
				<?php if ($may && $node->isPublished) { ?>
					<li title="Hide" class="iconOnly">
						<button type="button" data-cms-action title="Hide" aria-label="Hide" data-on_click="unpublish?id=<?= $node->id ?>">
							<i class="cmsIcon cmsIcon-eye-slash"></i>
						</button>
					</li>
                    <?php if ($node->hasChildren) { ?>
					<li title="Hide with children" class="iconOnly">
						<button type="button" data-cms-action title="Hide with children" aria-label="Hide with children"  data-on_click="userConfirm" data-action="unPublishAll?id=<?= (int) $node->id ?>" data-confirm="<?= htmlspecialchars($this->branchConfirmation((int) $node->id, 'unPublishAll'), ENT_QUOTES) ?>">
							<span class="layerEyeIcon" aria-hidden="true"><i class="cmsIcon cmsIcon-layer-group"></i><i class="cmsIcon cmsIcon-eye-slash"></i></span>
						</button>
					</li>
                    <?php } ?>
				<?php } elseif ($may) { ?>
					<li title="Publish" class="iconOnly">
						<button type="button" data-cms-action title="Publish" aria-label="Publish"  data-on_click="publish?id=<?= $node->id ?>">
							<i class="cmsIcon cmsIcon-eye"></i>
						</button>
					</li>
                    <?php if ($node->hasChildren) { ?>
					<li title="Publish with children" class="iconOnly">
						<button type="button" data-cms-action title="Publish with children" aria-label="Publish with children"  data-on_click="userConfirm" data-action="publishAll?id=<?= (int) $node->id ?>" data-confirm="<?= htmlspecialchars($this->branchConfirmation((int) $node->id, 'publishAll'), ENT_QUOTES) ?>">
							<span class="layerEyeIcon" aria-hidden="true"><i class="cmsIcon cmsIcon-layer-group"></i><i class="cmsIcon cmsIcon-eye"></i></span>
						</button>
					</li>
                    <?php } ?>
				<?php } ?>
				<?php if ($may) { ?>
				<li title="New subpage" class="iconOnly">
					<button type="button" data-cms-action title="New subpage" aria-label="New subpage" data-node_id="<?= $node->id ?>" data-on_click="newChild">
						<i class="cmsIcon cmsIcon-layer-plus"></i>
					</button>
				</li>
				<?php } ?>
				<?php if ($may && $node->isAlias && !$node->hasChildren) { ?>
					<li style='margin-left: auto;' title="Delete Alias" class="iconOnly">
						<button type="button" data-cms-action title="Delete Alias" aria-label="Delete Alias" data-confirm="Do you really want to delete this Alias Page?"
						   class='removeAlias'
						   data-on_click='userConfirm'
						   data-action="deleteAlias&node_id=<?= $node->id ?>&node_original_node_id=<?= $node->originalId ?>">
							<i class="cmsIcon cmsIcon-trash"></i>
						</button>
					</li>
				<?php } ?>
				<?php if ($may && !$node->isAlias && !$node->isTrashed) { ?>
					<li style='margin-left: auto;' title="Delete Page" class="iconOnly">
						<button type="button" data-cms-action title="Delete Page" aria-label="Delete Page" data-confirm="<?= htmlspecialchars($this->branchConfirmation((int) $node->id, 'deletePage'), ENT_QUOTES) ?>"
						   data-on_click='userConfirm'
						   data-action="deletePage"
						   data-node_id="<?= $node->id ?>">
							<i class="cmsIcon cmsIcon-trash"></i>
						</button>
					</li>
				<?php } ?>
			</ul>
		</li>
		<li class="seperator"></li>
		<?php } ?>
		<?php if ($active !== null) { ?>
			<?php if ($mayActive && $node->id !== $active->id) { ?>
				<li>
					<button type="button" data-cms-action class=" redirect"
					   data-on_click="redirectHere?node_id=<?= $active->id ?>&node_redirect_node_id=<?= $node->id ?>">
						<i class="cmsIcon cmsIcon-route"></i>
						<span>"<?= $active->label ?>" redirect here</span>
					</button>
				</li>
			<?php } ?>
			<?php if ($may && $node->redirectId) { ?>
				<li>
					<button type="button" data-cms-action class=" redirect" data-on_click="redirectHere?node_id=<?= $node->id ?>&node_redirect_node_id=0">
						<i class="cmsIcon cmsIcon-route"></i>
						<span>Remove redirect</span>
					</button>
				</li>
			<?php } ?>
            <?php if ($mayBeside && $canAliasAfter) { ?>
			<li>
				<button type="button" data-cms-action class=" alias"
				   data-on_click="createAlias?node_id=<?= $node->id ?>&node_parent_node_id=<?= $node->parent?->id ?>&node_original_node_id=<?= $active->contentId ?>">
					<i class="cmsIcon cmsIcon-file-import"></i>
					<span>Alias of "<?= $active->label ?>" after this</span>
				</button>
			</li>
            <?php } if ($may && $canAliasChild) { ?>
			<li>
				<button type="button" data-cms-action class=" alias"
				   data-on_click="createAlias?node_id=<?= $node->id ?>&node_parent_node_id=<?= $node->id ?>&node_original_node_id=<?= $active->contentId ?>">
					<i class="cmsIcon cmsIcon-file-import"></i>
					<span>Alias as child</span>
				</button>
			</li>
            <?php } ?>
		<?php } ?>

		<li class="seperator"></li>
		<li>
			<a href='<?= http::$siteRoot ?>?id=<?= $node->id ?>&lang=<?= $node->language ?>&preview'
			   target='_blank'>
				<i class="cmsIcon cmsIcon-user-hat-tie-magnifying-glass"></i>
				<span>Preview</span>
			</a>
		</li>
        <?php $live = $this->liveHref($node->id, $node->language); if ($live !== null) { ?>
        <li><a class="cmsExternalLink" href="<?= htmlspecialchars($live, ENT_QUOTES) ?>" target="_blank" rel="noopener"><span>Show live</span><i class="cmsIcon cmsIcon-arrow-up-right-from-square" aria-hidden="true"></i></a></li>
        <?php } ?>
	<?php } ?>

</ul>
<?php
