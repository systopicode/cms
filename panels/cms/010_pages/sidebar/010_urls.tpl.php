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
	return $sites->byRoot($siteNodeId)?->name ?? "Knoten $siteNodeId (keine Site)";
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
	$html .= "<i data-on_click='sidebar/rewritePath_delete' class='fal fa-trash right' ></i>";
	$html .= '</div>';
	$when = static fn(?DateTimeImmutable $d): string => $d?->format('Y-m-d H:i:s') ?? '';
	$site = $siteOf($path->siteNodeId);
	$html .= "<div class='group'><div>";
	if ($site !== '') {
		$html .= 'Site: <b>' . htmlspecialchars($site) . '</b><br>';
	}
	$html .= "Test: <b>{$path->testcount}</b> " . $when($path->testcountDatetime);
	$html .= "<br>Live: <b>{$path->livecount}</b> " . $when($path->livecountDatetime) . '</div></div>';
	return $html;
};
?>
<h3>URL's</h3>
<ul class="rewrite">
    <li class="seperator">Auto
		<i class="fal fa-undo right"
		   data-on_click="sidebar/rewritePath_updateAutoPath"
		   title='Rebuild Auto URL'
		   data-page_id="<?= $page->row->id ?>">
		</i>
    </li>
	<li>
	<?php
	foreach ($paths->auto as $rewritePath) {
		echo $form($rewritePath);
		if (empty($rewritePath->barrierId)) {
			$barrierLink = ' - ideal URL';
		} else {
			$owner = RewritepathLookup::pageOf($this->parent->scope, (int) $rewritePath->barrierId);
			$barrierLink = $owner === null
				? '</label><br/><label>Problem Page removed.'
				: "</label><br/><label><a class='ajax' href='./?id=" . (int) $owner->relationId('node') . '&lang=' . $owner->languageIso . "'>Problem Page: "
					. htmlspecialchars((string) $owner->label) . '</a>';
		}
		?>
		<div class="group">creation Level: <?= $rewritePath->autoIndex . $barrierLink ?></div>
	<?php } ?>
	</li>
<li  class="seperator">Custom
	<i class="fa-regular fa-plus right"
	   data-on_click="sidebar/rewritePath_new"
	   title='Create New'
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
