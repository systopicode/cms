<?php
/**
 * The speaking paths of the page — automatic and custom.
 *
 * `printRewriteForm()` of the legacy row class lives here now: how a path
 * looks in this sidebar is a property of the sidebar, not of the row.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */

use Systopic\Cms\Tables\Rewritepaths\Model as Rewritepath;
use Systopic\Cms\Queries\RewritepathLookup\RewritepathLookup;

$page = $this->parent->page;
$paths = $page->rewritepaths;

// The site a path answers for — copied from the tree, NULL = default site.
$sites = \Systopic\System\Config\Config::current()?->sites;
$siteOf = static function (?int $siteNodeId) use ($sites): string {
	if ($sites === null) {
		return '';
	}
	if ($siteNodeId === null) {
		return $sites->default()->name;
	}
		return $sites->byRoot($siteNodeId)?->name ?? 'Unknown site';
};

$form = static function (Rewritepath $path) use ($siteOf): string {
	$id = (int) $path->id;
	$string = htmlspecialchars((string) $path->string, ENT_QUOTES);
	$checked = $path->isPrimary ? 'checked="checked"' : '';
	$html = "<div data-rewritepath_id='$id'>";
	$html .= "<div><input $checked title='Set as primary' data-on_change='sidebar/rewritePath_setPrimary' class='custom' type='radio' name='rewritepath_is_primary' id='rewritepath_is_primary_$id'>";
	$html .= "<label for='rewritepath_is_primary_$id'></label></div>";
	if ($path->autoIndex !== null) {
		$html .= "<input type='text' style='background:transparent;' readonly='readonly'  name='rewritePath' class='ajax disabled'  value='$string'/>";
	} else {
		$html .= "<input type='text' name='rewritePath' data-on_input='sidebar/rewritePath_update' value='$string'/>";
	}
	$html .= "<i data-on_click='sidebar/rewritePath_delete' class='fa-regular fa-trash' title='Remove address'></i>";
	$html .= '</div>';
	$when = static fn(?DateTimeImmutable $d): string => ($d ? \Systopic\System\Sys\LocalTime::format($d, 'd.m.Y H:i') : '—');
	$site = $siteOf($path->siteNodeId);
	$html .= "<p class='pathMeta'>";
	if ($site !== '') {
		$html .= htmlspecialchars($site) . ' · ';
	}
	$html .= "Test {$path->testcount}";
	if ($path->testcountDatetime) {
		$html .= ' · ' . $when($path->testcountDatetime);
	}
	$html .= " · Live {$path->livecount}";
	if ($path->livecountDatetime) {
		$html .= ' · ' . $when($path->livecountDatetime);
	}
	$html .= '</p>';
	return $html;
};
?>
<h3>Addresses</h3>
<ul class="rewrite">
    <li class="seperator">Automatic
		<i class="fa-regular fa-arrow-rotate-left"
		   data-on_click="sidebar/rewritePath_updateAutoPath"
		   title="Rebuild from the page name"
		   data-page_id="<?= $page->row->id ?>">
		</i>
    </li>
	<li>
	<?php
	foreach ($paths->auto as $rewritePath) {
		echo $form($rewritePath);
		if (empty($rewritePath->barrierId)) {
			$barrierLink = ' · full path';
		} else {
			$owner = RewritepathLookup::pageOf($this->parent->scope, (int) $rewritePath->barrierId);
			$barrierLink = $owner === null
				? ' · blocked by a removed page'
				: " · blocked by <a class='ajax' href='./?id=" . (int) $owner->relationId('node') . '&lang=' . $owner->languageIso . "'>"
					. htmlspecialchars((string) $owner->label) . '</a>';
		}
		?>
		<p class="pathMeta">Level <?= (int) $rewritePath->autoIndex . $barrierLink ?></p>
	<?php } ?>
	</li>
<li  class="seperator">Custom
	<i class="fa-regular fa-plus"
	   data-on_click="sidebar/rewritePath_new"
	   title="Add an address"
	   data-page_id="<?= $page->row->id ?>">
	</i>
</li>
<?php
foreach ($paths->custom as $rewritePath) {
	echo '<li>';
	echo $form($rewritePath);
	echo '</li>';
}
?>
</ul>
