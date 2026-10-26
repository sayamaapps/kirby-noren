<?php

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The plugin with real Kirby (SPEC.md §9.7), in throwaway site directories:
 *  1. registered, off by default, translations, noren_option();
 *  2. sample shape: areas laden / cafe / werkstatt, legacy nm_ row, closures
 *     per area, two call orders without memoisation, units;
 *  3. no areas: one unit with id = page id;
 *  4. medical-practice shape: several locations, vm_/nm_ rows, closures in `urlaub`,
 *     the template list form;
 *  5. noren.locations as closure (listed only) and as list of ids;
 *  6. page methods (zeitenschluessel, no reference, location page);
 *  7. holidays: 'BY', legacy array, 'XX' logged once, norenHolidays(2026);
 *  8. options: timezone, holidayEve, overnightUntil, closureLabel, language;
 *  9. norenSchema(), norenStatus(), norenTable(), norenCompact();
 * 10. default snippet path and a site's own snippet.
 *
 * Kirby loads the plugin through the symlink noren_site() creates, so no src
 * file is required here.
 *
 *   vendor/bin/phpunit tests/KirbyTest.php
 */
require_once __DIR__ . '/helpers.php';
use Kirby\Cms\App;
use Kirby\Filesystem\Dir;

/** Runs $fn with a fresh throwaway site directory, removes it afterwards. */
function withSite(callable $fn): void
{
	$tmp = noren_site();
	try {
		$fn($tmp);
	} finally {
		Dir::remove($tmp);
	}
}

/** Sample shape: laden (Hofladen, kurzname Laden), cafe (Hofcafé), werkstatt (no hours). */
function ktStarter(): array
{
	return [
		'title' => 'Kontakt',
		'bereiche' => [
			['schluessel' => 'laden', 'name' => 'Hofladen', 'kurzname' => 'Laden'],
			['schluessel' => 'cafe', 'name' => 'Hofcafé'],
			['schluessel' => 'werkstatt', 'name' => 'Werkstatt'],
		],
		'zeiten' => [
			['bereich' => 'laden', 'tag' => 'Montag', 'von1' => '13:00', 'bis1' => '18:00'],
			['bereich' => 'laden', 'tag' => 'Dienstag', 'von1' => '08:30', 'bis1' => '12:00', 'von2' => '13:00', 'bis2' => '18:00', 'hinweis' => 'Mittags Lieferung'],
			['bereich' => 'laden', 'tag' => 'Samstag', 'nm_von' => '10:00', 'nm_bis' => '14:00'],
			['bereich' => 'cafe', 'tag' => 'Montag', 'von1' => '09:00', 'bis1' => '17:00'],
		],
		'schliessungen' => [
			['von' => '2026-12-24', 'bis' => '2026-12-24', 'anlass' => 'Heiligabend', 'oeffnet' => '08:30', 'schliesst' => '12:00'],
			['von' => '2027-08-02', 'bis' => '2027-08-15'],
			['von' => '2026-12-31', 'bis' => '2026-12-31', 'bereich' => 'cafe', 'anlass' => 'Silvester'],
		],
	];
}

/** The three areas of the sample shape, as norenAreas() returns them. */
const KT_AREAS = [
	['key' => 'laden', 'name' => 'Hofladen', 'short' => 'Laden'],
	['key' => 'cafe', 'name' => 'Hofcafé', 'short' => 'Hofcafé'],
	['key' => 'werkstatt', 'name' => 'Werkstatt', 'short' => 'Werkstatt'],
];

/** Runs $fn with the php error_log written to a file in $tmp, then restores the setting. */
function withErrorLog(string $tmp, callable $fn): string
{
	$previous = ini_get('error_log');
	$file = $tmp . '/php-error.log';
	ini_set('error_log', $file);
	try {
		$fn();
	} finally {
		ini_set('error_log', (string)$previous);
	}
	return is_file($file) ? (string)file_get_contents($file) : '';
}

/** Monday 9–17 and Saturday 10–14 for each of these areas: identical output. */
function ktMergeRows(array $areas): array
{
	$rows = [];
	foreach ($areas as $area) {
		$rows[] = ['bereich' => $area, 'tag' => 'Montag', 'von1' => '09:00', 'bis1' => '17:00'];
		$rows[] = ['bereich' => $area, 'tag' => 'Samstag', 'von1' => '10:00', 'bis1' => '14:00'];
	}
	return $rows;
}

/** A date $days after today in Europe/Berlin: inside the lead time, so closures show in the table at any run date. */
function ktInDays(int $days): string
{
	return (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->modify('+' . $days . ' days')->format('Y-m-d');
}

/** unit ids, in order */
function ktIds(array $units): array
{
	return array_map(static fn (array $u) => $u['id'], $units);
}

#[CoversNothing]
final class KirbyTest extends TestCase
{
	public function testSuite(): void
	{
		// --- 1. registered, off by default, translations -------------------------

		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());

			$off = noren_kirby($tmp);
			$off->plugins();
			holds(array_key_exists('sayamaapps/noren', $off->plugins()), 'plugin sayamaapps/noren is registered');
			expect(null, $off->site()->norenSchedule(), 'off by default: norenSchedule() is null');
			expect([], $off->site()->norenUnits(), 'off by default: norenUnits() is []');
			expect([], $off->site()->norenLocations(), 'off by default: norenLocations() is []');
			expect([], $off->site()->norenHolidays(2026), 'off by default: norenHolidays() is []');
			expect(null, $off->page('kontakt')->norenSchedule(), 'off by default: page norenSchedule() is null');
			expect(false, $off->page('kontakt')->norenHasHours(), 'off by default: norenHasHours() is false');

			$yes = noren_kirby($tmp, ['sayamaapps.noren.active' => 'yes']);
			expect(null, $yes->site()->norenSchedule(), '"yes" does not switch Noren on: norenSchedule() is null');
			expect([], $yes->site()->norenUnits(), '"yes" does not switch Noren on: norenUnits() is []');

			$on = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE']);
			$on->plugins();
			holds(array_key_exists('sayamaapps/noren', $on->plugins()), 'plugin is registered with the switch on, too');
			expect('Open now', $on->translation('en')->get('noren.status.openNow'), 'translation noren.status.openNow in English');
			expect('Jetzt geöffnet', $on->translation('de')->get('noren.status.openNow'), 'translation noren.status.openNow in German');
			holds(function_exists('noren_option'), 'noren_option() exists');
			expect(true, noren_option('active'), 'noren_option() reads noren.active');
			expect('fallback', noren_option('gibtsnicht', 'fallback'), 'noren_option() returns the fallback for an unset key');
		});

		// --- 2. sample shape ---------------------------------------------------

		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());

			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE']);
			expect('kontakt', $kirby->site()->norenLocation()?->id(), 'sample: first location is kontakt');
			expect(KT_AREAS, $kirby->site()->norenAreas(), 'sample: areas laden (Hofladen/Laden), cafe (Hofcafé), werkstatt');

			// Both call orders on one instance: no memoisation keyed on the page.
			$laden = $kirby->site()->norenSchedule('laden');
			$cafe = $kirby->site()->norenSchedule('cafe');
			expect([[780, 1080]], $laden->blocks(0), 'sample: laden Monday 13:00–18:00');
			expect([[540, 1020]], $cafe->blocks(0), 'sample: cafe Monday 9:00–17:00 (second call, same page)');

			$kirby2 = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE']);
			$cafe2 = $kirby2->site()->norenSchedule('cafe');
			$laden2 = $kirby2->site()->norenSchedule('laden');
			expect([[540, 1020]], $cafe2->blocks(0), 'sample: cafe first on a new instance');
			expect([[780, 1080]], $laden2->blocks(0), 'sample: laden second on the same instance');

			$page = $kirby2->page('kontakt');
			expect([[780, 1080]], $page->norenSchedule('laden')->blocks(0), 'sample: page norenSchedule(laden)');
			expect([[540, 1020]], $page->norenSchedule('cafe')->blocks(0), 'sample: page norenSchedule(cafe) right after it');

			// Legacy nm_von/nm_bis row is read as the second pair; the Tuesday note comes along.
			expect([[600, 840]], $laden->blocks(5), 'sample: legacy nm_von/nm_bis row, Saturday 10:00–14:00');
			expect([[510, 720], [780, 1080]], $laden->blocks(1), 'sample: Tuesday two blocks');
			expect('Mittags Lieferung', $laden->note(1), 'sample: Tuesday note');
			holds(!$kirby->site()->norenSchedule('werkstatt')->maintained(), 'sample: werkstatt has no hours');

			// Closures follow the area filter: laden gets the two all-area rows, cafe also Silvester.
			expect(2, count($laden->closures()), 'sample: laden sees two closures (all-area rows)');
			expect(3, count($cafe->closures()), 'sample: cafe sees its own closure too');

			expect(KT_AREAS, $kirby->page('kontakt')->norenAreas(), 'sample: page norenAreas() of the location page');

			$units = $kirby->site()->norenUnits();
			expect(['kontakt#laden', 'kontakt#cafe'], array_map(static fn (array $u) => $u['id'], $units), 'sample: units ids (werkstatt left out)');
			expect(['Laden', 'Hofcafé'], array_map(static fn (array $u) => $u['label'], $units), 'sample: unit labels');
			expect(['Hofladen', 'Hofcafé'], array_map(static fn (array $u) => $u['name'], $units), 'sample: unit names');
			expect('kontakt', $units[0]['location']->id(), 'sample: unit location is the page');
			expect('laden', $units[0]['area'], 'sample: unit area key');
		});

		// --- 3. no areas ----------------------------------------------------------

		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', [
				'title' => 'Kontakt',
				'kurzname' => 'Laden',
				'zeiten' => [
					['tag' => 'Mittwoch', 'von1' => '10:00', 'bis1' => '16:00'],
				],
			]);
			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true]);
			$units = $kirby->site()->norenUnits();
			expect(1, count($units), 'no areas: one unit');
			expect('kontakt', $units[0]['id'], 'no areas: unit id is the page id');
			expect(true, array_key_exists('area', $units[0]) && $units[0]['area'] === null, 'no areas: unit area is null');
			expect('Kontakt', $units[0]['name'] ?? null, 'no areas: unit name is the page title');
			expect('Laden', $units[0]['short'] ?? null, 'no areas: unit short is kurzname');
			expect('Laden', $units[0]['label'] ?? null, 'no areas: a single location labels with its short name');
			expect([], $kirby->site()->norenAreas(), 'no areas: norenAreas() is []');
			holds(is_array($kirby->site()->norenStatus()), 'no areas: a status line exists ');
		});

		// --- 4. medical-practice shape: several locations -------------------------------

		withSite(function (string $tmp): void {
			noren_page($tmp, '1_beispiel', 'standort', [
				'title' => 'Praxis Beispiel',
				'kurzname' => 'Beispiel',
				'zeiten' => [['tag' => 'Montag', 'vm_von' => '08:00', 'vm_bis' => '12:00', 'nm_von' => '14:00', 'nm_bis' => '18:00']],
				'urlaub' => [['von' => '2026-08-03', 'bis' => '2026-08-14']],
			]);
			noren_page($tmp, '2_beispiel-nord', 'standort', [
				'title' => 'Praxis Beispiel Nord',
				'kurzname' => 'Beispiel Nord',
				'zeiten' => [['tag' => 'Dienstag', 'vm_von' => '09:00', 'vm_bis' => '13:00']],
			]);
			noren_page($tmp, '3_kontakt', 'betrieb', [
				'title' => 'Verwaltung',
				'zeiten' => [['tag' => 'Freitag', 'von1' => '09:00', 'bis1' => '11:00']],
			]);

			$kirby = noren_kirby($tmp, [
				'sayamaapps.noren.active' => true,
				'sayamaapps.noren.template' => 'standort',
				'sayamaapps.noren.fields' => ['closures' => 'urlaub'],
				'locale' => 'de_DE',
			]);
			expect(['beispiel', 'beispiel-nord'], array_map(static fn ($p) => $p->id(), $kirby->site()->norenLocations()), 'practice: two locations in order, template standort');
			$units = $kirby->site()->norenUnits();
			expect(['beispiel', 'beispiel-nord'], array_map(static fn (array $u) => $u['id'], $units), 'practice: units without areas, ids = page ids');
			expect(['Beispiel', 'Beispiel Nord'], array_map(static fn (array $u) => $u['label'], $units), 'practice: unit labels from kurzname');

			$beispiel = $kirby->page('beispiel');
			expect([[480, 720], [840, 1080]], $beispiel->norenSchedule()->blocks(0), 'practice: vm_/nm_ rows read as two blocks');
			expect(1, count($beispiel->norenSchedule()->closures()), 'practice: the urlaub closure is read');
			$inside = $beispiel->norenSchedule()->status(at('2026-08-10 10:00'));
			expect('closure', $inside['state'], 'practice: urlaub closure applies during its days');

			$list = noren_kirby($tmp, [
				'sayamaapps.noren.active' => true,
				'sayamaapps.noren.template' => ['betrieb', 'standort'],
			]);
			expect(
				['beispiel', 'beispiel-nord', 'kontakt'],
				array_map(static fn ($p) => $p->id(), $list->site()->norenLocations()),
				'practice: template list form gives all three locations in order'
			);
		});

		// --- 5. noren.locations as closure and as list -----------------------------

		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());
			noren_page($tmp, 'altstandort', 'betrieb', [
				'title' => 'Alter Standort',
				'zeiten' => [['tag' => 'Montag', 'von1' => '10:00', 'bis1' => '11:00']],
			]);

			$default = noren_kirby($tmp, ['sayamaapps.noren.active' => true]);
			expect(
				['kontakt', 'altstandort'],
				array_map(static fn ($p) => $p->id(), $default->site()->norenLocations()),
				'default: listed and unlisted pages of the template are locations'
			);

			$closure = noren_kirby($tmp, [
				'sayamaapps.noren.active' => true,
				'sayamaapps.noren.locations' => fn () => kirby()->site()->children()->listed(),
			]);
			expect(
				['kontakt'],
				array_map(static fn ($p) => $p->id(), $closure->site()->norenLocations()),
				'closure: only the listed pages'
			);

			$ids = noren_kirby($tmp, [
				'sayamaapps.noren.active' => true,
				'sayamaapps.noren.locations' => ['kontakt', 'gibtsnicht', 'altstandort'],
			]);
			expect(
				['kontakt', 'altstandort'],
				array_map(static fn ($p) => $p->id(), $ids->site()->norenLocations()),
				'list of ids: unknown id skipped, order kept'
			);
		});

		// --- 6. page methods --------------------------------------------------------

		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());
			noren_page($tmp, '3_info', 'seite', ['title' => 'Info', 'zeitenschluessel' => 'cafe']);
			noren_page($tmp, '4_anfahrt', 'seite', ['title' => 'Anfahrt']);
			noren_page($tmp, '5_werkstatt-hinweis', 'seite', ['title' => 'Werkstatt', 'zeitenschluessel' => 'werkstatt']);

			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE']);

			$info = $kirby->page('info');
			expect([[540, 1020]], $info->norenSchedule()->blocks(0), 'page with zeitenschluessel cafe: café plan');
			holds($info->norenHasHours() === true, 'page with zeitenschluessel cafe: norenHasHours() is true');
			expect(KT_AREAS, $info->norenAreas(), 'non-location page: norenAreas() of the first location');

			$anfahrt = $kirby->page('anfahrt');
			// Café 9–17 and shop 13–18 overlap without an area filter: one opening, 9–18.
			expect([[540, 1080]], $anfahrt->norenSchedule()->blocks(0), 'page without reference: all rows, overlaps merged');
			holds($anfahrt->norenHasHours() === true, 'page without reference: norenHasHours() is true');

			$werkstatt = $kirby->page('werkstatt-hinweis');
			holds($werkstatt->norenHasHours() === false, 'page pointing to an area without hours: norenHasHours() is false');

			$location = $kirby->page('kontakt');
			expect([[540, 1080]], $location->norenSchedule()->blocks(0), 'location page: own plan, all rows, overlaps merged');
			expect([[780, 1080]], $location->norenSchedule('laden')->blocks(0), 'location page: own plan for an area');
			expect(3, count($location->norenAreas()), 'location page: its own areas');
			expect(true, is_array($location->norenStatus('laden')), 'location page: norenStatus(laden) is an array');
		});

		// --- 7. holidays ------------------------------------------------------------

		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());

			// 3 October 2026 is a Saturday: the Saturday row is open, so the holiday shows.
			$by = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.holidays' => 'BY', 'locale' => 'de_DE']);
			$status = $by->site()->norenSchedule()->status(at('2026-10-03 10:00'));
			expect('Heute Feiertag', $status['text'], "'BY': 'Heute Feiertag' on 3 October");
			expect('holiday', $status['state'], "'BY': state holiday");
			expect(13, count($by->site()->norenHolidays(2026)), "'BY': norenHolidays(2026) has 13 entries");

			$legacy = noren_kirby($tmp, [
				'sayamaapps.noren.active' => true,
				'sayamaapps.noren.holidays' => ['land' => 'bayern', 'mariaeHimmelfahrt' => true, 'friedensfest' => false],
				'locale' => 'de_DE',
			]);
			expect('Heute Feiertag', $legacy->site()->norenSchedule()->status(at('2026-10-03 10:00'))['text'], 'legacy array (land bayern) works');
			expect(13, count($legacy->site()->norenHolidays(2026)), 'legacy array: 13 holidays in 2026');

			$bad = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.holidays' => 'XX', 'locale' => 'de_DE']);
			$logged = withErrorLog($tmp, function () use ($bad): void {
				$schedule = $bad->site()->norenSchedule();
				expect(false, $schedule->hasHolidays(), "'XX': the schedule has no holidays");
				expect([], $bad->site()->norenHolidays(2026), "'XX': norenHolidays() is []");
				expect(false, $bad->site()->norenSchedule()->hasHolidays(), "'XX': still no holidays on the next call");
			});
			holds(str_contains($logged, 'Noren: '), "'XX': the problem is logged with the Noren prefix");
			holds(str_contains($logged, 'public holidays are off'), "'XX': the log says public holidays are off");
			expect(1, substr_count($logged, 'public holidays are off'), "'XX': logged once, not on every call");
		});

		// --- 8. options -------------------------------------------------------------

		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());
			noren_page($tmp, '2_bar', 'betrieb', [
				'title' => 'Bar',
				'zeiten' => [['tag' => 'Freitag', 'von1' => '18:00', 'bis1' => '02:00']],
			]);

			$defaults = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE']);
			expect('Europe/Berlin', $defaults->site()->norenSchedule()->timezone()->getName(), 'timezone default Europe/Berlin');
			expect('Betriebsferien', $defaults->site()->norenSchedule()->closureLabel(), 'closureLabel default: translated (de) Betriebsferien');
			expect([[1080, 1560]], $defaults->page('bar')->norenSchedule()->blocks(4), 'overnight Friday 18:00–02:00 with the default 06:00 limit');

			$noOvernight = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.overnightUntil' => false]);
			expect([], $noOvernight->page('bar')->norenSchedule()->blocks(4), "'overnightUntil' => false: overnight row ignored");

			$zone = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.timezone' => 'America/New_York']);
			expect('America/New_York', $zone->site()->norenSchedule()->timezone()->getName(), 'timezone option');

			$label = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.closureLabel' => 'Praxisurlaub', 'locale' => 'de_DE']);
			expect('Praxisurlaub', $label->site()->norenSchedule('laden')->closureLabel(), 'closureLabel option');
			expect('Praxisurlaub', $label->site()->norenSchedule('laden')->label(['from' => '2027-08-02', 'to' => '2027-08-15', 'occasion' => null]), 'closureLabel option is used for a range');

			// Holiday eve: Friday 2 October 2026, the holiday is Saturday 3 October.
			$eveOff = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.holidays' => 'BY', 'locale' => 'de_DE']);
			$offStatus = $eveOff->site()->norenSchedule()->status(at('2026-10-02 19:00'));
			expect(null, $offStatus['holiday'], 'holidayEve off: no notice for tomorrow');

			$eveOn = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.holidays' => 'BY', 'sayamaapps.noren.holidayEve' => true, 'locale' => 'de_DE']);
			$onStatus = $eveOn->site()->norenSchedule()->status(at('2026-10-02 19:00'));
			expect(\Sayamaapps\Noren\Holidays::name('germanUnity'), $onStatus['holiday'], 'holidayEve on: tomorrow\'s holiday is named');

			expect('de', \Sayamaapps\Noren\Options::language($defaults), 'language: locale de_DE gives de');
			$forced = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.language' => 'en', 'locale' => 'de_DE']);
			expect('en', \Sayamaapps\Noren\Options::language($forced), 'language: noren.language wins over locale');
			$unknown = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'fr_FR']);
			expect('en', \Sayamaapps\Noren\Options::language($unknown), 'language: an unshipped locale falls back to English');
		});

		// --- 9. schema, status, table, compact --------------------------------------

		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());
			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE']);

			$schema = $kirby->site()->norenSchema('laden');
			expect(['openingHoursSpecification', 'specialOpeningHoursSpecification'], array_keys($schema ?? []), 'norenSchema() keys');
			expect(
				[
					['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '13:00', 'closes' => '18:00'],
					['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Tuesday', 'opens' => '08:30', 'closes' => '12:00'],
					['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Tuesday', 'opens' => '13:00', 'closes' => '18:00'],
					['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Saturday', 'opens' => '10:00', 'closes' => '14:00'],
				],
				$schema['openingHoursSpecification'] ?? null,
				'norenSchema(laden): weekly entries'
			);
			holds(is_array($kirby->page('kontakt')->norenSchema()), 'page norenSchema() is an array');

			$status = $kirby->site()->norenStatus('laden');
			$keys = is_array($status) ? array_keys($status) : [];
			sort($keys);
			expect(['detail', 'holiday', 'next', 'occasion', 'open', 'state', 'text', 'until'], $keys, 'norenStatus() keys');

			$table = $kirby->site()->norenTable('laden');
			expect(7, count($table['days'] ?? []), 'norenTable(): seven day rows');

			$compact = $kirby->site()->norenCompact('laden');
			expect(['mode', 'groups', 'notes'], array_keys($compact ?? []), 'norenCompact() keys');
		});

		// --- 10. default snippet and the site's own snippet --------------------------

		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());
			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE']);
			$html = (string)$kirby->snippet('noren/status', ['now' => at('2026-10-05 14:00')], true);
			holds(str_contains($html, 'Jetzt'), 'default snippet noren/status shows the status with real content');

			mkdir($tmp . '/site/snippets/noren', 0700, true);
			file_put_contents($tmp . '/site/snippets/noren/status.php', '<p class="own">OWN-SNIPPET</p>');
			$own = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE']);
			$ownHtml = (string)$own->snippet('noren/status', [], true);
			holds(str_contains($ownHtml, 'OWN-SNIPPET'), "a site's own site/snippets/noren/status.php wins");
		});


		// --- 11. regressions ------------------------------------------------

		// No locale and no noren.language gives English; noren.language de gives German.
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());
			$none = noren_kirby($tmp, ['sayamaapps.noren.active' => true]);
			expect('en', \Sayamaapps\Noren\Options::language($none), 'language: no locale and no noren.language gives English');
			$de = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.language' => 'de']);
			expect('de', \Sayamaapps\Noren\Options::language($de), 'language: noren.language de without any locale gives German');
			expect('Jetzt geöffnet', (function () use ($de): string {
				\Sayamaapps\Noren\Options::applyLanguage($de);
				return \Sayamaapps\Noren\I18n::t('status.openNow');
			})(), 'language: the status texts follow noren.language de');
		});

		// Time-dependent output is not kept by Kirby's page cache.
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());
			$calls = [
				'norenStatus' => fn ($site) => $site->norenStatus(),
				'norenTable' => fn ($site) => $site->norenTable(),
				'norenCompact' => fn ($site) => $site->norenCompact(),
				'norenSchema' => fn ($site) => $site->norenSchema(),
			];
			foreach ($calls as $name => $call) {
				$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE', 'cache' => ['pages' => ['active' => true]]]);
				expect(true, $kirby->response()->cache(), "cache: $name starts from a cacheable response");
				$call($kirby->site());
				expect(false, $kirby->response()->cache(), "cache: $name switches Kirby's page cache off for this response");
			}
			$snippetKirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE']);
			$snippetKirby->snippet('noren/status', [], true);
			expect(false, $snippetKirby->response()->cache(), 'cache: the status snippet switches the page cache off');
		});

		// A field that is not structure data is ignored and logged, the page still renders.
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ['title' => 'Kontakt', 'zeiten' => 'Mo-Fr 9-18 Uhr']);
			noren_page($tmp, '2_bar', 'betrieb', ['title' => 'Bar', 'zeiten' => ['Montag', 'Dienstag']]);
			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE']);
			\Sayamaapps\Noren\Log::forget();
			$logged = withErrorLog($tmp, function () use ($kirby): void {
				expect(null, $kirby->site()->norenStatus(), 'text zeiten: norenStatus() is null, no exception');
				expect(null, $kirby->page('bar')->norenStatus(), 'list of scalars: page norenStatus() is null, no exception');
				expect([], $kirby->site()->norenUnits(), 'text zeiten: norenUnits() is []');
				expect(false, $kirby->page('kontakt')->norenHasHours(), 'text zeiten: norenHasHours() is false');
			});
			holds(str_contains($logged, 'field zeiten on page kontakt is not structure data'), 'the bad field is logged with its page and name');
		});

		// A plan without hours gives no status, table, compact or schema: never "closed".
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_leer', 'betrieb', ['title' => 'Leer', 'zeiten' => [['tag' => 'Montag']]]);
			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE']);
			expect(null, $kirby->page('leer')->norenStatus(), 'no hours: norenStatus() is null, not "Heute geschlossen"');
			expect(null, $kirby->page('leer')->norenTable(), 'no hours: norenTable() is null');
			expect(null, $kirby->page('leer')->norenCompact(), 'no hours: norenCompact() is null');
			expect(null, $kirby->page('leer')->norenSchema(), 'no hours: norenSchema() is null');
			expect(null, $kirby->site()->norenStatus(), 'no hours: site norenStatus() is null');
			expect('', (string)$kirby->snippet('noren/status', ['only' => 'leer'], true), 'no hours: the status snippet prints nothing');
		});

		// A broken option is logged once per process, however often it is read.
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());
			\Sayamaapps\Noren\Log::forget();
			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.timezone' => 'Europe/Berln', 'sayamaapps.noren.overnightUntil' => '6 Uhr', 'locale' => 'de_DE']);
			$logged = withErrorLog($tmp, function () use ($kirby): void {
				for ($i = 0; $i < 3; $i++) {
					$kirby->site()->norenStatus();
					$kirby->site()->norenTable();
					$kirby->site()->norenUnits();
					$kirby->snippet('noren/status', [], true);
					$kirby->snippet('noren/table', [], true);
				}
			});
			expect(1, substr_count($logged, 'unknown noren.timezone'), 'timezone error logged once for 15 reads');
			expect(1, substr_count($logged, 'overnightUntil must be'), 'overnightUntil error logged once for 15 reads');
			expect('Europe/Berlin', $kirby->site()->norenSchedule()->timezone()->getName(), 'the broken timezone falls back to Europe/Berlin');
		});

		// With Kirby's debug option on, a broken config throws; without it, it is logged and the default is used.
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());
			$thrownBy = function (App $kirby, callable $call): string|null {
				try {
					$call($kirby);
				} catch (\InvalidArgumentException $e) {
					return $e->getMessage();
				}
				return null;
			};
			$debugZone = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.timezone' => 'Mars/Base', 'debug' => true, 'locale' => 'de_DE']);
			$message = $thrownBy($debugZone, fn (App $k) => $k->site()->norenStatus());
			holds(is_string($message) && str_contains($message, 'Mars/Base'), 'debug on: a broken noren.timezone throws, naming the zone');

			$debugHolidays = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.holidays' => 'XX', 'debug' => true, 'locale' => 'de_DE']);
			$message = $thrownBy($debugHolidays, fn (App $k) => $k->site()->norenSchedule());
			holds(is_string($message) && str_contains($message, 'XX'), 'debug on: an unknown holiday state throws');

			$debugOvernight = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.overnightUntil' => 'late', 'debug' => true, 'locale' => 'de_DE']);
			$message = $thrownBy($debugOvernight, fn (App $k) => $k->site()->norenStatus());
			holds(is_string($message) && str_contains($message, 'overnightUntil'), 'debug on: a broken overnightUntil throws');

			$quiet = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.timezone' => 'Mars/Base', 'locale' => 'de_DE']);
			$logged = withErrorLog($tmp, function () use ($quiet): void {
				expect('Europe/Berlin', $quiet->site()->norenSchedule()->timezone()->getName(), 'debug off: the default time zone is used');
			});
			holds(str_contains($logged, 'unknown noren.timezone'), 'debug off: the problem is logged');
		});

		// Every zone PHP accepts is valid, including those missing from listIdentifiers().
		// Legacy aliases (Europe/Kiev, US/Eastern) must work even where the runner's tzdata lacks them: they
		// resolve to their canonical zone, which has the same rules, so the hours are the same either way.
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());
			foreach (['Etc/UTC', 'GMT', 'Europe/Kiev', 'US/Eastern'] as $zone) {
				$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.timezone' => $zone, 'locale' => 'de_DE']);
				$logged = withErrorLog($tmp, function () use ($kirby, $zone): void {
					$resolved = $kirby->site()->norenSchedule()->timezone();
					$canonical = new DateTimeZone(\Sayamaapps\Noren\Options::legacyZone($zone) ?? $zone);
					foreach (['2026-01-15 12:00', '2026-07-15 12:00'] as $at) {
						expect(
							(new DateTimeImmutable($at, $canonical))->getOffset(),
							(new DateTimeImmutable($at, $resolved))->getOffset(),
							"timezone $zone has the rules of its zone at $at",
						);
					}
				});
				expect('', $logged, "timezone $zone is not logged as unknown");
			}
		});

		// Every canonical target of the alias table is a zone PHP knows.
		foreach (\Sayamaapps\Noren\Options::LEGACY_ZONES as $alias => $canonical) {
			try {
				new DateTimeZone($canonical);
				holds(true, "legacy zone $alias maps to a known zone");
			} catch (\Exception) {
				holds(false, "legacy zone $alias maps to $canonical, which PHP does not know");
			}
		}

		// A boolean option given as a string is reported, the default is used.
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', ktStarter());
			$strings = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.status' => 'false', 'sayamaapps.noren.holidayRow' => '0', 'locale' => 'de_DE']);
			\Sayamaapps\Noren\Log::forget();
			$logged = withErrorLog($tmp, function () use ($strings): void {
				$settings = \Sayamaapps\Noren\Options::settings($strings);
				expect(true, $settings['status'], "'status' => 'false' is not a switch: default true");
				expect(true, $settings['holidayRow'], "'holidayRow' => '0' is not a switch: default true");
			});
			holds(str_contains($logged, 'noren.status must be true or false'), 'a string status value is logged');
			holds(str_contains($logged, 'noren.holidayRow must be true or false'), 'a string holidayRow value is logged');
			holds(str_contains((string)$strings->snippet('noren/status', [], true), 'noren-status-line'), 'the status band still prints with status => "false"');
		});

		// overnightUntil false ignores rows past midnight, but a row ending at 00:00 is "until midnight".
		withSite(function (string $tmp): void {
			noren_page($tmp, '2_bar', 'betrieb', ['title' => 'Bar', 'zeiten' => [['tag' => 'Freitag', 'von1' => '18:00', 'bis1' => '00:00'], ['tag' => 'Samstag', 'von1' => '18:00', 'bis1' => '02:00']]]);
			$noOvernight = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.overnightUntil' => false, 'locale' => 'de_DE']);
			expect([[1080, 1440]], $noOvernight->page('bar')->norenSchedule()->blocks(4), 'overnightUntil false: Friday 18:00–00:00 is read until midnight');
			expect([], $noOvernight->page('bar')->norenSchedule()->blocks(5), 'overnightUntil false: Saturday 18:00–02:00 is ignored');
		});

		// The default leadDays (14) and holidayNoteDays (14) are asserted.
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', [
				'title' => 'Kontakt',
				// Saturday is open too: a holiday note is only given for a day the plan is open.
				'zeiten' => [['tag' => 'Montag', 'von1' => '08:00', 'bis1' => '12:00'], ['tag' => 'Samstag', 'von1' => '08:00', 'bis1' => '12:00']],
				'schliessungen' => [
					['von' => '2026-12-15', 'bis' => '2026-12-15', 'anlass' => 'Vierzehn'],
					['von' => '2026-12-16', 'bis' => '2026-12-16', 'anlass' => 'Fuenfzehn'],
				],
			]);
			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE']);
			$table = \Sayamaapps\Noren\Locations::table($kirby->site()->norenSchedule(), at('2026-12-01 10:00'));
			$froms = array_map(static fn (array $note) => $note['from'], $table['notes']);
			expect(['2026-12-15'], $froms, 'leadDays default 14: closure 14 days ahead shown, 15 days ahead not');

			$holidays = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.holidays' => 'BY', 'locale' => 'de_DE']);
			$on15 = \Sayamaapps\Noren\Locations::table($holidays->site()->norenSchedule(), at('2026-09-18 10:00'))['notes'];
			$on14 = \Sayamaapps\Noren\Locations::table($holidays->site()->norenSchedule(), at('2026-09-19 10:00'))['notes'];
			expect([], array_values(array_filter($on15, static fn (array $n) => $n['type'] === 'holiday')), 'holidayNoteDays default 14: holiday 15 days ahead not noted');
			expect(1, count(array_filter($on14, static fn (array $n) => $n['type'] === 'holiday')), 'holidayNoteDays default 14: holiday 14 days ahead noted');
		});

		// --- 11. noren.mergeAreas (SPEC.md §3.7) ---------------------------------










		// Two areas with the same hours: one unit "Hofladen und Hofcafé", the snippet finds it by either area.
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', [
				'title' => 'Kontakt',
				'bereiche' => [['schluessel' => 'laden', 'name' => 'Hofladen', 'kurzname' => 'Laden'], ['schluessel' => 'cafe', 'name' => 'Hofcafé']],
				'zeiten' => ktMergeRows(['laden', 'cafe']),
			]);

			$merged = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.mergeAreas' => true, 'locale' => 'de_DE']);
			$units = $merged->site()->norenUnits();
			expect(['kontakt#laden+cafe'], ktIds($units), 'mergeAreas: equal areas → one unit');
			expect('Hofladen und Hofcafé', $units[0]['name'] ?? null, 'mergeAreas: name "A und B"');
			expect('Laden und Hofcafé', $units[0]['label'] ?? null, 'mergeAreas: label from the short names');
			expect(['laden', 'cafe'], $units[0]['areas'] ?? null, 'mergeAreas: areas in order');
			expect('laden', $units[0]['area'] ?? null, 'mergeAreas: area is the first key');
			expect([[540, 1020]], $units[0]['schedule']->blocks(0), 'mergeAreas: the group carries the schedule');
			expect([[540, 1020]], $merged->site()->norenSchedule('cafe')->blocks(0), 'mergeAreas: norenSchedule() per area is unchanged');
			holds(str_contains((string)$merged->snippet('noren/status', ['only' => 'cafe'], true), 'noren-status-line'), 'mergeAreas: status $only = cafe finds the merged unit');

			$off = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'locale' => 'de_DE']);
			$offUnits = $off->site()->norenUnits();
			expect(['kontakt#laden', 'kontakt#cafe'], ktIds($offUnits), 'mergeAreas off (default): one unit per area');
			holds(!array_key_exists('areas', $offUnits[0]), 'mergeAreas off: units have no areas key');
			expect('Hofladen', $offUnits[0]['name'], 'mergeAreas off: name is the area name');
		});

		// A closure for the café alone splits the same-hours areas again.
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', [
				'title' => 'Kontakt',
				'bereiche' => [['schluessel' => 'laden', 'name' => 'Hofladen', 'kurzname' => 'Laden'], ['schluessel' => 'cafe', 'name' => 'Hofcafé']],
				'zeiten' => ktMergeRows(['laden', 'cafe']),
				'schliessungen' => [['von' => ktInDays(1), 'bis' => ktInDays(3), 'bereich' => 'cafe']],
			]);
			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.mergeAreas' => true, 'locale' => 'de_DE']);
			$units = $kirby->site()->norenUnits();
			expect(['kontakt#laden', 'kontakt#cafe'], ktIds($units), 'mergeAreas: closure for the café only → two units');
			expect(['Hofladen', 'Hofcafé'], array_map(static fn (array $u) => $u['name'], $units), 'mergeAreas: two units keep their names');
			expect(['laden'], $units[0]['areas'] ?? null, 'mergeAreas: single unit lists its own area');
		});

		// Three areas, the first and the last alike: the group sits at the first area's place, "A und C".
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', [
				'title' => 'Kontakt',
				'bereiche' => [
					['schluessel' => 'laden', 'name' => 'Hofladen', 'kurzname' => 'Laden'],
					['schluessel' => 'cafe', 'name' => 'Hofcafé'],
					['schluessel' => 'werkstatt', 'name' => 'Werkstatt'],
				],
				'zeiten' => array_merge(ktMergeRows(['laden', 'werkstatt']), [['bereich' => 'cafe', 'tag' => 'Montag', 'von1' => '13:00', 'bis1' => '18:00']]),
			]);
			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.mergeAreas' => true, 'locale' => 'de_DE']);
			$units = $kirby->site()->norenUnits();
			expect(['kontakt#laden+werkstatt', 'kontakt#cafe'], ktIds($units), 'mergeAreas: 3 areas, two alike → group first, then the other');
			expect(['Hofladen und Werkstatt', 'Hofcafé'], array_map(static fn (array $u) => $u['name'], $units), 'mergeAreas: "A und C" in German');
		});

		// Three alike, English: "A, B and C".
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', [
				'title' => 'Kontakt',
				'bereiche' => [
					['schluessel' => 'laden', 'name' => 'Hofladen', 'kurzname' => 'Laden'],
					['schluessel' => 'cafe', 'name' => 'Hofcafé'],
					['schluessel' => 'werkstatt', 'name' => 'Werkstatt'],
				],
				'zeiten' => ktMergeRows(['laden', 'cafe', 'werkstatt']),
			]);
			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.mergeAreas' => true, 'sayamaapps.noren.language' => 'en', 'locale' => 'de_DE']);
			$units = $kirby->site()->norenUnits();
			expect(['kontakt#laden+cafe+werkstatt'], ktIds($units), 'mergeAreas: three alike → one unit');
			expect('Hofladen, Hofcafé and Werkstatt', $units[0]['name'] ?? null, 'mergeAreas: "A, B and C" in English');
		});

		// A café closure beyond the lead time: alike today, apart on that date. The
		// status snippet groups at its $now; $merge false keeps every area.
		withSite(function (string $tmp): void {
			$rows = [];
			foreach (['laden', 'cafe'] as $area) {
				foreach (['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'] as $day) {
					$rows[] = ['bereich' => $area, 'tag' => $day, 'von1' => '09:00', 'bis1' => '17:00'];
				}
			}
			noren_page($tmp, '1_kontakt', 'betrieb', [
				'title' => 'Kontakt',
				'bereiche' => [['schluessel' => 'laden', 'name' => 'Hofladen', 'kurzname' => 'Laden'], ['schluessel' => 'cafe', 'name' => 'Hofcafé']],
				'zeiten' => $rows,
				'schliessungen' => [['von' => ktInDays(30), 'bis' => ktInDays(30), 'bereich' => 'cafe', 'anlass' => 'Renovierung']],
			]);
			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.mergeAreas' => true, 'locale' => 'de_DE']);
			$closed = new DateTimeImmutable(ktInDays(30) . ' 12:00', new DateTimeZone('Europe/Berlin'));

			expect(['kontakt#laden+cafe'], ktIds($kirby->site()->norenUnits()), 'mergeAreas: closure beyond the lead time → still one unit today');
			expect(['kontakt#laden', 'kontakt#cafe'], ktIds($kirby->site()->norenUnits($closed)), 'mergeAreas: norenUnits($now) on the closure day → two units');

			$cafe = (string)$kirby->snippet('noren/status', ['only' => 'cafe', 'now' => $closed], true);
			holds(str_contains($cafe, 'data-open="false"'), 'mergeAreas: status $only = cafe, $now = closure day → closed');
			$laden = (string)$kirby->snippet('noren/status', ['only' => 'laden', 'now' => $closed], true);
			holds(str_contains($laden, 'data-open="true"'), 'mergeAreas: status $only = laden, $now = closure day → open');

			$apart = $kirby->site()->norenUnits(null, false);
			expect(['kontakt#laden', 'kontakt#cafe'], ktIds($apart), 'mergeAreas: norenUnits(merge: false) → one unit per area');
			holds(!array_key_exists('areas', $apart[0]), 'mergeAreas: merge false → no areas key, as with the option off');
		});

		// Public holidays: the group is one unit, so its holiday note is shown once and equals the single area's.
		withSite(function (string $tmp): void {
			noren_page($tmp, '1_kontakt', 'betrieb', [
				'title' => 'Kontakt',
				'bereiche' => [['schluessel' => 'laden', 'name' => 'Hofladen'], ['schluessel' => 'cafe', 'name' => 'Hofcafé']],
				'zeiten' => ktMergeRows(['laden', 'cafe']),
			]);
			$kirby = noren_kirby($tmp, ['sayamaapps.noren.active' => true, 'sayamaapps.noren.mergeAreas' => true, 'sayamaapps.noren.holidays' => 'BY', 'locale' => 'de_DE']);
			$units = $kirby->site()->norenUnits();
			expect(1, count($units), 'mergeAreas with holidays: one unit');
			$holidayNotes = static fn (array $table) => array_values(array_filter($table['notes'], static fn (array $n) => $n['type'] === 'holiday'));
			$single = \Sayamaapps\Noren\Locations::table($kirby->site()->norenSchedule('laden'));
			$group = \Sayamaapps\Noren\Locations::table($units[0]['schedule']);
			expect($holidayNotes($single), $holidayNotes($group), 'mergeAreas with holidays: holiday note of the group equals the single area\'s');
			holds(count($holidayNotes($group)) <= 1, 'mergeAreas with holidays: at most one holiday note');
		});
	}
}
