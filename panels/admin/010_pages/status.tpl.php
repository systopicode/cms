<?php
/**
 * Status unten rechts: letzte Änderung und Veröffentlichungsstand.
 *
 * @var \Systopic\Cms\Panels\Root\Admin\Pages\Panel $this
 */
$page = $this->page;
$row = $page?->row;
if ($row === null) {
	return;
}
$publishing = \Systopic\Cms\Pages\PublishingState::of(true, (bool) $page->node->isTrashed, (bool) $row->isPublished, $row->startPublishing, $row->endPublishing);
$modified = $row->modifiedDate;
$modifiedDate = $modified instanceof DateTimeImmutable ? \Systopic\System\Sys\LocalTime::format($modified, \Systopic\System\Sys\LocalTime::DATETIME) : '';
$modifiedName = trim((string) ($row->modifiedUser?->fullname ?? ''));
?>
<div class="cmsStatusInfo">
	<?php if ($modifiedDate !== '') { ?>
		<span class="modified">Modified <?= htmlspecialchars($modifiedDate, ENT_QUOTES, 'UTF-8') ?><?= $modifiedName !== '' ? ' · ' . htmlspecialchars($modifiedName, ENT_QUOTES, 'UTF-8') : '' ?></span>
	<?php } ?>
	<span title="<?= htmlspecialchars($publishing['reason'], ENT_QUOTES, 'UTF-8') ?>" class="publishState <?= $publishing['key'] ?>"><?= htmlspecialchars($publishing['label'], ENT_QUOTES, 'UTF-8') ?></span>
</div>
