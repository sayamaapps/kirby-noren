<?php

/**
 * Noren status band: "Jetzt geöffnet · noch bis 18:00 Uhr" per unit (SPEC.md §3.8).
 * Unstyled, only noren-* classes, every value escaped. A site overrides it
 * with site/snippets/noren/status.php.
 *
 * Variables (all optional):
 *   $units  list of units as site()->norenUnits() (default: that call, at $now)
 *   $only   keep only units whose id, area, areas or location id equals this value
 *   $link   URL of a page with all opening hours
 *   $now    DateTimeImmutable, default now in each schedule's timezone
 */

if (!\Sayamaapps\Noren\Options::active(kirby())) {
	return;
}
\Sayamaapps\Noren\Options::applyLanguage(kirby());
$settings = \Sayamaapps\Noren\Options::settings(kirby());
if ($settings['status'] !== true) {
	return;
}

$now ??= null;
// Grouped at the moment shown: a preview date may split areas that are alike today.
$units ??= site()->norenUnits($now);
$only ??= null;
$link ??= null;

if ($only !== null && $only !== '') {
	$units = array_values(array_filter($units, static function (array $unit) use ($only): bool {
		return ($unit['id'] ?? null) === $only ||
			($unit['area'] ?? null) === $only ||
			in_array($only, $unit['areas'] ?? [], true) ||
			(string)($unit['location']?->id() ?? '') === $only;
	}));
}
if ($units === []) {
	return;
}
// The status changes with the clock: Kirby's page cache must not keep this page.
kirby()->response()->cache(false);

$labels = count($units) > 1;
?>
<div class="noren-status">
<?php foreach ($units as $unit) :
	$status = $unit['schedule']->status($now);
	?>
  <p class="noren-status-line" data-open="<?= $status['open'] ? 'true' : 'false' ?>" data-state="<?= esc((string)$status['state']) ?>"><?php if ($labels) : ?><span class="noren-status-label"><?= esc((string)$unit['label']) ?></span> <?php endif ?><b class="noren-status-text"><?= esc((string)$status['text']) ?></b><?php if ($status['detail'] !== '') : ?> <span class="noren-status-detail"><?= esc((string)$status['detail']) ?></span><?php endif ?></p>
<?php endforeach ?>
<?php if ($link !== null && $link !== '') : ?>
  <p class="noren-status-more"><a href="<?= esc((string)$link) ?>"><?= esc(\Sayamaapps\Noren\I18n::t('status.allHours')) ?></a></p>
<?php endif ?>
</div>
