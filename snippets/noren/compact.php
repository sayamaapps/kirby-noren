<?php

/**
 * Noren compact hours: "Mi, Fr 10–18 Uhr" and the next closure (SPEC.md §3.8).
 * Unstyled, only noren-* classes, every value escaped. A site overrides it
 * with site/snippets/noren/compact.php.
 *
 * Variables (all optional):
 *   $schedule  \Sayamaapps\Noren\Schedule (default: site()->norenSchedule($area))
 *   $area      area key, used only for the default schedule
 *   $mode      'grouped' | 'consecutive' (default: noren.compact)
 *   $title     heading above the list (no heading without it)
 *   $now       DateTimeImmutable, default now in the schedule's timezone
 */

if (!\Sayamaapps\Noren\Options::active(kirby())) {
	return;
}
\Sayamaapps\Noren\Options::applyLanguage(kirby());
$settings = \Sayamaapps\Noren\Options::settings(kirby());

$area ??= null;
$mode ??= null;
$title ??= null;
$now ??= null;
$schedule ??= site()->norenSchedule($area);
if (!$schedule || !$schedule->maintained()) {
	return;
}
// The closure notes depend on the date: no page cache.
kirby()->response()->cache(false);

$compact = \Sayamaapps\Noren\Presenter::compact($schedule, $now, [
	'mode'     => $mode ?? $settings['compact'],
	'leadDays' => $settings['leadDays'],
]);
?>
<div class="noren-compact">
<?php if ($title !== null && $title !== '') : ?>
  <p class="noren-compact-title"><?= esc((string)$title) ?></p>
<?php endif ?>
  <ul class="noren-compact-list">
<?php foreach ($compact['groups'] as $group) : ?>
    <li><span class="noren-compact-days"><?= esc($group['label']) ?></span> <span class="noren-compact-hours"><?= esc($group['hours']) ?></span><?php
		if ($group['note'] !== null && $group['note'] !== '') : ?> <span class="noren-compact-note"><?= esc($group['note']) ?></span><?php
		endif ?></li>
<?php endforeach ?>
  </ul>
<?php foreach ($compact['notes'] as $note) : ?>
  <p class="noren-compact-closure"><?= esc($note['text']) ?><?php if ($note['note'] !== null && $note['note'] !== '') : ?> — <?= esc($note['note']) ?><?php endif ?></p>
<?php endforeach ?>
</div>
