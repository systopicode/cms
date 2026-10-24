<?php
/**
 * Status unten rechts: letzte Änderung und Veröffentlichungsstand.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Panel $this
 */
$page = $this->page;
$row = $page?->row;
if ($row === null) {
	return;
}
$now = new DateTimeImmutable();
$from = $row->startPublishing;
$to = $row->endPublishing;
if (!$row->isPublished) {
	$publishState = ['offline', 'Offline'];
} elseif ($from instanceof DateTimeImmutable && $from > $now) {
	$publishState = ['scheduled', 'Geplant ab ' . $from->format('d.m.Y H:i')];
} elseif ($to instanceof DateTimeImmutable && $to < $now) {
	$publishState = ['expired', 'Abgelaufen'];
} else {
	$publishState = ['online', 'Veröffentlicht'];
}
$modified = $row->modifiedDate;
$modifiedDate = $modified instanceof DateTimeImmutable ? $modified->format('d.m.Y H:i') : '';
$modifiedName = trim((string) ($row->modifiedUser?->fullname ?? ''));
?>
<div class="cmsStatusInfo">
	<?php if ($modifiedDate !== '') { ?>
		<span class="modified">Geändert <?= htmlspecialchars($modifiedDate, ENT_QUOTES, 'UTF-8') ?><?= $modifiedName !== '' ? ' · ' . htmlspecialchars($modifiedName, ENT_QUOTES, 'UTF-8') : '' ?></span>
	<?php } ?>
	<span class="publishState <?= $publishState[0] ?>"><?= htmlspecialchars($publishState[1], ENT_QUOTES, 'UTF-8') ?></span>
</div>
