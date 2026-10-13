<?php

/**
 * Tests for Noren\Schedule — the opening-hours logic, without Kirby (SPEC.md §3.1, §9.1).
 *
 * Covers: reading the content fields (rows appended, areas, legacy vm_/nm_
 * row, half-filled rows, notes), blocks, the status order (overnight spill-over,
 * closures, holidays, blocks, next opening), overnight blocks, the eve-of-holiday
 * notice, English texts, closure labels, timezones, dayIndex, parseTime,
 * lookahead, closures per area, upcoming holidays, week/maintained/sorting.
 *
 * Ports every check of kirby-starter's OeffnungszeitenTest.php (same sample
 * business, same dates, same German strings) plus the extra check of
 * the shop site's OeffnungszeitenTest.php ("Ohne Feiertagsprüfung wäre derselbe
 * Samstag offen"), renamed per SPEC §8. Regression guards kept: no memoisation
 * across areas, appended rows, DST, running closure, 14-day lead boundary.
 *
 * Run:  cd kirby-noren && ASDF_PHP_VERSION=8.5.3 php tests/ScheduleTest.php
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/Log.php';
require_once __DIR__ . '/../src/Schedule.php';
require_once __DIR__ . '/../src/Holidays.php';

use Noren\{I18n, Schedule, Holidays};

I18n::setLanguage('de');

/* --------------------------------------------------------------------------
 * Sample business (starter): shop "laden" Mo 13–18, Di–Fr 8:30–18, Sa 8–18;
 * café "cafe" Di–Sa 9–17; Monday and Sunday closed for both.
 * Monday of the shop is written with the legacy vm_/nm_ fields on purpose:
 * that row is needed in several checks below.
 * -------------------------------------------------------------------------- */

$zeilen = [];
$zeilen[] = ['bereich' => 'laden', 'tag' => 'Montag', 'vm_von' => '', 'vm_bis' => '', 'nm_von' => '13:00', 'nm_bis' => '18:00'];
foreach (['Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag'] as $t) {
    $zeilen[] = ['bereich' => 'laden', 'tag' => $t, 'von1' => '08:30', 'bis1' => '18:00'];
    $zeilen[] = ['bereich' => 'cafe',  'tag' => $t, 'von1' => '09:00', 'bis1' => '17:00'];
}
$zeilen[] = ['bereich' => 'laden', 'tag' => 'Samstag', 'von1' => '08:00', 'bis1' => '18:00'];
$zeilen[] = ['bereich' => 'cafe',  'tag' => 'Samstag', 'von1' => '09:00', 'bis1' => '17:00'];

$feld  = field($zeilen);
$laden = Schedule::fromFields($feld, null, 'laden');
$cafe  = Schedule::fromFields($feld, null, 'cafe');

/* --------------------------------------------------------------------------
 * Muster business (SPEC §9.1): Mo 13–18, Di–Fr 8:30–18, Sa 8–18, Sunday closed,
 * one row per weekday, Bavarian holidays.
 * -------------------------------------------------------------------------- */

$musterZeilen = [
    ['tag' => 'Montag',     'von1' => '13:00', 'bis1' => '18:00'],
    ['tag' => 'Dienstag',   'von1' => '08:30', 'bis1' => '18:00'],
    ['tag' => 'Mittwoch',   'von1' => '08:30', 'bis1' => '18:00'],
    ['tag' => 'Donnerstag', 'von1' => '08:30', 'bis1' => '18:00'],
    ['tag' => 'Freitag',    'von1' => '08:30', 'bis1' => '18:00'],
    ['tag' => 'Samstag',    'von1' => '08:00', 'bis1' => '18:00'],
];
$musterFeld = field($musterZeilen);
$checkerBY  = Holidays::checker(Holidays::config('BY'));

// Special days: Heiligabend 8:30–12, Silvester (closed), 16.2.2027 (closed),
// 2.–15.8.2027 (closure), 1.3.2027 half-filled (therefore closed).
$sonderFeld = field([
    ['von' => '2026-12-24', 'bis' => '2026-12-24', 'anlass' => 'Heiligabend', 'oeffnet' => '08:30', 'schliesst' => '12:00'],
    ['von' => '2026-12-31', 'bis' => '2026-12-31', 'anlass' => 'Silvester'],
    ['von' => '2027-02-16', 'bis' => '2027-02-16'],
    ['von' => '2027-08-02', 'bis' => '2027-08-15'],
    ['von' => '2027-03-01', 'bis' => '2027-03-01', 'oeffnet' => '10:00'],
]);
$sonder = Schedule::fromFields($musterFeld, $sonderFeld, null, 'Betriebsferien', $checkerBY);

// Holiday-less copy of the same plan (shop: "without the holiday check").
$musterOhneFeiertage = Schedule::fromFields($musterFeld, null, null, 'Betriebsferien');
$muster = Schedule::fromFields($musterFeld, null, null, 'Betriebsferien', $checkerBY);

/* ==========================================================================
 * 1. Starter: status at fixed moments
 * ========================================================================== */

expect('Jetzt geschlossen', $laden->status(at('2026-07-06 10:00'))['text'], 'Montagvormittag: jetzt geschlossen');
expect('Jetzt geschlossen', $laden->status(at('2026-07-06 20:00'))['text'], 'Nach Feierabend: jetzt geschlossen');
expect('Heute geschlossen', $laden->status(at('2026-07-12 10:00'))['text'], 'Sonntag: heute geschlossen');

// Monday 6 July 2026, 14:00
$s = $laden->status(at('2026-07-06 14:00'));
expect(true, $s['open'], 'Laden Mo 14:00 ist offen');
expect('noch bis 18:00 Uhr', $s['detail'], 'Laden Mo 14:00 nennt das Ende');

// Exactly at closing minute: closed
$s = $laden->status(at('2026-07-06 18:00'));
expect(false, $s['open'], 'Laden Mo 18:00 ist zu');
expect('öffnet morgen um 8:30 Uhr', $s['detail'], 'Laden Mo 18:00 nennt morgen');

// Exactly at opening minute: open
expect(true, $laden->status(at('2026-07-06 13:00'))['open'], 'Laden Mo 13:00 ist offen');

// One area open, the other closed (the reason areas exist)
expect(true, $laden->status(at('2026-07-06 14:00'))['open'], 'Mo 14:00 Laden offen');
expect(false, $cafe->status(at('2026-07-06 14:00'))['open'], 'Mo 14:00 Café zu');

// Same moment, different next opening
$sl = $laden->status(at('2026-07-06 10:00'));
$sc = $cafe->status(at('2026-07-06 10:00'));
expect('öffnet heute um 13:00 Uhr', $sl['detail'], 'Mo 10:00 Laden öffnet heute');
expect('öffnet morgen um 9:00 Uhr', $sc['detail'], 'Mo 10:00 Café öffnet morgen');

// Sunday 5 July 2026: weekday names differ for the two areas
$sl = $laden->status(at('2026-07-05 11:00'));
$sc = $cafe->status(at('2026-07-05 11:00'));
expect('öffnet morgen um 13:00 Uhr', $sl['detail'], 'So Laden öffnet morgen (Mo)');
expect('öffnet am Dienstag um 9:00 Uhr', $sc['detail'], 'So Café öffnet am Dienstag');

/* ==========================================================================
 * 2. Starter: closures (Betriebsferien) and the running closure
 * ========================================================================== */

$ferien = field([
    ['von' => '2026-07-06', 'bis' => '2026-07-10', 'hinweis' => ''],
]);
$mitFerien = Schedule::fromFields($feld, $ferien, 'laden');
$s = $mitFerien->status(at('2026-07-06 14:00'));
expect(false, $s['open'], 'Betriebsferien schlagen die Zeiten');
expect('Betriebsferien', $s['text'], 'Betriebsferien heißen so');
expect('wieder ab 11.7.', $s['detail'], 'Betriebsferien: wieder ab bis+1');

// Reopening skips the rest day and a directly following closure.
// Closure to Saturday 11.7.; Sunday closed → Monday 13.7.
$bisSamstag = Schedule::fromFields($feld, field([
    ['von' => '2026-07-06', 'bis' => '2026-07-11', 'hinweis' => ''],
]), 'laden');
expect('wieder ab 13.7.', $bisSamstag->status(at('2026-07-08 10:00'))['detail'], 'Ferienende vor Ruhetag: nächster offener Tag');

// Directly following second closure Mon–Tue 13.–14.7. → Wednesday 15.7.
$verkettet = Schedule::fromFields($feld, field([
    ['von' => '2026-07-06', 'bis' => '2026-07-11', 'hinweis' => ''],
    ['von' => '2026-07-13', 'bis' => '2026-07-14', 'hinweis' => ''],
]), 'laden');
expect('wieder ab 15.7.', $verkettet->status(at('2026-07-08 10:00'))['detail'], 'Anschließende Schließung wird übersprungen');

// Without a maintained plan no reopening day is known: do not invent one.
$ohnePlan = new Schedule(
    array_fill(0, 7, ['blocks' => [], 'note' => null]),
    [['from' => '2026-07-06', 'to' => '2026-07-11', 'note' => null]],
);
expect('', $ohnePlan->status(at('2026-07-08 10:00'))['detail'], 'Ohne Plan: kein Wiederöffnungsdatum');

// A closure that spans the next opening is skipped.
$ferien2 = field([
    ['von' => '2026-07-06', 'bis' => '2026-07-08', 'hinweis' => ''],
]);
$mitFerien2 = Schedule::fromFields($feld, $ferien2, 'laden');
expect('öffnet am Donnerstag um 8:30 Uhr', $mitFerien2->status(at('2026-07-05 11:00'))['detail'], 'nächste Öffnung überspringt die Ferien');

/* ==========================================================================
 * 3. Starter: half-filled rows, no field, unknown area
 * ========================================================================== */

$halb = field([
    ['bereich' => 'laden', 'tag' => 'Sonntag', 'vm_von' => '10:00', 'vm_bis' => ''],
]);
$halbS = Schedule::fromFields($halb, null, 'laden');
expect([], $halbS->blocks(6), 'halbe Zeile erzeugt keinen Block');
expect(false, $halbS->maintained(), 'halbe Zeile gilt nicht als gepflegt');

$leer = Schedule::fromFields(null);
expect(false, $leer->status(at('2026-07-06 10:00'))['open'], 'ohne Feld: geschlossen');
expect([], $leer->blocks(0), 'ohne Feld: keine Blöcke');
expect(false, $leer->maintained(), 'ohne Feld: nicht gepflegt');
expect('', $leer->status(at('2026-07-06 10:00'))['detail'], 'ohne Feld: kein Detail');

expect('8:30', Schedule::formatTime(510), 'formatTime 8:30');
expect('0:05', Schedule::formatTime(5), 'formatTime 0:05');
expect('18:00', Schedule::formatTime(1080), 'formatTime 18:00');

$fremd = Schedule::fromFields($feld, null, 'werkstatt');
expect(false, $fremd->maintained(), 'unbekannter Bereich: nicht gepflegt');

/* ==========================================================================
 * 4. Starter: regression guard — no memoisation per page
 * ========================================================================== */

// Both plans come from the SAME field and the same page. A cache keyed on the
// page id alone would return the same object twice.
expect(false, $laden->blocks(0) === $cafe->blocks(0), 'Laden und Café sind nicht derselbe Plan');
expect(1, count($laden->blocks(0)), 'Laden hat montags einen Block');
expect(0, count($cafe->blocks(0)), 'Café hat montags keinen Block');

// Calling in the other order gives the same answers.
$cafeZuerst = Schedule::fromFields($feld, null, 'cafe');
$ladenZuerst = Schedule::fromFields($feld, null, 'laden');
expect(0, count($cafeZuerst->blocks(0)), 'Reihenfolge: Café zuerst, hat montags keinen Block');
expect(1, count($ladenZuerst->blocks(0)), 'Reihenfolge: Laden danach, hat montags einen Block');

/* ==========================================================================
 * 5. Starter: several rows per weekday are appended
 * ========================================================================== */

$geteilt = field([
    ['bereich' => 'laden', 'tag' => 'Montag', 'von1' => '08:00', 'bis1' => '12:00'],
    ['bereich' => 'laden', 'tag' => 'Montag', 'von1' => '14:00', 'bis1' => '18:00'],
]);
$g = Schedule::fromFields($geteilt, null, 'laden');
expect(2, count($g->blocks(0)), 'zwei Zeilen ergeben zwei Blöcke');
expect([[480, 720], [840, 1080]], $g->blocks(0), 'Blöcke sind chronologisch');
expect('Jetzt geschlossen', $g->status(at('2026-07-06 13:00'))['text'], 'Mittagspause: jetzt geschlossen');
expect(false, $g->status(at('2026-07-06 13:00'))['open'], 'Mittagspause gilt als geschlossen');
expect('öffnet heute um 14:00 Uhr', $g->status(at('2026-07-06 13:00'))['detail'], 'Mittagspause nennt die Nachmittagszeit');

/* ==========================================================================
 * 6. Starter: DST change (29 March 2026)
 * ========================================================================== */

// modify('+1 day') across the switch must not move the next opening.
expect('öffnet morgen um 13:00 Uhr', $laden->status(at('2026-03-29 11:00'))['detail'], 'Zeitumstellung: nächste Öffnung stimmt');

/* ==========================================================================
 * 7. Starter: upcoming closures (constructor input, no lead time)
 * ========================================================================== */

$mitFerienKonstruktor = new Schedule(
    array_fill(0, 7, ['blocks' => [[480, 720]], 'note' => null]),
    [
        ['from' => '2026-08-01', 'to' => '2026-08-14', 'note' => 'Sommerpause'],
        ['from' => '2026-12-24', 'to' => '2027-01-06', 'note' => null],
        ['from' => '2026-05-01', 'to' => '2026-05-03', 'note' => 'vorbei'],
    ],
    'Betriebsferien',
);

$kommend = $mitFerienKonstruktor->upcomingClosures(at('2026-08-10'));
expect(2, count($kommend), 'Vergangene Schließung fällt heraus');

// The running closure (began 1 Aug, still on 10 Aug) must stay in the list:
// a filter `from >= today` would drop exactly the closure people are standing in front of.
expect('2026-08-01', $kommend[0]['from'], 'Laufende Schließung bleibt drin');
expect('2026-12-24', $kommend[1]['from'], 'Aufsteigend nach Beginn');
expect('Sommerpause', $kommend[0]['note'], 'Hinweis wird durchgereicht');

expect(1, count((new Schedule(
    array_fill(0, 7, ['blocks' => [], 'note' => null]),
    [['from' => '2026-08-01', 'to' => '2026-08-14', 'note' => null]],
))->upcomingClosures(at('2026-08-14'))), 'Am letzten Tag noch enthalten');
expect(0, count((new Schedule(
    array_fill(0, 7, ['blocks' => [], 'note' => null]),
    [['from' => '2026-08-01', 'to' => '2026-08-14', 'note' => null]],
))->upcomingClosures(at('2026-08-15'))), 'Einen Tag später nicht mehr');

expect('Betriebsferien', $mitFerienKonstruktor->closureLabel(), 'Label ist auslesbar');

/* ==========================================================================
 * 8. Starter: public holidays (Bavaria)
 * ========================================================================== */

$s = $muster->status(at('2026-10-03 10:00'));
expect(
    ['open' => false, 'text' => 'Heute Feiertag', 'detail' => 'öffnet am Montag um 13:00 Uhr'],
    only($s, ['open', 'text', 'detail']),
    'Samstag, 3.10.2026, 10 Uhr: Feiertag statt geöffnet',
);
expect('öffnet am Montag um 13:00 Uhr', $muster->status(at('2026-10-02 19:00'))['detail'], 'Freitag 2.10. abends: öffnet nicht „morgen“ (Feiertag)');
expect('Heute Feiertag', $muster->status(at('2027-03-26 09:00'))['text'], 'Karfreitag 2027 (26.3.)');
expect('öffnet am Samstag um 8:00 Uhr', $muster->status(at('2027-03-25 19:00'))['detail'], 'Gründonnerstag abends: Karfreitag übersprungen, Karsamstag ist kein Feiertag');
expect('öffnet am Dienstag um 8:30 Uhr', $muster->status(at('2027-03-27 19:00'))['detail'], 'Karsamstag abends: Ostermontag übersprungen');
expect(false, $muster->status(at('2026-08-15 10:00'))['open'], 'Mariä Himmelfahrt 2026 (Samstag) geschlossen');
expect('Heute geschlossen', $muster->status(at('2026-11-01 10:00'))['text'], 'Feiertag am Sonntag: gewohntes „Heute geschlossen“');
expect(true, $muster->status(at('2026-10-10 10:00'))['open'], 'Normaler Samstag bleibt offen');

expect(
    [['date' => '2026-10-03', 'name' => 'Tag der Deutschen Einheit']],
    $muster->upcomingHolidays(at('2026-09-27'), 60),
    'Kommende Feiertage ab 27.9.2026, nur an Öffnungstagen, 60 Tage',
);
expect(
    ['2026-12-25', '2026-12-26'],
    array_column($muster->upcomingHolidays(at('2026-12-20'), 10), 'date'),
    'Weihnachten 2026: Fr 25. und Sa 26.',
);
expect(null, $muster->holidayOn(at('2026-12-24')), 'Heiligabend ist kein gesetzlicher Feiertag');

// Without the holiday option: the same Saturday stays open (shop extra check).
expect(true, $musterOhneFeiertage->status(at('2026-10-03 10:00'))['open'], 'Ohne Option: Samstag 3.10.2026 bleibt offen');
expect('Jetzt geöffnet', $musterOhneFeiertage->status(at('2026-10-03 10:00'))['text'], 'Ohne Option: kein „Heute Feiertag“');
expect(null, $musterOhneFeiertage->holidayOn(at('2026-10-03')), 'Ohne Option: holidayOn() liefert nie einen Namen');
expect([], $musterOhneFeiertage->upcomingHolidays(at('2026-09-27'), 60), 'Ohne Option: keine kommenden Feiertage');
expect(true, $musterOhneFeiertage->status(at('2026-10-03 10:00'))['open'], 'Ohne Feiertagsprüfung wäre derselbe Samstag offen');

/* ==========================================================================
 * 9. Starter: special days (Muster) and their names
 * ========================================================================== */

// Notice only 14 days ahead; without lead time (schema.org) everything.
expect([], $sonder->upcomingClosures(at('2026-10-15'), 14), 'Oktober: kein Sondertag in der 14-Tage-Vorschau');
expect(5, count($sonder->upcomingClosures(at('2026-10-15'))), 'Oktober: strukturierte Daten kennen alle');
expect(['2026-12-24', '2026-12-31'], array_column($sonder->upcomingClosures(at('2026-12-17'), 14), 'from'), '17.12.: Heiligabend und Silvester in Sicht');
expect(['2026-12-24'], array_column($sonder->upcomingClosures(at('2026-12-10'), 14), 'from'), '10.12.: genau 14 Tage vorher erscheint Heiligabend');
expect([], $sonder->upcomingClosures(at('2026-12-09'), 14), '9.12.: 15 Tage vorher noch nicht');

// Label: occasion, else "Geschlossen" for one day, else the closure label.
$eintraege = $sonder->upcomingClosures(at('2026-10-15'));
expect('Silvester', $sonder->label($eintraege[1]), 'Bezeichnung mit Anlass');
expect('Geschlossen', $sonder->label($eintraege[2]), 'Einzelner Tag ohne Anlass ist keine Betriebsferien');
expect('Betriebsferien', $sonder->label($eintraege[4]), 'Mehrere Tage ohne Anlass: Projektbegriff');
expect(null, $eintraege[3]['hours'], 'Halb ausgefüllte Zeiten gelten als geschlossen');

// Silvester: a single day reads like a holiday; next opening across New Year.
expect(
    ['open' => false, 'text' => 'Silvester: geschlossen', 'detail' => 'öffnet am Samstag um 8:00 Uhr'],
    only($sonder->status(at('2026-12-31 10:00')), ['open', 'text', 'detail']),
    'Silvester 10 Uhr',
);
expect('Heute geschlossen', $sonder->status(at('2027-02-16 10:00'))['text'], 'Einzelner Tag ohne Anlass');
expect('öffnet am Samstag um 8:00 Uhr', $sonder->status(at('2026-12-30 19:00'))['detail'], 'Tag vor Silvester abends: überspringt Silvester und Neujahr');

// Christmas Eve shortened: open until 12, closed afterwards; the evening before shows the right time.
expect(
    ['open' => true, 'text' => 'Jetzt geöffnet', 'detail' => 'noch bis 12:00 Uhr (Heiligabend)'],
    only($sonder->status(at('2026-12-24 10:00')), ['open', 'text', 'detail']),
    'Heiligabend 10 Uhr offen, mit Anlass',
);
expect(false, $sonder->status(at('2026-12-24 13:00'))['open'], 'Heiligabend 13 Uhr geschlossen');
expect(null, $sonder->closureOn(at('2026-12-24')), 'Heiligabend ist keine Schließung');
expect([[510, 720]], $sonder->blocksOn(at('2026-12-24')), 'Blöcke am Heiligabend');
expect('öffnet morgen um 8:30 Uhr', $sonder->status(at('2026-12-23 19:00'))['detail'], '23.12. abends: morgen um 8:30');

// Multi-day closure unchanged.
expect(
    ['open' => false, 'text' => 'Betriebsferien', 'detail' => 'wieder ab 16.8.'],
    only($sonder->status(at('2027-08-05 10:00')), ['open', 'text', 'detail']),
    'Sommerferien 2027',
);

// Special opening on a public holiday beats the holiday.
$feiertagOffen = Schedule::fromFields($musterFeld, field([
    ['von' => '2026-10-03', 'bis' => '2026-10-03', 'anlass' => 'Hoffest', 'oeffnet' => '10:00', 'schliesst' => '16:00'],
]), null, 'Betriebsferien', $checkerBY);
expect(true, $feiertagOffen->status(at('2026-10-03 11:00'))['open'], 'Hoffest am 3.10. offen');

/* ==========================================================================
 * 10. New: full status shape
 * ========================================================================== */

expect(
    [
        'open' => true, 'text' => 'Jetzt geöffnet', 'detail' => 'noch bis 18:00 Uhr',
        'state' => 'open', 'until' => '18:00', 'next' => null, 'occasion' => null, 'holiday' => null,
    ],
    $muster->status(at('2026-07-06 14:00')),
    'Full status shape: Mon 14:00 open',
);

$s = $muster->status(at('2026-07-06 10:00'));
expect('closed', $s['state'], 'Mon 10:00 state closed');
expect('2026-07-06T13:00', $s['next'], 'Mon 10:00 next opening');

$s = $muster->status(at('2026-10-03 10:00'));
expect('holiday', $s['state'], 'Sat 3.10. state holiday');
expect('Tag der Deutschen Einheit', $s['holiday'], 'Sat 3.10. holiday name');
expect('2026-10-05T13:00', $s['next'], 'Sat 3.10. next opening Mon 13:00');

$s = $sonder->status(at('2027-08-05 10:00'));
expect('closure', $s['state'], 'Summer closure: state closure');
expect('2027-08-16T13:00', $s['next'], 'Summer closure: next opening Mon 16.8.2027 13:00');

/* ==========================================================================
 * 11. New: overnight blocks (SPEC §3.1.4)
 * ========================================================================== */

$nachtFeld = field([['tag' => 'Freitag', 'von1' => '18:00', 'bis1' => '02:00']]);
$nacht = Schedule::fromFields($nachtFeld);
expect([[1080, 1560]], $nacht->blocks(4), 'Overnight: Freitag 18:00–02:00 = [1080, 1560]');
expect('öffnet heute um 18:00 Uhr', $nacht->status(at('2026-10-09 17:00'))['detail'], 'Overnight: Fri 17:00 öffnet heute');

$s = $nacht->status(at('2026-10-09 23:00'));
expect(true, $s['open'], 'Overnight: Fri 23:00 open');
expect('noch bis 2:00 Uhr', $s['detail'], 'Overnight: Fri 23:00 noch bis 2:00');
expect('2:00', $s['until'], 'Overnight: Fri 23:00 until 2:00');

expect(true, $nacht->status(at('2026-10-10 01:00'))['open'], 'Overnight: Sat 01:00 open');
expect(true, $nacht->status(at('2026-10-10 01:59'))['open'], 'Overnight: Sat 01:59 open');

$s = $nacht->status(at('2026-10-10 02:00'));
expect(false, $s['open'], 'Overnight: Sat 02:00 closed');
expect('Heute geschlossen', $s['text'], 'Overnight: Sat 02:00 text');
expect('öffnet am Freitag um 18:00 Uhr', $s['detail'], 'Overnight: Sat 02:00 detail');

expect('öffnet morgen um 18:00 Uhr', $nacht->status(at('2026-10-08 23:00'))['detail'], 'Overnight: Thu 23:00 öffnet morgen');

// Until midnight: [1080, 1440]
$bisMitternacht = Schedule::fromFields(field([['tag' => 'Freitag', 'von1' => '18:00', 'bis1' => '00:00']]));
expect([[1080, 1440]], $bisMitternacht->blocks(4), 'Bis Mitternacht: [1080, 1440]');
expect('noch bis 24:00 Uhr', $bisMitternacht->status(at('2026-10-09 23:59'))['detail'], 'Bis Mitternacht: Fri 23:59 noch bis 24:00');

// Ends at 08:00 with the default limit 06:00: a typo, ignored.
$tippfehler = field([['tag' => 'Freitag', 'von1' => '18:00', 'bis1' => '08:00']]);
expect([], Schedule::fromFields($tippfehler)->blocks(4), 'Tippfehler 18:00–08:00 wird ignoriert');
expect(
    [[1080, 1920]],
    Schedule::fromFields($tippfehler, null, null, null, null, ['overnightUntil' => 600])->blocks(4),
    'Overnight limit 600: 18:00–08:00 = [1080, 1920]',
);
expect(
    [],
    Schedule::fromFields(field([['tag' => 'Freitag', 'von1' => '18:00', 'bis1' => '02:00']]), null, null, null, null, ['overnightUntil' => null])->blocks(4),
    'Overnight off: 18:00–02:00 ignored',
);

// Full closure on Friday: Saturday 01:00 is not open (the block belongs to Friday).
$nachtFerien = Schedule::fromFields($nachtFeld, field([['von' => '2026-10-09', 'bis' => '2026-10-09']]));
expect(false, $nachtFerien->status(at('2026-10-10 01:00'))['open'], 'Friday full closure: Sat 01:00 not open');

// Closure starting Saturday: Sat 01:00 still open (belongs to Friday).
$nachtSamstagZu = Schedule::fromFields($nachtFeld, field([['von' => '2026-10-10', 'bis' => '2026-10-12']]));
expect(true, $nachtSamstagZu->status(at('2026-10-10 01:00'))['open'], 'Closure from Saturday: Sat 01:00 still open');

// Good Friday 2026 (3 April) with BY: Saturday 01:00 not open.
$nachtKarfreitag = Schedule::fromFields($nachtFeld, null, null, null, $checkerBY);
expect(false, $nachtKarfreitag->status(at('2026-04-04 01:00'))['open'], 'Good Friday 2026 with BY: Sat 01:00 not open');

// Special day with overnight hours on a day with no weekly plan.
$silvesterNacht = Schedule::fromFields($nachtFeld, field([
    ['von' => '2026-12-31', 'bis' => '2026-12-31', 'anlass' => 'Silvesterparty', 'oeffnet' => '20:00', 'schliesst' => '03:00'],
]), null, null, $checkerBY);
$s = $silvesterNacht->status(at('2027-01-01 01:30'));
expect(
    ['open' => true, 'text' => 'Jetzt geöffnet', 'detail' => 'noch bis 3:00 Uhr (Silvesterparty)'],
    only($s, ['open', 'text', 'detail']),
    'Silvesterparty 20–3: 1.1. 01:30 offen (Neujahr, BY)',
);

/* ==========================================================================
 * 12. New: eve-of-holiday notice (SPEC §3.1.3 step 5, opt-in)
 * ========================================================================== */

$musterEve = Schedule::fromFields($musterFeld, null, null, null, $checkerBY, ['holidayEve' => true]);

$s = $musterEve->status(at('2026-10-02 19:00'));
expect(
    'morgen Feiertag (Tag der Deutschen Einheit) · öffnet am Montag um 13:00 Uhr',
    $s['detail'],
    'Eve notice: Fri 19:00 names the holiday',
);
expect('Tag der Deutschen Einheit', $s['holiday'], 'Eve notice: holiday name in field holiday');

$s = $musterEve->status(at('2026-10-02 07:00'));
expect('öffnet heute um 8:30 Uhr', $s['detail'], 'Eve notice: Fri 07:00 öffnet heute (no notice before the opening)');
expect(null, $s['holiday'], 'Eve notice: Fri 07:00 holiday null');

expect(
    'öffnet am Montag um 13:00 Uhr',
    $musterEve->status(at('2026-10-31 19:00'))['detail'],
    'Eve notice: Sat 31.10. 19:00 (1.11. is a Sunday) no notice',
);
expect(null, $musterEve->status(at('2026-10-31 19:00'))['holiday'], 'Eve notice: Sat 31.10. holiday null');

// Tomorrow has special hours: no notice (the holiday is open).
$musterEveHoffest = Schedule::fromFields($musterFeld, field([
    ['von' => '2026-10-03', 'bis' => '2026-10-03', 'anlass' => 'Hoffest', 'oeffnet' => '10:00', 'schliesst' => '16:00'],
]), null, null, $checkerBY, ['holidayEve' => true]);
$s = $musterEveHoffest->status(at('2026-10-02 19:00'));
expect('öffnet morgen um 10:00 Uhr', $s['detail'], 'Eve notice: tomorrow with special hours → no notice');
expect(null, $s['holiday'], 'Eve notice: tomorrow with special hours → holiday null');

// Tomorrow closed: no notice.
$musterEveZu = Schedule::fromFields($musterFeld, field([
    ['von' => '2026-10-03', 'bis' => '2026-10-03'],
]), null, null, $checkerBY, ['holidayEve' => true]);
$s = $musterEveZu->status(at('2026-10-02 19:00'));
expect('öffnet am Montag um 13:00 Uhr', $s['detail'], 'Eve notice: tomorrow with closure → no notice');
expect(null, $s['holiday'], 'Eve notice: tomorrow with closure → holiday null');

// Without the setting the ported check stays as it was.
expect('öffnet am Montag um 13:00 Uhr', $muster->status(at('2026-10-02 19:00'))['detail'], 'Eve notice off: plain detail');

/* ==========================================================================
 * 13. New: closure label, timezone
 * ========================================================================== */

$praxisurlaub = Schedule::fromFields($musterFeld, field([['von' => '2027-08-02', 'bis' => '2027-08-15']]), null, 'Praxisurlaub');
expect('Praxisurlaub', $praxisurlaub->status(at('2027-08-05 10:00'))['text'], 'closureLabel Praxisurlaub: range closure text');

// Timezone: a UTC clock reading is converted into the schedule's zone (Berlin).
expect(true, $muster->status(at('2026-07-06 12:00', 'UTC'))['open'], 'UTC 12:00 = Berlin 14:00: shop open');
expect('Europe/Berlin', (new Schedule([]))->timezone()->getName(), 'Default timezone Europe/Berlin');

// New York schedule: Berlin 14:00 Monday is 08:00 in New York → closed.
$newYork = Schedule::fromFields($musterFeld, null, null, null, null, ['timezone' => 'America/New_York']);
expect('America/New_York', $newYork->timezone()->getName(), 'Timezone America/New_York is reported');
$s = $newYork->status(at('2026-07-06 14:00'));
expect(false, $s['open'], 'New York: Berlin 14:00 = NY 08:00 closed');
expect('öffnet heute um 13:00 Uhr', $s['detail'], 'New York: Berlin 14:00 next opening today 13:00 (NY time)');
// Berlin 19:00 = New York 13:00 → open until 18:00 (NY).
expect(true, $newYork->status(at('2026-07-06 19:00'))['open'], 'New York: Berlin 19:00 = NY 13:00 open');
expect('noch bis 18:00 Uhr', $newYork->status(at('2026-07-06 19:00'))['detail'], 'New York: Berlin 19:00 until 18:00');

/* ==========================================================================
 * 14. New: dayIndex, parseTime, blocks/notes
 * ========================================================================== */

expect(0, Schedule::dayIndex('Montag'), 'dayIndex Montag = 0');
expect(0, Schedule::dayIndex('monday'), 'dayIndex monday = 0');
expect(6, Schedule::dayIndex('SONNTAG'), 'dayIndex SONNTAG = 6');
expect(6, Schedule::dayIndex('7'), 'dayIndex "7" = 6');
expect(null, Schedule::dayIndex('0'), 'dayIndex "0" = null');
expect(null, Schedule::dayIndex('Mon'), 'dayIndex "Mon" = null');
expect(null, Schedule::dayIndex(''), 'dayIndex "" = null');

$tuesdayRow = Schedule::fromFields(field([['tag' => 'Tuesday', 'von1' => '10:00', 'bis1' => '12:00']]));
expect([[600, 720]], $tuesdayRow->blocks(1), 'English day name "Tuesday" is read');

expect(480, Schedule::parseTime('08:00:00'), 'parseTime 08:00:00 = 480');
expect(485, Schedule::parseTime('8:05'), 'parseTime 8:05 = 485');
expect(null, Schedule::parseTime('24:00'), 'parseTime 24:00 = null');
expect(null, Schedule::parseTime('8:5'), 'parseTime 8:5 = null');
expect(null, Schedule::parseTime(''), 'parseTime "" = null');
expect(null, Schedule::parseTime('abc'), 'parseTime abc = null');

$notizen = Schedule::fromFields(field([
    ['tag' => 'Samstag', 'von1' => '08:00', 'bis1' => '12:00', 'nm_hinweis' => 'warme Küche bis 14 Uhr'],
]));
expect('warme Küche bis 14 Uhr', $notizen->note(5), 'Legacy nm_hinweis is used as note');
expect(null, $notizen->note(0), 'No note on a day without one');

// Constructor sorts blocks; week() has 7 entries.
$sortiert = new Schedule([0 => ['blocks' => [[900, 1000], [480, 600]], 'note' => null]]);
expect([[480, 600], [900, 1000]], $sortiert->blocks(0), 'Constructor sorts blocks');
expect(7, count($sortiert->week()), 'week() has 7 entries');
expect(true, $sortiert->maintained(), 'maintained() with one block');
expect(false, (new Schedule([]))->maintained(), 'maintained() false without blocks');

expect(true, $muster->hasHolidays(), 'hasHolidays() true with a checker');
expect(false, $musterOhneFeiertage->hasHolidays(), 'hasHolidays() false without a checker');

/* ==========================================================================
 * 15. New: lookahead
 * ========================================================================== */

// Plan: Monday only. Closure 5.–14.7. (10 days) ends on a Tuesday. Next Monday is 20.7.,
// six days after the end → outside a lookahead of 5 days → no detail.
$montagNur = [0 => ['blocks' => [[780, 1080]], 'note' => null]];
$kurzeSuche = new Schedule($montagNur, [['from' => '2026-07-05', 'to' => '2026-07-14', 'note' => null]], null, null, ['lookahead' => 5]);
expect('', $kurzeSuche->status(at('2026-07-11 10:00'))['detail'], 'lookahead 5 with a 10-day closure: range detail empty');
$weiteSuche = new Schedule($montagNur, [['from' => '2026-07-05', 'to' => '2026-07-14', 'note' => null]]);
expect('wieder ab 20.7.', $weiteSuche->status(at('2026-07-11 10:00'))['detail'], 'default lookahead 90 finds Monday 20.7.');

/* ==========================================================================
 * 16. New: closures per area
 * ========================================================================== */

$bereichsFeld = field([
    ['von' => '2026-07-07', 'bis' => '2026-07-07', 'bereich' => 'cafe', 'anlass' => 'Kuchenbuffet'],
    ['von' => '2026-07-09', 'bis' => '2026-07-09', 'bereich' => ''],
]);
$bereichLaden = Schedule::fromFields($feld, $bereichsFeld, 'laden');
$bereichCafe = Schedule::fromFields($feld, $bereichsFeld, 'cafe');

expect(true, $bereichLaden->status(at('2026-07-07 10:00'))['open'], 'Café-Schließung: Laden am Dienstag offen');
expect(false, $bereichCafe->status(at('2026-07-07 10:00'))['open'], 'Café-Schließung: Café am Dienstag zu');
expect(false, $bereichLaden->status(at('2026-07-09 10:00'))['open'], 'Leerer Bereich: Laden am Donnerstag zu');
expect(false, $bereichCafe->status(at('2026-07-09 10:00'))['open'], 'Leerer Bereich: Café am Donnerstag zu');
expect('cafe', $bereichCafe->closures()[0]['area'], 'closures()[0] area is cafe');
expect(1, count($bereichLaden->closures()), 'Shop schliesst nur die Schließung ohne Bereich');

/* ==========================================================================
 * 17. New: upcoming holidays skip special hours (Hoffest)
 * ========================================================================== */

$hoffest = Schedule::fromFields($musterFeld, field([
    ['von' => '2026-10-03', 'bis' => '2026-10-03', 'anlass' => 'Hoffest', 'oeffnet' => '10:00', 'schliesst' => '16:00'],
]), null, null, $checkerBY);
expect([], $hoffest->upcomingHolidays(at('2026-09-27'), 60), 'Hoffest on 3.10.: upcomingHolidays is empty');

/* ==========================================================================
 * 18. English (SPEC §9.1)
 * ========================================================================== */

I18n::setLanguage('en');

$s = $muster->status(at('2026-07-06 14:00'));
expect('Open now', $s['text'], 'EN: Mon 14:00 Open now');
expect('open until 18:00', $s['detail'], 'EN: Mon 14:00 open until 18:00');

$s = $cafe->status(at('2026-07-05 11:00'));
expect('Closed today', $s['text'], 'EN: Sunday café Closed today');
expect('opens on Tuesday at 9:00', $s['detail'], 'EN: Sunday café opens on Tuesday at 9:00');

$ferienEn = Schedule::fromFields($musterFeld, field([['von' => '2026-07-06', 'bis' => '2026-07-10']]));
$s = $ferienEn->status(at('2026-07-08 10:00'));
expect('Closed for holidays', $s['text'], 'EN: closure range text');
expect('back on 11 Jul', $s['detail'], 'EN: back on 11 Jul');

expect('Public holiday today', $muster->status(at('2026-10-03 10:00'))['text'], 'EN: holiday today');
expect('Silvester: closed', $sonder->status(at('2026-12-31 10:00'))['text'], 'EN: Silvester: closed');

$sommerEn = Schedule::fromFields($musterFeld, field([['von' => '2027-08-02', 'bis' => '2027-08-15']]));
$s = $sommerEn->status(at('2027-08-01 10:00'));
expect('Closed today', $s['text'], 'EN: Sunday before the summer closure');
expect('opens on 16 Aug at 13:00', $s['detail'], 'EN: date 15 days ahead: opens on 16 Aug at 13:00');

I18n::setLanguage('de');

/* ------------------------------------------------------------------------ */


/* --------------------------------------------------------------------------
 * Runs and merged blocks (review findings 4, 5, 18, 20).
 * -------------------------------------------------------------------------- */

// Touching rows (morning and afternoon split at 12:00) are one opening.
$touching = new Schedule([0 => ['blocks' => [[480, 720], [720, 1080]]]]);
expect([[480, 1080]], $touching->blocks(0), 'touching blocks merge into one block');
expect(I18n::t('detail.until', ['time' => '18:00']), $touching->status(at('2026-10-05 11:00'))['detail'], 'touching blocks: open until the real end, 18:00');

// Overlapping rows merge too, whatever their order in the content.
$overlap = new Schedule([0 => ['blocks' => [[780, 1080], [540, 1020]]]]);
expect([[540, 1080]], $overlap->blocks(0), 'overlapping blocks merge, order does not matter');

// Friday 18:00–00:00 and Saturday 00:00–02:00: one run until 2:00.
$midnight = new Schedule([4 => ['blocks' => [[1080, 1440]]], 5 => ['blocks' => [[0, 120]]]]);
expect(I18n::t('detail.until', ['time' => '2:00']), $midnight->status(at('2026-10-09 23:30'))['detail'], 'run across midnight: Friday 23:30 open until 2:00');
expect(I18n::t('detail.until', ['time' => '2:00']), $midnight->status(at('2026-10-10 00:30'))['detail'], 'run across midnight: Saturday 00:30 open until 2:00');

// Friday 20:00–03:00 and Saturday 02:00–10:00: at 02:30 the Saturday block extends the run.
$spill = new Schedule([4 => ['blocks' => [[1200, 1620]]], 5 => ['blocks' => [[120, 600]]]]);
expect(I18n::t('detail.until', ['time' => '10:00']), $spill->status(at('2026-10-10 02:30'))['detail'], 'overnight run extended by the next morning block: until 10:00');

// The same, but Saturday is closed: the overnight block ends at 3:00.
$spillClosed = new Schedule(
    [4 => ['blocks' => [[1200, 1620]]], 5 => ['blocks' => [[120, 600]]]],
    [['from' => '2026-10-10', 'to' => '2026-10-10']],
);
expect(I18n::t('detail.until', ['time' => '3:00']), $spillClosed->status(at('2026-10-10 02:30'))['detail'], 'closed day does not extend the overnight run: until 3:00');

// 18:00–06:00 is an overnight block, the limit itself included.
expect([1080, 1800], Schedule::block(1080, 360, 360), '18:00–06:00 is overnight with the default limit 06:00');
expect(null, Schedule::block(1080, 361, 360), '18:00–06:01 is beyond the limit: ignored');

// Closure dates must be Y-m-d and in order. Anything else is skipped.
$logFile = tempnam(sys_get_temp_dir(), 'noren-schedule-log-');
$previousLog = (string) ini_get('error_log');
ini_set('error_log', $logFile);
\Noren\Log::forget();
$badDates = Schedule::fromFields(field([]), field([
    ['von' => '24.12.2026', 'bis' => '31.12.2026'],
    ['von' => '2026-12-31', 'bis' => '2026-12-24'],
    ['von' => '2026-02-30', 'bis' => '2026-03-01'],
    ['von' => '2026-12-24 00:00:00', 'bis' => '2026-12-24'],
]));
ini_set('error_log', $previousLog);
$badLog = (string) file_get_contents($logFile);
unlink($logFile);
expect(1, count($badDates->closures()), 'closures: only the valid row (a timestamp is cut to its date) is kept');
expect('2026-12-24', $badDates->closures()[0]['from'], 'closures: the timestamp row keeps its date');
expect(3, substr_count($badLog, 'skipped — dates must be Y-m-d'), 'closures: the three invalid rows are logged');
expect(false, $badDates->specialOn(at('2026-12-28 10:00')) !== null, 'closures: nothing is applied from a malformed date');

// Weekday wording up to six days ahead, date wording from seven days (SPEC: "am Montag" within the week).
$monday = new Schedule([0 => ['blocks' => [[480, 720]]]]);
expect(I18n::t('detail.opensWeekday', ['weekday' => I18n::day(0), 'time' => '8:00']), $monday->status(at('2026-10-06 13:00'))['detail'], 'opening exactly 6 days ahead: weekday wording');
expect(I18n::t('detail.opensDate', ['date' => I18n::date('2026-10-12'), 'time' => '8:00']), $monday->status(at('2026-10-05 13:00'))['detail'], 'opening exactly 7 days ahead: date wording');

finish('Noren Schedule');
