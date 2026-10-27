<?php

/**
 * Noren week table: all seven days, the holidays row, notes and closures
 * (SPEC.md §3.8). Unstyled, only noren-* classes, every value escaped. A
 * site overrides it with site/snippets/noren/table.php.
 *
 * Variables (all optional):
 *   $schedule  \Sayamaapps\Noren\Schedule (default: site()->norenSchedule($area))
 *   $area      area key, used only for the default schedule
 *   $now       DateTimeImmutable, default now in the schedule's timezone
 */

if (!\Sayamaapps\Noren\Options::active(kirby())) {
	return;
}
\Sayamaapps\Noren\Options::applyLanguage(kirby());
$settings = \Sayamaapps\Noren\Options::settings(kirby());

$area ??= null;
$now ??= null;
$schedule ??= site()->norenSchedule($area);
if (!$schedule || !$schedule->maintained()) {
	return;
}
// Holidays, notes and the "today" marker change with the clock: no page cache.
kirby()->response()->cache(false);

$table = \Sayamaapps\Noren\Presenter::table($schedule, $now, [
	'holidayRow'      => $settings['holidayRow'],
	'holidayNote'     => $settings['holidayNote'],
	'holidayNoteDays' => $settings['holidayNoteDays'],
	'leadDays'        => $settings['leadDays'],
]);
$closed = esc(\Sayamaapps\Noren\I18n::t('table.closed'));
?>
<div class="noren-table">
<?php foreach ($table['days'] as $day) : ?>
  <div class="noren-row" data-today="<?= $day['today'] ? 'true' : 'false' ?>"><div class="noren-day"><?= esc($day['name']) ?>
</div><div class="noren-hours"><?php
	foreach ($day['hours'] as $hours) : ?><?= esc($hours) ?><br><?php
	endforeach ?><?php
	if ($day['closed']) : ?><span class="noren-closed"><?= $closed ?></span><?php
	endif ?><?php
	if ($day['note'] !== null && $day['note'] !== '') : ?><span class="noren-note"><?= esc($day['note']) ?></span><?php
	endif ?></div></div>
<?php endforeach ?>
<?php $holidays = $table['holidays']; if ($holidays !== null) : ?>
  <div class="noren-row noren-row--holidays" data-today="<?= $holidays['today'] ? 'true' : 'false' ?>"><div class="noren-day"><?= esc($holidays['label']) ?>
</div><?php
?><div class="noren-hours"><span class="noren-closed"><?= esc($holidays['text']) ?></span></div></div>
<?php endif ?>
</div>
<?php foreach ($table['notes'] as $note) :
	$text = esc($note['text']);
	$extra = $note['note'] !== null && $note['note'] !== '' ? esc($note['note']) : null;
	if ($note['type'] === 'closure') : ?>
<p class="noren-closure"><strong><?= $text ?></strong><?php if ($extra !== null) : ?><br><?= $extra ?><?php endif ?></p>
    <?php elseif ($note['type'] === 'holiday') : ?>
<p class="noren-extra" data-holiday><?= $text ?>
<?php if ($extra !== null) : ?>
<?= esc(\Sayamaapps\Noren\I18n::t('detail.separator')) ?>
<?= $extra ?>
<?php endif ?>
</p>
    <?php else : ?>
<p class="noren-extra" data-special><?= $text ?>
<?php if ($extra !== null) : ?>
<?= esc(\Sayamaapps\Noren\I18n::t('detail.separator')) ?>
<?= $extra ?>
<?php endif ?>
</p>
    <?php endif ?>
<?php endforeach ?>
