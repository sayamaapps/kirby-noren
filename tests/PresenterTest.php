<?php

/**
 * Presenter view models (SPEC.md §3.5, §9.4): table, closure notes, compact.
 * Kirby-free. The plan is the Muster example: Mo 13–18, Di–Fr 8:30–18,
 * Sa 8–18, public holidays Bavaria.
 */

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__) . '/src/I18n.php';
require_once dirname(__DIR__) . '/src/Log.php';
require_once dirname(__DIR__) . '/src/Schedule.php';
require_once dirname(__DIR__) . '/src/Holidays.php';
require_once dirname(__DIR__) . '/src/Grouping.php';
require_once dirname(__DIR__) . '/src/Presenter.php';

use Noren\Holidays;
use Noren\I18n;
use Noren\Presenter;
use Noren\Schedule;

const MUSTER_HOURS = [
    ['tag' => 'Montag', 'von1' => '13:00', 'bis1' => '18:00'],
    ['tag' => 'Dienstag', 'von1' => '08:30', 'bis1' => '18:00'],
    ['tag' => 'Mittwoch', 'von1' => '08:30', 'bis1' => '18:00'],
    ['tag' => 'Donnerstag', 'von1' => '08:30', 'bis1' => '18:00'],
    ['tag' => 'Freitag', 'von1' => '08:30', 'bis1' => '18:00'],
    ['tag' => 'Samstag', 'von1' => '08:00', 'bis1' => '18:00'],
];

const MUSTER_CLOSURES = [
    ['von' => '2026-12-24', 'bis' => '2026-12-24', 'anlass' => 'Heiligabend', 'oeffnet' => '08:30', 'schliesst' => '12:00'],
    ['von' => '2026-12-31', 'bis' => '2026-12-31', 'anlass' => 'Silvester'],
    ['von' => '2027-02-16', 'bis' => '2027-02-16'],
    ['von' => '2027-03-01', 'bis' => '2027-03-01', 'oeffnet' => '08:00', 'schliesst' => ''],
    ['von' => '2027-08-02', 'bis' => '2027-08-15', 'hinweis' => 'Sommerpause'],
];

function bavaria(): Closure
{
    return Holidays::checker(Holidays::config('BY'));
}

function muster(?Closure $holiday = null): Schedule
{
    return Schedule::fromFields(field(MUSTER_HOURS), field(MUSTER_CLOSURES), null, null, $holiday ?? bavaria(), []);
}

$de = static fn () => I18n::setLanguage('de');
$en = static fn () => I18n::setLanguage('en');
$de();

/* ------------------------------------------------------------------ table */
$s = muster();
$table = Presenter::table($s, at('2026-09-28 10:00'));

expect(7, count($table['days']), 'table: seven days');
expect(true, $table['days'][0]['today'], 'table: Monday is today on 28.09.2026');
expect(['13:00 – 18:00'], $table['days'][0]['hours'], 'table: Monday hours');
expect(true, $table['days'][6]['closed'], 'table: Sunday closed');
expect([], $table['days'][6]['hours'], 'table: Sunday has no hours');
expect(false, $table['days'][6]['today'], 'table: Sunday not today');
expect(['index' => 0, 'name' => 'Montag', 'short' => 'Mo'], only($table['days'][0], ['index', 'name', 'short']), 'table: day names');
expect(
    ['label' => 'Feiertage', 'text' => 'geschlossen', 'today' => false],
    $table['holidays'],
    'table: holidays row',
);
expect([[
    'type' => 'holiday', 'from' => '2026-10-03', 'to' => '2026-10-03',
    'text' => '3.10. Tag der Deutschen Einheit: geschlossen', 'note' => null,
]], $table['notes'], 'table: next holiday note at 28.09.');

$table = Presenter::table($s, at('2026-10-03 10:00'));
expect(false, $table['days'][5]['today'], 'table: Saturday is not today on a closing holiday');
expect(true, $table['holidays']['today'], 'table: holidays row is today on 03.10.');
expect(false, $table['days'][5]['closed'], 'table: Saturday still has its weekly hours');

// Without a checker: no holidays row, no holiday note
$plain = Schedule::fromFields(field(MUSTER_HOURS), field(MUSTER_CLOSURES), null, null, null, []);
$table = Presenter::table($plain, at('2026-09-28 10:00'));
expect(null, $table['holidays'], 'table without checker: holidays null');
expect([], $table['notes'], 'table without checker: no holiday note');

$table = Presenter::table($s, at('2026-09-28 10:00'), ['holidayRow' => false]);
expect(null, $table['holidays'], 'table holidayRow false: holidays null');

$table = Presenter::table($s, at('2026-09-28 10:00'), ['holidayNote' => false]);
expect([], $table['notes'], 'table holidayNote false: no holiday note');

$table = Presenter::table($s, at('2026-09-28 10:00'), ['holidayNoteDays' => 3]);
expect([], $table['notes'], 'table holidayNoteDays 3: holiday 5 days ahead not noted');

// Muster special days at 17.12.2026
$table = Presenter::table($s, at('2026-12-17 10:00'));
expect([
    [
        'type' => 'holiday', 'from' => '2026-12-25', 'to' => '2026-12-25',
        'text' => '25.12. 1. Weihnachtsfeiertag: geschlossen', 'note' => null,
    ],
    [
        'type' => 'special', 'from' => '2026-12-24', 'to' => '2026-12-24',
        'text' => '24.12. Heiligabend: 8:30–12:00 Uhr', 'note' => null,
    ],
    [
        'type' => 'closedDay', 'from' => '2026-12-31', 'to' => '2026-12-31',
        'text' => '31.12. Silvester: geschlossen', 'note' => null,
    ],
], $table['notes'], 'table at 17.12.: holiday, Heiligabend, Silvester');

// Holiday on a rest day / weekday today on a special-hours day
$table = Presenter::table($s, at('2026-12-24 10:00'));
expect(true, $table['days'][3]['today'], 'table: special hours day stays on the weekday row');
expect(false, $table['holidays']['today'], 'table: no holiday on 24.12.');

/* ---------------------------------------------------------- closure notes */
$table = Presenter::table($s, at('2027-02-10 10:00'));
expect([[
    'type' => 'closedDay', 'from' => '2027-02-16', 'to' => '2027-02-16',
    'text' => '16.2.: geschlossen', 'note' => null,
]], $table['notes'], 'closedDay without occasion at 10.02.2027');

$table = Presenter::table($s, at('2027-07-25 10:00'));
expect([[
    'type' => 'closure', 'from' => '2027-08-02', 'to' => '2027-08-15',
    'text' => 'Betriebsferien 2.8. bis 15.8.2027', 'note' => 'Sommerpause',
]], $table['notes'], 'closure range with note at 25.07.2027');

// Half-filled special hours count as a full closure
$table = Presenter::table($s, at('2027-02-22 10:00'));
expect([[
    'type' => 'closedDay', 'from' => '2027-03-01', 'to' => '2027-03-01',
    'text' => '1.3.: geschlossen', 'note' => null,
]], $table['notes'], 'half-filled special hours read as a full closure');

// Special hours without occasion
$special = Schedule::fromFields(field(MUSTER_HOURS), field([
    ['von' => '2026-12-23', 'bis' => '2026-12-23', 'oeffnet' => '09:00', 'schliesst' => '11:00'],
]), null, null, null, []);
$table = Presenter::table($special, at('2026-12-17 10:00'));
expect('23.12. Sonderöffnung: 9:00–11:00 Uhr', $table['notes'][0]['text'], 'special without occasion → Sonderöffnung');

// Two blocks and a note, overnight row
$rows = [
    ['tag' => 'Dienstag', 'von1' => '08:30', 'bis1' => '12:00', 'von2' => '13:00', 'bis2' => '18:00', 'hinweis' => 'Mittagspause'],
    ['tag' => 'Freitag', 'von1' => '18:00', 'bis1' => '02:00'],
];
$shop = Schedule::fromFields(field($rows), null, null, null, null, []);
$table = Presenter::table($shop, at('2026-09-28 10:00'));
expect(['8:30 – 12:00', '13:00 – 18:00'], $table['days'][1]['hours'], 'two blocks → two entries');
expect('Mittagspause', $table['days'][1]['note'], 'note of the weekday');
expect(['18:00 – 2:00'], $table['days'][4]['hours'], 'overnight row shows 2:00');

/* ----------------------------------------------------------------- English */
$en();
$table = Presenter::table($s, at('2026-12-17 10:00'));
expect(
    ['25 Dec Christmas Day: closed', '24 Dec Heiligabend: 8:30–12:00', '31 Dec Silvester: closed'],
    array_column($table['notes'], 'text'),
    'en: holiday, special and closed-day notes',
);
expect(['label' => 'Public holidays', 'text' => 'closed', 'today' => false], $table['holidays'], 'en: holidays row');
expect(['Monday', 'Mon'], [$table['days'][0]['name'], $table['days'][0]['short']], 'en: day names');
expect(['13:00 – 18:00'], Presenter::table($s, at('2026-09-28 10:00'))['days'][0]['hours'], 'en: range text');

$table = Presenter::table($s, at('2027-02-10 10:00'));
expect('16 Feb: closed', $table['notes'][0]['text'], 'en: closedDay without occasion');
$table = Presenter::table($s, at('2027-07-25 10:00'));
expect('Closed for holidays 2 Aug to 15 Aug 2027', $table['notes'][0]['text'], 'en: closure range');
$table = Presenter::table($s, at('2026-09-28 10:00'));
expect('3 Oct German Unity Day: closed', $table['notes'][0]['text'], 'en: holiday note');
$de();

/* ----------------------------------------------------------------- compact */
$s = muster();
$compact = Presenter::compact($s, at('2026-09-28 10:00'));
expect('grouped', $compact['mode'], 'compact: default mode grouped');
expect(['Sa', 'Di–Fr', 'Mo'], array_column($compact['groups'], 'label'), 'compact grouped: Sa, Di–Fr, Mo (earliest opening first)');
expect(['8–18 Uhr', '8:30–18 Uhr', '13–18 Uhr'], array_column($compact['groups'], 'hours'), 'compact grouped: short hours');
expect([], $compact['notes'], 'compact: no closure within 14 days of 28.09.');

$compact = Presenter::compact($s, at('2026-09-28 10:00'), ['mode' => 'consecutive']);
expect('consecutive', $compact['mode'], 'compact: consecutive mode');
expect(['Mo', 'Di – Fr', 'Sa'], array_column($compact['groups'], 'label'), 'compact consecutive: groups in week order');
expect('8:30 – 18:00', $compact['groups'][1]['hours'], 'compact consecutive: block label');

$compact = Presenter::compact($s, at('2026-09-28 10:00'), ['mode' => 'bogus']);
expect('grouped', $compact['mode'], 'compact: unknown mode → grouped');

$compact = Presenter::compact($s, at('2026-12-17 10:00'));
expect(1, count($compact['notes']), 'compact: notes limited to one by default');
expect('24.12. Heiligabend: 8:30–12:00 Uhr', $compact['notes'][0]['text'], 'compact: first note is Heiligabend');

$compact = Presenter::compact($s, at('2026-12-17 10:00'), ['closures' => 2]);
expect(2, count($compact['notes']), 'compact: closures 2 gives two notes');

$compact = Presenter::compact($s, at('2026-12-17 10:00'), ['closures' => 0]);
expect([], $compact['notes'], 'compact: closures 0 gives no notes');

$compact = Presenter::compact($s, at('2026-12-17 10:00'), ['leadDays' => 3, 'closures' => 5]);
expect([], $compact['notes'], 'compact: leadDays 3 hides the 24.12. closure seen 7 days ahead');

$compact = Presenter::compact($s, at('2026-12-17 10:00'), ['leadDays' => 14, 'closures' => 5]);
expect(2, count($compact['notes']), 'compact: leadDays 14 shows Heiligabend and Silvester');


/* --------------------------------------------------------------------------
 * A holiday with special hours keeps "today" on the weekday row (finding 5).
 * 3 October 2026 is a Saturday, a Bavarian holiday.
 * -------------------------------------------------------------------------- */

$hoursOnly = Schedule::fromFields(field(MUSTER_HOURS), field([]), null, null, bavaria(), []);
$tableNoSpecial = Presenter::table($hoursOnly, at('2026-10-03 11:00'), []);
expect(false, $tableNoSpecial['days'][5]['today'], 'holiday without special hours: the Saturday row is not today');
expect(true, $tableNoSpecial['holidays']['today'], 'holiday without special hours: the holidays row is today');

$withSpecial = Schedule::fromFields(
    field(MUSTER_HOURS),
    field([['von' => '2026-10-03', 'bis' => '2026-10-03', 'oeffnet' => '10:00', 'schliesst' => '14:00']]),
    null, null, bavaria(), [],
);
$tableSpecial = Presenter::table($withSpecial, at('2026-10-03 11:00'), []);
expect(true, $tableSpecial['days'][5]['today'], 'holiday with special hours: today stays on the Saturday row');
expect(false, $tableSpecial['holidays']['today'], 'holiday with special hours: the holidays row is not today');

finish('Noren Presenter');
