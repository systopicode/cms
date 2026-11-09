<?php
/**
 * admin/pages — the editor of a site, shown in main instead of a page preview.
 *
 * Three ways in:
 *   ?id=<node>        a node of type 'site' clicked in the tree
 *   ?site=default     the globe in the tree navi: the default site (no node)
 *   ?site=new         "Site" in the add menu: a name field, nothing written yet
 *
 * A site is a node of type 'site' plus a row in cms_sites (name = domain,
 * meta as JSON); the default site has no node. Where a site answers is set in
 * config/<Instanz>.config.json, not here; its alias domains (301 to the
 * site) are.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Panel $this
 * @var \Systopic\Cms\Tables\Sites\Model|null    $site  the site on screen, null for 'new' or a missing default
 */

use Systopic\Cms\Pages\Sites\SiteRegistry;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<section class="cms siteEditor">
	<?= message::flush() ?>
	<?php if ($site === null) { ?>
        <h1><i class="cmsIcon cmsIcon-globe" aria-hidden="true"></i> New site</h1>
        <?php if (($this->state->editSite ?? null) === 'default') { ?><p>No default site yet. The first site becomes the default site for top-level pages.</p><?php } ?>
        <p>Enter the site's domain, including its extension.</p>
        <details><summary>Technical details</summary><p>A site record and template folders are created. Additional sites also get a site node in the page tree.</p></details>
        <label class="_4_8" data-on_change="createSite">Domain (Enter)
            <input name="siteName" placeholder="example.com" autocomplete="off" autofocus>
        </label>
	<?php } else {
		$nodeId = $site->relationId('node');
		// a site that came in with a backup has its row but not its folder
		$hasFolder = \fs::isDir((string) \fs::$projectRoot . 'sites/' . $site->name);
		// ... and no link on this machine: projects/<site> → this project
		$missingLinks = SiteRegistry::missingLinks($site->name);
		?>
		<div data-site_id="<?= (int) $site->id ?>">
			<h1>
				<i class="cmsIcon cmsIcon-globe"></i> <?= $e($site->name) ?>
				<small><?= $nodeId === NULL ? 'Default site' : 'Site node ' . $nodeId ?></small>
				<?php if (($url = $this->siteUrl($site)) !== NULL) { ?>
					<a class="right" href="<?= $e($url) ?>" target="_blank" rel="noopener" title="<?= $e($url) ?> — open in new tab">
						<i class="cmsIcon cmsIcon-arrow-up-right-from-square"></i>
					</a>
				<?php } ?>
			</h1>
            <p><?= $this->sitePageCount($site) ?> pages directly below this site.</p>
			<?php if ($missingLinks) { ?>
				<div class="cms message is-stay siteLinkMissing"><div class="warning">
					<p>Site link not created on this machine: <code><?= $e(implode('</code>, <code>', $missingLinks)) ?></code> → this project<?= $hasFolder ? '' : ', and the folder sites/' . $e($site->name) . '/' ?>.</p>
					<p><button type="button" data-cms-action class="button submit" data-on_click="createSiteLink" data-site_id="<?= (int) $site->id ?>">
						<i class="cmsIcon cmsIcon-link"></i> <?= $hasFolder ? 'Create site link' : 'Create site link and folder' ?>
					</button></p>
				</div></div>
			<?php } ?>
            <details><summary>Technical details</summary>
                <p>Folder <code>sites/<?= $e($site->name) ?>/</code>: <?= $hasFolder ? 'present' : '<b>missing</b>' ?>.</p>
                <p>Where a site answers on a stage (DEV, STAGING, RC) follows from its name and the pattern of the installation (Settings, Environment: Site addresses). Site aliases below redirect to the site's domain.</p>
            </details>
			<label class="_8_8" data-on_change="updateSiteMeta">Site metadata (JSON: title, appName, author, description, keywords, robots, titleSeparator, shareImage)
				<textarea name="meta" rows="14" spellcheck="false" style="font-family:monospace"><?= $e(json_encode($site->metaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></textarea>
			</label>
            <label class="_4_8" data-on_change="updateSiteAliases">Site aliases (one domain per line)
                <textarea name="aliases" rows="4" spellcheck="false" placeholder="example.org"><?= $e(implode("\n", $site->aliasList)) ?></textarea>
            </label>
            <div class="siteRename" data-current-name="<?= $e($site->name) ?>">
                <label class="_4_8">New domain<input name="siteName" value="<?= $e($site->name) ?>" autocomplete="off"></label>
                <button type="button" class="button" data-on_click="reviewSiteRename">Rename site…</button>
                <p class="hint">Renaming can change the site directory and local links. The review lists the affected locations.</p>
            </div>
			<?php if ((!$hasFolder && !$missingLinks) || $nodeId !== NULL) { ?>
				<p>
				<?php if (!$hasFolder && !$missingLinks) { ?>
					<button type="button" data-cms-action class="button submit" data-on_click="createSiteFolder" data-site_id="<?= (int) $site->id ?>"
					   title="sites/<?= $e($site->name) ?>/templates/{document,components,pages,layout}/ create">
						<i class="cmsIcon cmsIcon-folder-plus"></i> Create site folder
					</button>
				<?php } ?>
				<?php if ($nodeId !== NULL) { ?>
					<button type="button" data-cms-action class="button submit" data-on_click="userConfirm" data-action="removeSite"
					   data-site_id="<?= (int) $site->id ?>"
					   data-confirm="Delete site <?= $e($site->name) ?>? The site must have no pages. The folder sites/<?= $e($site->name) ?>/ is kept.">
						<i class="cmsIcon cmsIcon-trash"></i> Delete site
					</button>
				<?php } ?>
				</p>
			<?php } ?>
		</div>
	<?php } ?>
</section>
