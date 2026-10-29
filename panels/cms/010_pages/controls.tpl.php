<?php
/**
 * Language switch, page title, preview width.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this
 */
use Systopic\Cms\Tables\Pages\Languages;
use Systopic\Cms\Pages\PreviewWidths;

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
['desktop' => $desktopWidth, 'tablet' => $tabletWidth, 'mobile' => $mobileWidth] = PreviewWidths::of(fs::$siteRoot . 'css');
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
			<?php if (\Systopic\Cms\Pages\PageAccess::mayEdit($page)) { ?>
				<button type="button" class="pageTitleEdit" data-page-title-edit title="Edit title"><i class="fa-regular fa-pen"></i></button>
			<?php } else { ?>
				<i class="fa-regular fa-lock pageTitleLock" title="Read only: you may not change this page"></i>
			<?php } ?>
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
<div class="cmsPageTools">
	<button type="button" class="cmsMobileOnly cmsMobileMore" data-cms-more aria-label="Page tools" aria-expanded="false">…</button>
	<div class="cmsPageToolsMenu">
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

<?php if ($desktopWidth) { ?>
<style>body{--cms-desktop-width:<?= (int) $desktopWidth ?>px;<?= $tabletWidth ? '--cms-tablet-width:' . (int) $tabletWidth . 'px;' : '' ?><?= $mobileWidth ? '--cms-mobile-width:' . (int) $mobileWidth . 'px;' : '' ?>}</style>
<div class="deviceSwitch" role="group" aria-label="Preview width">
	<button type="button" data-cms-device="fit" title="Fit: the width there is"><i class="fa-regular fa-arrows-left-right-to-line"></i></button>
	<button type="button" data-cms-device="desktop" title="Desktop (from <?= (int) $desktopWidth ?>px)"><i class="fa-regular fa-desktop"></i></button>
	<?php if ($tabletWidth) { ?>
		<button type="button" data-cms-device="tablet" title="Tablet (<?= (int) $tabletWidth ?>px)"><i class="fa-regular fa-tablet-screen-button"></i></button>
	<?php } ?>
	<?php if ($mobileWidth) { ?>
		<button type="button" data-cms-device="mobile" title="Mobile (<?= (int) $mobileWidth ?>px)"><i class="fa-regular fa-mobile-screen-button"></i></button>
	<?php } ?>
</div>
<?php } ?>

		<button type="button" class="cmsMobileOnly" data-cms-properties><i class="fa-regular fa-sliders"></i> Properties</button>
		<button type="button" class="cmsMobileOnly" data-cms-window="media"><i class="fa-regular fa-images"></i> Media Library</button>
	</div>
</div>
