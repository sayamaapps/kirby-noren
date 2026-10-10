<?php
/**
 * SchemaOrg (SPEC.md §3.6, §9.6): time(), openingHours(),
 * specialOpeningHours() and forSchedule() as plain arrays. Kirby-free.
 *
 * The sample business is the Gasthaus Muster plan in Bavaria, from 2026-10-15:
 * Mon 13:00–18:00, Tue–Fri 8:30–18:00, Sat 8:00–18:00, Sunday closed.
 * The expected holiday list is written by hand from a calendar (Bavaria,
 * default flags: assumption day on, Augsburg off, Corpus Christi on).
 *
 *   ASDF_PHP_VERSION=8.5.3 php tests/SchemaOrgTest.php
 */

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__) . '/src/I18n.php';
require_once dirname(__DIR__) . '/src/Log.php';
require_once dirname(__DIR__) . '/src/Schedule.php';
require_once dirname(__DIR__) . '/src/Holidays.php';
require_once dirname(__DIR__) . '/src/SchemaOrg.php';

use Noren\Holidays;
use Noren\Schedule;
use Noren\SchemaOrg;

$settings = ['timezone' => 'Europe/Berlin', 'holidayEve' => false, 'lookahead' => 90];
$holiday = Holidays::checker(Holidays::config('BY'));

/* --- time() ------------------------------------------------------------ */

expect('00:00', SchemaOrg::time(0), 'time(0)');
expect('08:30', SchemaOrg::time(510), 'time(510)');
expect('13:00', SchemaOrg::time(780), 'time(780)');
expect('18:00', SchemaOrg::time(1080), 'time(1080)');
expect('00:00', SchemaOrg::time(1440), 'time(1440) is midnight, modulo one day');
expect('02:00', SchemaOrg::time(1560), 'time(1560) overnight end is 02:00');

/* --- the Muster plan ----------------------------------------------------- */

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

/* --- openingHours() ------------------------------------------------------ */

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

/* --- specialOpeningHours() ------------------------------------------------ */

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

/* --- forSchedule() ------------------------------------------------------- */

$both = SchemaOrg::forSchedule($muster, $from);
expect(['openingHoursSpecification', 'specialOpeningHoursSpecification'], array_keys($both), 'forSchedule() keys');
expect(6, count($both['openingHoursSpecification']), 'forSchedule(): six weekly entries');
expect(15, count($both['specialOpeningHoursSpecification']), 'forSchedule(): fifteen special entries');
expect($expectedWeek, $both['openingHoursSpecification'], 'forSchedule(): weekly part equals openingHours()');
expect($special, $both['specialOpeningHoursSpecification'], 'forSchedule(): special part equals specialOpeningHours()');

finish('Noren SchemaOrg');
