<?php
/**
 * Language switch, page title, preview width.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this
 */
use Systopic\Cms\Tables\Pages\Languages;

if (($this->state->id ?? null) === null) {
	echo '<!-- controls: no page selected -->';
	return;
}
$page = $this->page;
$crumbs = [];
$walker = $page?->parent;
while ($walker && count($crumbs) < 10) {
	array_unshift($crumbs, $walker);
	$walker = $walker->parent;
}
$title = $page?->label->plain ?? '';
$breakpoints = [];
$responsiveFile = fs::$siteRoot . 'css/x_responsive.css';
if (fs::isFile($responsiveFile) && preg_match_all('/max-width:\s*(\d+)px/', (string) fs::file_get_contents($responsiveFile), $found)) {
	$breakpoints = array_values(array_unique(array_map('intval', $found[1])));
	sort($breakpoints);
}
$mobileWidth = null;
$tabletWidth = null;
foreach ($breakpoints as $width) {
	if ($width <= 640 && $mobileWidth === null) {
		$mobileWidth = $width;
	} elseif ($width > 640 && $width <= 1100 && $tabletWidth === null) {
		$tabletWidth = $width;
	}
}
?>
<div class="pageHead">
	<?php if ($page) { ?>
		<nav class="crumbs"<?= $crumbs === [] ? ' aria-hidden="true"' : '' ?>>
			<?php foreach ($crumbs as $crumb) { ?>
				<a class="ajax" href="./?id=<?= (int) $crumb->id ?>"><?= $crumb->label->plain ?></a>
				<i class="fa-regular fa-chevron-right"></i>
			<?php } ?>
		</nav>
		<div class="pageTitleRow">
			<h1 class="pageTitle" contenteditable="false" data-on_blur="updateLabel"><?= $title !== '' ? $title : '- - - -' ?></h1>
			<button type="button" class="pageTitleEdit" data-page-title-edit title="Edit title"><i class="fa-regular fa-pen"></i></button>
		</div>
	<?php } else { ?>
		<h1>Pages</h1>
	<?php } ?>
</div>
<?php
$flag = static function (array $language) {
	$code = htmlspecialchars(strtolower((string) ($language['code'] ?? '')), ENT_QUOTES);
	return '<img alt="" src="https://flagcdn.com/' . $code . '.svg">';
};
$current = Languages::activated()[$this->state->lang] ?? null;
?>
<div class="leftAlign">
	<div class="splitApply langSwitch">
		<button type="button" class="splitApplyMenu" aria-label="<?= htmlspecialchars((string) ($current['label'] ?? 'Language'), ENT_QUOTES) ?>">
			<?php if ($current) { echo $flag($current); } ?>
			<span><?= strtoupper((string) $this->state->lang) ?></span>
			<i class="fa-regular fa-chevron-down"></i>
		</button>
		<div class="splitApplyList langMenu" hidden>
			<?php foreach (Languages::activated() as $languageIso => $languageSetting) { ?>
				<a class="ajax<?= $this->state->lang === $languageIso ? ' is-current' : '' ?>" href="./?lang=<?= htmlspecialchars((string) $languageIso, ENT_QUOTES) ?>">
					<?= $flag($languageSetting) ?>
					<span><?= htmlspecialchars((string) $languageSetting['label'], ENT_QUOTES) ?></span>
				</a>
			<?php } ?>
		</div>
	</div>
</div>

<div class="deviceSwitch" role="group" aria-label="Preview width">
	<button type="button" data-cms-device="desktop" title="Desktop"><i class="fa-regular fa-desktop"></i></button>
	<?php if ($tabletWidth) { ?>
		<button type="button" data-cms-device="tablet" data-cms-width="<?= (int) $tabletWidth ?>" title="Tablet (<?= (int) $tabletWidth ?>px)"><i class="fa-regular fa-tablet-screen-button"></i></button>
	<?php } ?>
	<?php if ($mobileWidth) { ?>
		<button type="button" data-cms-device="mobile" data-cms-width="<?= (int) $mobileWidth ?>" title="Mobile (<?= (int) $mobileWidth ?>px)"><i class="fa-regular fa-mobile-screen-button"></i></button>
	<?php } ?>
</div>
