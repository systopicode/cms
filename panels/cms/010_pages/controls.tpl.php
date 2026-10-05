<?php
/**
 * Language switch and the sidebar toggles.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this
 */
use Systopic\Cms\Tables\Pages\Languages;

if (($this->state->id ?? null) === null) {
	echo '<!-- controls: no page selected -->';
	return;
}
?>
<div class="leftAlign">
    <ul>
		<?php 
		foreach (Languages::activated() as $languageIso => $languageSetting) { 
			?>
			<li <?php if ($this->state->lang === $languageIso) { ?> class="selected" <?php } ?>>
				<a class="ajax" title="Switch to <?= $languageSetting['label'] ?>" href="./?lang=<?= $languageIso ?>">
					<?php if (fs::fileExists(fs::$sysRoot . "public/img/system/flags/{$languageSetting['code']}.svg")) {
						?>
						<img  src="<?= http::$sysRoot ?>public/img/system/flags/<?= $languageSetting["code"] ?>.svg">
						<?php } else {
						?>
						<img alt='<?= $languageSetting["code"]; ?>' src="https://flagpedia.net/data/flags/w580/<?= $languageSetting["code"]; ?>.webp">
					<?php }
					?>
				</a>
			</li>
		<?php } ?>
    </ul>
</div>

<?php
if (count(Languages::activated())) {
	$this->withChild('sidebar', 'main', function ($panel) {
		?>
		<div class="rightAlign">
			<ul class="sidebarControls">
				<?php
				foreach ($panel->templates AS $templateName => $template) {
					$inArray = in_array($templateName, $panel->state->activeSidebarTemplates);
					$active = $inArray ? ' selected ' : '';
					$action = $inArray ? 'removeFromSidebar' : 'addToSidebar';
					$title = $inArray ? 'remove from Sidebar' : 'add to Sidebar';
					echo "<li><a class='put ajax $active' href='sidebar/$action' data-template='$templateName'>$template->label</a></li>";
				}
				?>
			</ul>
		</div>
		<?php
	});
}
