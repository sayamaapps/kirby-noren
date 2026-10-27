<?php

use Kirby\Filesystem\Dir;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Snippets (SPEC.md §3.8, §9.5): noren/status, noren/table, noren/compact
 * rendered by a booted Kirby. Data is always passed explicitly, so this test
 * does not depend on Locations. Kirby loads the plugin; no src file is
 * required here.
 */
require_once __DIR__ . '/helpers.php';
const SNIP_MUSTER_HOURS = [
	['tag' => 'Montag', 'von1' => '13:00', 'bis1' => '18:00'],
	['tag' => 'Dienstag', 'von1' => '08:30', 'bis1' => '18:00'],
	['tag' => 'Mittwoch', 'von1' => '08:30', 'bis1' => '18:00'],
	['tag' => 'Donnerstag', 'von1' => '08:30', 'bis1' => '18:00'],
	['tag' => 'Freitag', 'von1' => '08:30', 'bis1' => '18:00'],
	['tag' => 'Samstag', 'von1' => '08:00', 'bis1' => '18:00'],
];

const SNIP_MUSTER_CLOSURES = [
	['von' => '2026-12-24', 'bis' => '2026-12-24', 'anlass' => 'Heiligabend', 'oeffnet' => '08:30', 'schliesst' => '12:00', 'hinweis' => 'Hinweis'],
	['von' => '2026-12-31', 'bis' => '2026-12-31', 'anlass' => 'Silvester'],
	['von' => '2027-08-02', 'bis' => '2027-08-15', 'hinweis' => 'Sommerpause'],
];

/**
 * Boots a fresh Kirby on a throwaway site and returns what $run gives back.
 * $files maps site-relative paths (e.g. snippets/noren/table.php) to content.
 */
function snip_boot(array $options, callable $run, array $files = []): mixed
{
	$tmp = noren_site();
	try {
		foreach ($files as $path => $code) {
			@mkdir(dirname($tmp . '/site/' . $path), 0700, true);
			file_put_contents($tmp . '/site/' . $path, $code);
		}
		$kirby = noren_kirby($tmp, $options);
		return $run($kirby);
	} finally {
		Dir::remove($tmp);
	}
}

/** Renders one snippet; the closure builds its data after boot (Noren classes exist then). */
function snip_render(string $name, array $options, callable $data, array $files = []): string
{
	return snip_boot($options, static function ($kirby) use ($name, $data) {
		return $kirby->snippet('noren/' . $name, $data($kirby), true);
	}, $files);
}

/** A plan from content rows, as the Panel stores them. */
function snip_plan(array $rows, array|null $closures = null, bool $holidays = false): Sayamaapps\Noren\Schedule
{
	return Sayamaapps\Noren\Schedule::fromFields(
		field($rows),
		$closures !== null ? field($closures) : null,
		null,
		null,
		$holidays ? Sayamaapps\Noren\Holidays::checker(Sayamaapps\Noren\Holidays::config('BY')) : null,
		[],
	);
}

function snip_muster(bool $holidays = true, array|null $closures = null): Sayamaapps\Noren\Schedule
{
	return snip_plan(SNIP_MUSTER_HOURS, $closures ?? SNIP_MUSTER_CLOSURES, $holidays);
}

/** The §3.7 unit shape, built by hand. */
function snip_unit(Sayamaapps\Noren\Schedule $schedule, Kirby\Cms\Page $location, string $id, string|null $area, string $label): array
{
	return [
		'id' => $id,
		'location' => $location,
		'area' => $area,
		'name' => $label,
		'short' => $label,
		'label' => $label,
		'schedule' => $schedule,
	];
}

function snip_has(string $html, string $line, string $what): void
{
	holds(str_contains($html, $line), $what . "\n      missing: " . $line . "\n      got: " . $html);
}

#[CoversNothing]
final class SnippetsTest extends TestCase
{
	public function testSuite(): void
	{
		$on = ['sayamaapps.noren.active' => true, 'locale' => 'de_DE'];

		// ------------------------------------------------------------------ status

		// One unit, Monday 10:00: closed until 13:00
		$html = snip_render('status', $on, static function ($kirby) {
			$unit = snip_unit(snip_muster(false), $kirby->site()->homePage(), 'kontakt#laden', 'laden', 'Laden');
			return ['units' => [$unit], 'now' => at('2026-09-28 10:00')];
		});
		snip_has($html, '<div class="noren-status">', 'status: wrapper');
		snip_has($html, '<p class="noren-status-line" data-open="false" data-state="closed"><b class="noren-status-text">Jetzt geschlossen</b> <span class="noren-status-detail">öffnet heute um 13:00 Uhr</span></p>', 'status: one unit closed, no label');
		holds(!str_contains($html, 'noren-status-label'), 'status: one unit has no label span');
		holds(!str_contains($html, 'noren-status-more'), 'status: no more paragraph without link');

		// One unit, Monday 14:00: open until 18:00
		$html = snip_render('status', $on, static function ($kirby) {
			$unit = snip_unit(snip_muster(false), $kirby->site()->homePage(), 'kontakt#laden', 'laden', 'Laden');
			return ['units' => [$unit], 'now' => at('2026-09-28 14:00')];
		});
		snip_has($html, '<p class="noren-status-line" data-open="true" data-state="open"><b class="noren-status-text">Jetzt geöffnet</b> <span class="noren-status-detail">noch bis 18:00 Uhr</span></p>', 'status: one unit open');

		// Two units, labels; Café open at 10:00, Laden closed
		$html = snip_render('status', $on, static function ($kirby) {
			$home = $kirby->site()->homePage();
			$cafe = snip_plan([['tag' => 'Montag', 'von1' => '09:00', 'bis1' => '12:00']]);
			return [
				'units' => [
					snip_unit(snip_muster(false), $home, 'kontakt#laden', 'laden', 'Laden'),
					snip_unit($cafe, $home, 'kontakt#cafe', 'cafe', 'Café'),
				],
				'now' => at('2026-09-28 10:00'),
			];
		});
		snip_has($html, '<span class="noren-status-label">Laden</span> <b class="noren-status-text">Jetzt geschlossen</b> <span class="noren-status-detail">öffnet heute um 13:00 Uhr</span>', 'status: two units → label for Laden');
		snip_has($html, '<span class="noren-status-label">Café</span> <b class="noren-status-text">Jetzt geöffnet</b> <span class="noren-status-detail">noch bis 12:00 Uhr</span>', 'status: two units → label for Café');
		holds(substr_count($html, '<p class="noren-status-line"') === 2, 'status: one line per unit');

		// only = area
		$html = snip_render('status', $on, static function ($kirby) {
			$home = $kirby->site()->homePage();
			$cafe = snip_plan([['tag' => 'Montag', 'von1' => '09:00', 'bis1' => '12:00']]);
			return [
				'units' => [
					snip_unit(snip_muster(false), $home, 'kontakt#laden', 'laden', 'Laden'),
					snip_unit($cafe, $home, 'kontakt#cafe', 'cafe', 'Café'),
				],
				'only' => 'cafe',
				'now' => at('2026-09-28 10:00'),
			];
		});
		holds(substr_count($html, '<p class="noren-status-line"') === 1, 'status only=area: one line');
		holds(!str_contains($html, 'noren-status-label'), 'status only=area: a single unit has no label');
		holds(str_contains($html, 'noch bis 12:00 Uhr'), 'status only=area: the café line');

		// only = unit id
		$html = snip_render('status', $on, static function ($kirby) {
			$home = $kirby->site()->homePage();
			return [
				'units' => [
					snip_unit(snip_muster(false), $home, 'kontakt#laden', 'laden', 'Laden'),
					snip_unit(snip_plan([['tag' => 'Montag', 'von1' => '09:00', 'bis1' => '12:00']]), $home, 'kontakt#cafe', 'cafe', 'Café'),
				],
				'only' => 'kontakt#laden',
				'now' => at('2026-09-28 10:00'),
			];
		});
		holds(str_contains($html, 'öffnet heute um 13:00 Uhr') && !str_contains($html, 'Café'), 'status only=id: only the Laden line');

		// link
		$html = snip_render('status', $on, static function ($kirby) {
			return [
				'units' => [snip_unit(snip_muster(false), $kirby->site()->homePage(), 'kontakt', null, 'Laden')],
				'link' => '/kontakt',
				'now' => at('2026-09-28 10:00'),
			];
		});
		snip_has($html, '<p class="noren-status-more"><a href="/kontakt">Alle Öffnungszeiten</a></p>', 'status: link paragraph');

		// Nothing to show
		expect('', snip_render('status', $on, static fn () => ['units' => []]), 'status: no units → nothing');
		expect('', snip_render('status', ['sayamaapps.noren.active' => true, 'sayamaapps.noren.status' => false], static fn () => ['units' => []]), 'status: noren.status false → nothing');
		expect('', snip_render('status', ['sayamaapps.noren.active' => true, 'sayamaapps.noren.status' => false], static function ($kirby) {
			return ['units' => [snip_unit(snip_muster(false), $kirby->site()->homePage(), 'k', null, 'Laden')], 'now' => at('2026-09-28 10:00')];
		}), 'status: noren.status false → nothing even with units');
		expect('', snip_render('status', ['locale' => 'de_DE'], static function ($kirby) {
			return ['units' => [snip_unit(snip_muster(false), $kirby->site()->homePage(), 'k', null, 'Laden')], 'now' => at('2026-09-28 10:00')];
		}), 'status: noren.active missing → nothing');

		// Escaping in the detail text is applied to every value
		$html = snip_render('status', $on, static function ($kirby) {
			$unit = snip_unit(snip_muster(false), $kirby->site()->homePage(), 'k', null, 'Laden <i>x</i>');
			return ['units' => [$unit, snip_unit(snip_muster(false), $kirby->site()->homePage(), 'k2', null, 'B')], 'now' => at('2026-09-28 10:00')];
		});
		holds(str_contains($html, 'Laden &lt;i&gt;x&lt;/i&gt;') && !str_contains($html, '<i>x</i>'), 'status: label is escaped');

		// ------------------------------------------------------------------- table

		$html = snip_render('table', $on, static fn () => ['schedule' => snip_muster(), 'now' => at('2026-09-28 10:00')]);
		snip_has($html, '<div class="noren-table">', 'table: wrapper');
		snip_has($html, '<div class="noren-row" data-today="true"><div class="noren-day">Montag</div><div class="noren-hours">13:00 – 18:00<br></div></div>', 'table: Monday is today');
		snip_has($html, '<div class="noren-row" data-today="false"><div class="noren-day">Sonntag</div><div class="noren-hours"><span class="noren-closed">geschlossen</span></div></div>', 'table: closed Sunday is printed');
		snip_has($html, '<div class="noren-row noren-row--holidays" data-today="false"><div class="noren-day">Feiertage</div><div class="noren-hours"><span class="noren-closed">geschlossen</span></div></div>', 'table: holidays row');
		snip_has($html, '<p class="noren-extra" data-holiday>3.10. Tag der Deutschen Einheit: geschlossen</p>', 'table: holiday note');
		holds(substr_count($html, 'class="noren-row"') === 7, 'table: seven weekday rows');

		// Weekday with two blocks, note; overnight; closures with and without note
		$shop = snip_plan([
			['tag' => 'Samstag', 'von1' => '08:00', 'bis1' => '12:00', 'von2' => '14:00', 'bis2' => '18:00', 'hinweis' => 'warme Küche bis 14 Uhr'],
			['tag' => 'Freitag', 'von1' => '18:00', 'bis1' => '02:00'],
		], null, false);
		$html = snip_render('table', $on, static fn () => ['schedule' => $shop, 'now' => at('2026-09-28 10:00')]);
		snip_has($html, '<div class="noren-row" data-today="false"><div class="noren-day">Samstag</div><div class="noren-hours">8:00 – 12:00<br>14:00 – 18:00<br><span class="noren-note">warme Küche bis 14 Uhr</span></div></div>', 'table: two blocks and a note');
		snip_has($html, '<div class="noren-hours">18:00 – 2:00<br></div>', 'table: overnight row');
		holds(!str_contains($html, 'noren-row--holidays'), 'table: no holidays row without holidays');

		$html = snip_render('table', $on, static fn () => ['schedule' => snip_muster(true), 'now' => at('2026-12-17 10:00')]);
		snip_has($html, '<p class="noren-extra" data-holiday>25.12. 1. Weihnachtsfeiertag: geschlossen</p>', 'table: holiday within 14 days');
		snip_has($html, '<p class="noren-extra" data-special>24.12. Heiligabend: 8:30–12:00 Uhr · Hinweis</p>', 'table: special hours with note');
		snip_has($html, '<p class="noren-extra" data-special>31.12. Silvester: geschlossen</p>', 'table: Silvester closed day');

		$summerWeek = snip_muster(true, [['von' => '2026-07-06', 'bis' => '2026-07-10', 'anlass' => 'Sommerwoche', 'oeffnet' => '10:00', 'schliesst' => '14:00']]);
		$html = snip_render('table', $on, static fn () => ['schedule' => $summerWeek, 'now' => at('2026-07-08 10:00')]);
		snip_has($html, '<p class="noren-extra" data-special>6.7. bis 10.7.2026 Sommerwoche: 10:00–14:00 Uhr</p>', 'table: multi-day special hours name both dates');

		$html = snip_render('table', $on, static fn () => ['schedule' => snip_muster(true), 'now' => at('2027-07-25 10:00')]);
		snip_has($html, '<p class="noren-closure"><strong>Betriebsferien 2.8. bis 15.8.2027</strong><br>Sommerpause</p>', 'table: closure paragraph with note');
		holds(substr_count($html, 'class="noren-closure"') === 1, 'table: one closure paragraph');

		$html = snip_render('table', $on, static fn () => ['schedule' => snip_muster(true), 'now' => at('2027-08-01 10:00')]);
		snip_has($html, '<p class="noren-closure"><strong>Betriebsferien 2.8. bis 15.8.2027</strong><br>Sommerpause</p>', 'table: closure with note after br');

		// Holidays row off by noren.holidayRow
		$html = snip_render('table', $on + ['sayamaapps.noren.holidayRow' => false], static fn () => ['schedule' => snip_muster(true), 'now' => at('2026-09-28 10:00')]);
		holds(!str_contains($html, 'noren-row--holidays'), 'table: noren.holidayRow false hides the holidays row');

		// Escaping
		$evil = snip_plan([['tag' => 'Montag', 'von1' => '10:00', 'bis1' => '12:00', 'hinweis' => '<script>alert(1)</script>']]);
		$html = snip_render('table', $on, static fn () => ['schedule' => $evil, 'now' => at('2026-09-28 10:00')]);
		holds(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html, '<script>'), 'table: HTML in a note is escaped');

		// Schedule not maintained: nothing
		expect('', snip_render('table', $on, static fn () => ['schedule' => snip_plan([]), 'now' => at('2026-09-28 10:00')]), 'table: schedule not maintained → nothing');
		expect('', snip_render('table', $on, static fn () => ['schedule' => null]), 'table: no schedule → nothing');
		expect('', snip_render('table', ['locale' => 'de_DE'], static fn () => ['schedule' => snip_muster(), 'now' => at('2026-09-28 10:00')]), 'table: noren.active missing → nothing');

		// English
		$html = snip_render('table', ['sayamaapps.noren.active' => true, 'sayamaapps.noren.language' => 'en', 'locale' => 'de_DE'], static fn () => ['schedule' => snip_muster(), 'now' => at('2026-10-03 10:00')]);
		snip_has($html, '<div class="noren-row" data-today="false"><div class="noren-day">Saturday</div><div class="noren-hours">8:00 – 18:00<br></div></div>', 'table en: English day names');
		snip_has($html, '<div class="noren-row noren-row--holidays" data-today="true"><div class="noren-day">Public holidays</div><div class="noren-hours"><span class="noren-closed">closed</span></div></div>', 'table en: holidays row');
		snip_has($html, '<p class="noren-extra" data-holiday>3 Oct German Unity Day: closed</p>', 'table en: holiday note');

		// A site's own snippet wins over the package snippet
		$html = snip_render('table', $on, static fn () => ['schedule' => snip_muster(), 'now' => at('2026-09-28 10:00')], [
			'snippets/noren/table.php' => '<p class="own">OWN TABLE</p>',
		]);
		expect('<p class="own">OWN TABLE</p>', trim($html), 'table: site override in site/snippets/noren/table.php wins');

		// ----------------------------------------------------------------- compact

		$html = snip_render('compact', $on, static fn () => ['schedule' => snip_muster(), 'now' => at('2026-09-28 10:00')]);
		snip_has($html, '<div class="noren-compact">', 'compact: wrapper');
		holds(!str_contains($html, 'noren-compact-title'), 'compact: no title without $title');
		snip_has($html, '<li><span class="noren-compact-days">Sa</span> <span class="noren-compact-hours">8–18 Uhr</span></li>', 'compact: Saturday line');
		snip_has($html, '<li><span class="noren-compact-days">Di–Fr</span> <span class="noren-compact-hours">8:30–18 Uhr</span></li>', 'compact: Tue–Fri line');
		snip_has($html, '<li><span class="noren-compact-days">Mo</span> <span class="noren-compact-hours">13–18 Uhr</span></li>', 'compact: Monday line');
		holds(!str_contains($html, 'noren-compact-closure'), 'compact: no closure paragraph within 14 days');

		$html = snip_render('compact', $on, static fn () => ['schedule' => snip_muster(), 'now' => at('2026-09-28 10:00'), 'mode' => 'consecutive']);
		snip_has($html, '<li><span class="noren-compact-days">Di – Fr</span> <span class="noren-compact-hours">8:30 – 18:00</span></li>', 'compact consecutive: Di – Fr');

		$werkstatt = snip_plan([
			['tag' => 'Mittwoch', 'von1' => '10:00', 'bis1' => '18:00', 'hinweis' => 'nach Vereinbarung'],
			['tag' => 'Freitag', 'von1' => '10:00', 'bis1' => '18:00', 'hinweis' => 'nach Vereinbarung'],
			['tag' => 'Samstag', 'von1' => '09:00', 'bis1' => '13:00'],
		]);
		$html = snip_render('compact', $on, static fn () => ['schedule' => $werkstatt, 'now' => at('2026-09-28 10:00'), 'title' => 'Werkstatt-Zeiten']);
		snip_has($html, '<p class="noren-compact-title">Werkstatt-Zeiten</p>', 'compact: title');
		snip_has($html, '<li><span class="noren-compact-days">Mi, Fr</span> <span class="noren-compact-hours">10–18 Uhr</span> <span class="noren-compact-note">nach Vereinbarung</span></li>', 'compact: group with note');
		snip_has($html, '<li><span class="noren-compact-days">Sa</span> <span class="noren-compact-hours">9–13 Uhr</span></li>', 'compact: group without note');

		$html = snip_render('compact', $on, static fn () => ['schedule' => snip_muster(), 'now' => at('2027-07-25 10:00')]);
		snip_has($html, '<p class="noren-compact-closure">Betriebsferien 2.8. bis 15.8.2027 — Sommerpause</p>', 'compact: closure paragraph with note');

		expect('', snip_render('compact', $on, static fn () => ['schedule' => snip_plan([]), 'now' => at('2026-09-28 10:00')]), 'compact: not maintained → nothing');
		expect('', snip_render('compact', ['locale' => 'de_DE'], static fn () => ['schedule' => snip_muster(), 'now' => at('2026-09-28 10:00')]), 'compact: noren.active missing → nothing');

		$html = snip_render('compact', ['sayamaapps.noren.active' => true, 'sayamaapps.noren.language' => 'en', 'locale' => 'de_DE'], static fn () => ['schedule' => snip_muster(), 'now' => at('2026-09-28 10:00')]);
		snip_has($html, '<li><span class="noren-compact-days">Sat</span> <span class="noren-compact-hours">8–18</span></li>', 'compact en: English days and hours');
		snip_has($html, '<li><span class="noren-compact-days">Tue–Fri</span> <span class="noren-compact-hours">8:30–18</span></li>', 'compact en: Tue–Fri');
	}
}
