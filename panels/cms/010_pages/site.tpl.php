<?php
/**
 * cms/pages — the editor of a site, shown in main instead of a page preview.
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
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this
 * @var \Systopic\Cms\Tables\Sites\Model|null    $site  the site on screen, null for 'new' or a missing default
 */

use Systopic\Cms\Pages\Sites\SiteRegistry;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<section class="cms siteEditor">
	<?= message::flush() ?>
	<?php if ($site === null) { ?>
		<h1><i class="fa-regular fa-globe"></i> Neue Site</h1>
		<?php if (($this->state->editSite ?? NULL) === 'default') { ?>
			<p>Es gibt noch keine Default-Site. Die erste angelegte Site wird zur Default-Site (ohne Knoten, ihre Seiten sind die obersten Seiten).</p>
		<?php } ?>
		<p>
			Name ist die Domain mit TLD, z. B. <code>beautiful-technology.de</code>. Angelegt werden ein Knoten vom Typ
			<b>site</b> am Ende der obersten Ebene, die Zeile in <code>cms_sites</code> und der Ordner
			<code>sites/&lt;domain&gt;/templates/{document,components,pages,layout}/</code>.
		</p>
		<label class="_4_8" data-on_change="createSite">Domain (Enter)
			<input name="siteName" placeholder="beispiel.de" autocomplete="off" autofocus>
		</label>
	<?php } else {
		$nodeId = $site->relationId('node');
		// a site that came in with a backup has its row but not its folder
		$hasFolder = \fs::isDir((string) \fs::$projectRoot . 'sites/' . $site->name);
		// ... and no rules in the instance files of the other machines
		$missingRules = SiteRegistry::filesWithoutHostRules($site->name);
		?>
		<div data-site_id="<?= (int) $site->id ?>">
			<h1>
				<i class="fa-regular fa-globe"></i> <?= $e($site->name) ?>
				<small><?= $nodeId === NULL ? 'Default-Site' : 'Site-Knoten ' . $nodeId ?></small>
				<?php if (($url = $this->siteUrl($site)) !== NULL) { ?>
					<a class="right" href="<?= $e($url) ?>" target="_blank" rel="noopener" title="<?= $e($url) ?> in neuem Fenster öffnen">
						<i class="fa-regular fa-arrow-up-right-from-square"></i>
					</a>
				<?php } ?>
			</h1>
			<p>
				<?= $this->sitePageCount($site) ?> Seiten direkt darunter ·
				Ordner <code>sites/<?= $e($site->name) ?>/</code>
				<?= $hasFolder ? 'vorhanden' : '<b>fehlt</b>' ?>
				<?php if ($nodeId === NULL) { ?>· die obersten Seiten im Baum gehören zu dieser Site<?php } ?>
			</p>
			<label class="_8_8" data-on_change="updateSiteMeta">meta (JSON: title, appName, author, description, keywords, robots, titleSeparator, shareImage)
				<textarea name="meta" rows="14" spellcheck="false" style="font-family:monospace"><?= $e(json_encode($site->metaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></textarea>
			</label>
			<label class="_4_8" data-on_change="updateSiteAliases">Alias-Domains (eine pro Zeile) — leiten mit 301 auf <?= $e($site->name) ?> um. Hosts einer Stage (RC, DEV) stehen in config/&lt;Instanz&gt;.config.json, nicht hier.
				<textarea name="aliases" rows="4" spellcheck="false" style="font-family:monospace" placeholder="beispiel.com"><?= $e(implode("\n", $site->aliasList)) ?></textarea>
			</label>
			<label class="_4_8" data-on_change="renameSite">Umbenennen (Domain mit TLD, Enter) — Datenbank, Ordner sites/&lt;domain&gt;/ und Links dieser Maschine
				<input name="siteName" value="<?= $e($site->name) ?>" autocomplete="off">
			</label>
			<?php if (!$hasFolder || $missingRules || $nodeId !== NULL) { ?>
				<p>
				<?php if (!$hasFolder) { ?>
					<a class="button submit" data-on_click="createSiteFolder" data-site_id="<?= (int) $site->id ?>"
					   title="sites/<?= $e($site->name) ?>/templates/{document,components,pages,layout}/ anlegen">
						<i class="fa-regular fa-folder-plus"></i> Site-Ordner erstellen
					</a>
				<?php } ?>
				<?php if ($missingRules) { ?>
					<a class="button submit" data-on_click="addSiteHostRules" data-site_id="<?= (int) $site->id ?>"
					   title="Keine Regel für <?= $e($site->name) ?> in <?= $e(implode(', ', $missingRules)) ?> — eintragen als Kopie der Pfad-Regel einer anderen Site">
						<i class="fa-regular fa-route"></i> Host-Regel eintragen
					</a>
				<?php } ?>
				<?php if ($nodeId !== NULL) { ?>
					<a class="button submit" data-on_click="userConfirm" data-action="removeSite"
					   data-site_id="<?= (int) $site->id ?>"
					   data-confirm="Site <?= $e($site->name) ?> löschen? Nur möglich, wenn keine Seiten mehr darunter hängen. Der Ordner sites/<?= $e($site->name) ?>/ bleibt bestehen.">
						<i class="fa-regular fa-trash"></i> Site löschen
					</a>
				<?php } ?>
				</p>
			<?php } ?>
		</div>
	<?php } ?>
</section>
