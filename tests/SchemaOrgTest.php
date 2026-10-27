<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * SchemaOrg (SPEC.md §3.6, §9.6): time(), openingHours(),
 * specialOpeningHours() and forSchedule() as plain arrays. Kirby-free.
 *
 * The sample business is the Muster plan in Bavaria, from 2026-10-15:
 * Mon 13:00–18:00, Tue–Fri 8:30–18:00, Sat 8:00–18:00, Sunday closed.
 * The expected holiday list is written by hand from a calendar (Bavaria,
 * default flags: assumption day on, Augsburg off, Corpus Christi on).
 *
 *   php tests/SchemaOrgTest.php
 */
require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__) . '/src/I18n.php';
use Sayamaapps\Noren\Holidays;
use Sayamaapps\Noren\Schedule;
use Sayamaapps\Noren\SchemaOrg;

#[CoversClass(Sayamaapps\Noren\SchemaOrg::class)]
final class SchemaOrgTest extends TestCase
{
	public function testSuite(): void
	{
		$settings = ['timezone' => 'Europe/Berlin', 'holidayEve' => false, 'lookahead' => 90];
		$holiday = Holidays::checker(Holidays::config('BY'));

		// --- time() ------------------------------------------------------------

		expect('00:00', SchemaOrg::time(0), 'time(0)');
		expect('08:30', SchemaOrg::time(510), 'time(510)');
		expect('13:00', SchemaOrg::time(780), 'time(780)');
		expect('18:00', SchemaOrg::time(1080), 'time(1080)');
		expect('00:00', SchemaOrg::time(1440), 'time(1440) is midnight, modulo one day');
		expect('02:00', SchemaOrg::time(1560), 'time(1560) overnight end is 02:00');

		// --- the Muster plan -----------------------------------------------------

		$musterDays = [
			0 => ['blocks' => [[780, 1080]], 'note' => null],   // Mon 13–18
			1 => ['blocks' => [[510, 1080]], 'note' => null],   // Tue 8:30–18
			2 => ['blocks' => [[510, 1080]], 'note' => null],   // Wed
			3 => ['blocks' => [[510, 1080]], 'note' => null],   // Thu
			4 => ['blocks' => [[510, 1080]], 'note' => null],   // Fri
			5 => ['blocks' => [[480, 1080]], 'note' => null],   // Sat 8–18
			6 => ['blocks' => [], 'note' => null],              // Sun closed
		];
		$musterClosures = [
			['from' => '2026-11-20', 'to' => '2026-11-20'],
			['from' => '2026-12-24', 'to' => '2026-12-24', 'occasion' => 'Heiligabend', 'hours' => [510, 720]],
			['from' => '2026-12-31', 'to' => '2026-12-31', 'occasion' => 'Silvester'],
			['from' => '2027-02-15', 'to' => '2027-02-15', 'occasion' => 'Fortbildung'],
			['from' => '2027-08-02', 'to' => '2027-08-15'],
		];
		$muster = new Schedule($musterDays, $musterClosures, null, $holiday, $settings);

		// --- openingHours() ------------------------------------------------------

		$expectedWeek = [
			['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '13:00', 'closes' => '18:00'],
			['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Tuesday', 'opens' => '08:30', 'closes' => '18:00'],
			['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Wednesday', 'opens' => '08:30', 'closes' => '18:00'],
			['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Thursday', 'opens' => '08:30', 'closes' => '18:00'],
			['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Friday', 'opens' => '08:30', 'closes' => '18:00'],
			['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Saturday', 'opens' => '08:00', 'closes' => '18:00'],
		];
		expect($expectedWeek, SchemaOrg::openingHours($muster), 'Muster plan: six entries, Monday first, Sunday absent');

		// Two blocks on one day, given unsorted: two entries, sorted by start.
		$split = new Schedule([0 => ['blocks' => [[840, 1080], [600, 750]]]], [], null, null, $settings);
		expect(
			[
				['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '10:00', 'closes' => '12:30'],
				['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '14:00', 'closes' => '18:00'],
			],
			SchemaOrg::openingHours($split),
			'two blocks on one day: two entries in start order'
		);

		// Overnight Friday 18:00–02:00, read through fromFields (default overnight limit).
		$overnight = Schedule::fromFields(field([['tag' => 'Freitag', 'von1' => '18:00', 'bis1' => '02:00']]), null, null, null, null, $settings);
		expect(
			[['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Friday', 'opens' => '18:00', 'closes' => '02:00']],
			SchemaOrg::openingHours($overnight),
			'overnight Friday: opens 18:00, closes 02:00'
		);

		expect([], SchemaOrg::openingHours(new Schedule([], [], null, null, $settings)), 'no weekly plan: no entries (closed, not an error)');

		// --- specialOpeningHours() ------------------------------------------------

		$from = at('2026-10-15');
		$special = SchemaOrg::specialOpeningHours($muster, $from);

		// 1. All five closures and special days, no lead time, in start order.
		$closureEntries = [
			['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-11-20', 'validThrough' => '2026-11-20', 'opens' => '00:00', 'closes' => '00:00'],
			['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-12-24', 'validThrough' => '2026-12-24', 'opens' => '08:30', 'closes' => '12:00'],
			['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-12-31', 'validThrough' => '2026-12-31', 'opens' => '00:00', 'closes' => '00:00'],
			['@type' => 'OpeningHoursSpecification', 'validFrom' => '2027-02-15', 'validThrough' => '2027-02-15', 'opens' => '00:00', 'closes' => '00:00'],
			['@type' => 'OpeningHoursSpecification', 'validFrom' => '2027-08-02', 'validThrough' => '2027-08-15', 'opens' => '00:00', 'closes' => '00:00'],
		];

		// 2. Bavarian holidays from 2026-10-15 for 365 days, on days the plan is open.
		//    Calendar: 2026-11-01 is a Sunday, 2027-08-15 a Sunday, 2027-10-03 a
		//    Sunday (all three skipped). Easter 2027 is 28 March.
		$holidayEntries = [
			['2026-12-25'], // Christmas Day, Friday
			['2026-12-26'], // St Stephen's Day, Saturday
			['2027-01-01'], // New Year, Friday
			['2027-01-06'], // Epiphany, Wednesday
			['2027-03-26'], // Good Friday (Easter − 2)
			['2027-03-29'], // Easter Monday
			['2027-05-01'], // Labour Day, Saturday
			['2027-05-06'], // Ascension (Easter + 39), Thursday
			['2027-05-17'], // Whit Monday (Easter + 50)
			['2027-05-27'], // Corpus Christi (Easter + 60), Thursday
		];
		$expectedHolidays = array_map(
			static fn (array $d) => ['@type' => 'OpeningHoursSpecification', 'validFrom' => $d[0], 'validThrough' => $d[0], 'opens' => '00:00', 'closes' => '00:00'],
			$holidayEntries
		);

		expect(array_merge($closureEntries, $expectedHolidays), $special, 'Muster special hours from 2026-10-15: closures first, then Bavarian holidays on open days');
		expect(15, count($special), 'fifteen entries in total');

		$dates = array_column($special, 'validFrom');
		holds(in_array('2026-12-25', $dates, true) && in_array('2026-12-26', $dates, true), 'Christmas 2026 (Friday and Saturday, open days) is listed');
		holds(!in_array('2026-11-01', $dates, true), 'All Saints 2026 falls on a Sunday and is not listed');

		// Heiligabend: opens 08:30, closes 12:00, as the closure entry says.
		$christmasEve = array_values(array_filter($special, static fn (array $e) => $e['validFrom'] === '2026-12-24'));
		expect(['08:30', '12:00'], [$christmasEve[0]['opens'] ?? null, $christmasEve[0]['closes'] ?? null], 'Heiligabend opens 08:30, closes 12:00');

		// Closures ending before $from are not listed.
		$earlier = new Schedule([], [['from' => '2026-10-01', 'to' => '2026-10-02', 'occasion' => 'Vorbei']], null, null, $settings);
		expect([], SchemaOrg::specialOpeningHours($earlier, $from, 0), 'a closure that ended before $from is not listed');

		// Hoffest special on Saturday 3 October 2026 (a Bavarian holiday): listed once, as special hours.
		$hoffest = new Schedule(
			$musterDays,
			[['from' => '2026-10-03', 'to' => '2026-10-03', 'occasion' => 'Hoffest', 'hours' => [600, 1080]]],
			null,
			$holiday,
			$settings
		);
		$hoffestList = SchemaOrg::specialOpeningHours($hoffest, at('2026-10-01'));
		$onHoffestDay = array_values(array_filter($hoffestList, static fn (array $e) => $e['validFrom'] === '2026-10-03'));
		expect(1, count($onHoffestDay), 'Hoffest on 3.10.: the date appears exactly once');
		expect(
			['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-10-03', 'validThrough' => '2026-10-03', 'opens' => '10:00', 'closes' => '18:00'],
			$onHoffestDay[0] ?? null,
			'Hoffest on 3.10.: special hours 10:00–18:00, not closed'
		);
		expect(11, count($hoffestList), 'Hoffest from 2026-10-01: the Hoffest plus ten Bavarian holidays (Sundays and the holiday with special hours skipped)');

		// A full day is 00:00 to 23:59: 00:00 to 00:00 would mean closed in Google's notation.
		$fullDays = new Schedule([0 => ['blocks' => [[0, 1440]]], 1 => ['blocks' => [[1080, 1440]]]], [], null, null, $settings);
		expect(
			[
				['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '00:00', 'closes' => '23:59'],
				['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Tuesday', 'opens' => '18:00', 'closes' => '00:00'],
			],
			SchemaOrg::openingHours($fullDays),
			'full day is 00:00–23:59, a block until midnight stays 18:00–00:00'
		);

		$fullSpecial = new Schedule(
			$musterDays,
			[
				['from' => '2026-10-20', 'to' => '2026-10-20', 'occasion' => 'Hoffest', 'hours' => [0, 1440]],
				['from' => '2026-10-21', 'to' => '2026-10-21', 'occasion' => 'Inventur'],
			],
			null,
			null,
			$settings
		);
		$fullSpecialList = SchemaOrg::specialOpeningHours($fullSpecial, at('2026-10-15'));
		expect(
			['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-10-20', 'validThrough' => '2026-10-20', 'opens' => '00:00', 'closes' => '23:59'],
			$fullSpecialList[0] ?? null,
			'special hours covering a full day are 00:00–23:59'
		);
		expect(
			['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-10-21', 'validThrough' => '2026-10-21', 'opens' => '00:00', 'closes' => '00:00'],
			$fullSpecialList[1] ?? null,
			'a closed entry stays 00:00–00:00'
		);

		// --- a block that runs past midnight from 00:00 ----------------------------

		// Two rows on Monday, 00:00–18:00 and 18:00–02:00, merge to [0, 1560]: the
		// full Monday, and Tuesday until 02:00. The 02:00 must not be dropped.
		$overhang = new Schedule([0 => ['blocks' => [[0, 1080], [1080, 1560]]]], [], null, null, $settings);
		$overhangSpecs = SchemaOrg::openingHours($overhang);
		expect(
			[
				['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '00:00', 'closes' => '23:59'],
				['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Tuesday', 'opens' => '00:00', 'closes' => '02:00'],
			],
			$overhangSpecs,
			'Monday 00:00 to Tuesday 02:00 (merged rows): full Monday plus Tuesday 00:00–02:00'
		);

		// The same plan written as two days gives the same entries.
		$twoDays = new Schedule([0 => ['blocks' => [[0, 1440]]], 1 => ['blocks' => [[0, 120]]]], [], null, null, $settings);
		expect(SchemaOrg::openingHours($twoDays), $overhangSpecs, 'Monday full day + Tuesday 00:00–02:00 equals the merged block');

		// Sunday's overhang continues into Monday (weekday index wraps).
		$sunday = new Schedule([6 => ['blocks' => [[0, 1560]]]], [], null, null, $settings);
		expect(
			[
				['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Sunday', 'opens' => '00:00', 'closes' => '23:59'],
				['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '00:00', 'closes' => '02:00'],
			],
			SchemaOrg::openingHours($sunday),
			'Sunday 00:00 to Monday 02:00: the overhang lands on Monday'
		);

		// Overhang longer than a day: every full day is listed.
		$twoFull = new Schedule([0 => ['blocks' => [[0, 2880]]]], [], null, null, $settings);
		expect(
			[
				['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '00:00', 'closes' => '23:59'],
				['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Tuesday', 'opens' => '00:00', 'closes' => '23:59'],
			],
			SchemaOrg::openingHours($twoFull),
			'Monday 00:00 to Wednesday 00:00: Monday and Tuesday are both full days'
		);

		// An evening block that runs past midnight keeps its start day (unchanged).
		$evening = new Schedule([4 => ['blocks' => [[1080, 1560]]]], [], null, null, $settings);
		expect(
			[['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Friday', 'opens' => '18:00', 'closes' => '02:00']],
			SchemaOrg::openingHours($evening),
			'Friday 18:00–02:00 stays one Friday entry'
		);

		// --- forSchedule() -------------------------------------------------------

		$both = SchemaOrg::forSchedule($muster, $from);
		expect(['openingHoursSpecification', 'specialOpeningHoursSpecification'], array_keys($both), 'forSchedule() keys');
		expect(6, count($both['openingHoursSpecification']), 'forSchedule(): six weekly entries');
		expect(15, count($both['specialOpeningHoursSpecification']), 'forSchedule(): fifteen special entries');
		expect($expectedWeek, $both['openingHoursSpecification'], 'forSchedule(): weekly part equals openingHours()');
		expect($special, $both['specialOpeningHoursSpecification'], 'forSchedule(): special part equals specialOpeningHours()');
	}
}
