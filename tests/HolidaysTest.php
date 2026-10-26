<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Holidays (src/Holidays.php, SPEC.md §3.2 and §9.2): Easter Sunday, the rule
 * table of all 16 states (the 2026 lists are written out by hand from the
 * rules and the official sources, never printed from the implementation),
 * introduction years, the switches, config() normalisation, extra days,
 * checker() and state().
 * Kirby-free.
 *
 *   php tests/HolidaysTest.php
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/Log.php';
require_once __DIR__ . '/../src/Schedule.php';
require_once __DIR__ . '/../src/Holidays.php';
use Noren\{I18n, Holidays};
/** Message of the InvalidArgumentException thrown by $fn, or null when nothing was thrown. */
function holidaysThrows(callable $fn): ?string
{
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return $e->getMessage();
    }
    return null;
}

#[CoversClass(Noren\Holidays::class)]
final class HolidaysTest extends TestCase
{
    public function testSuite(): void
    {
        I18n::setLanguage('de');




        $berlin = new DateTimeZone('Europe/Berlin');

        /* ---------------------------------------------------------------------------
         * Easter Sunday (Meeus/Jones/Butcher). Dates checked
         * against published Easter tables) plus the SPEC §9.2 extremes.
         * ------------------------------------------------------------------------- */

        $easter = [
            1818 => '1818-03-22', // earliest Easter of the Gregorian calendar
            1943 => '1943-04-25',
            2000 => '2000-04-23',
            2019 => '2019-04-21',
            2024 => '2024-03-31',
            2025 => '2025-04-20',
            2026 => '2026-04-05',
            2027 => '2027-03-28',
            2028 => '2028-04-16',
            2029 => '2029-04-01',
            2030 => '2030-04-21',
            2035 => '2035-03-25',
            2038 => '2038-04-25',
            2049 => '2049-04-18',
            2285 => '2285-03-22',
        ];
        foreach ($easter as $year => $date) {
            expect($date, Holidays::easterSunday($year)->format('Y-m-d'), "Ostersonntag $year");
        }
        expect('Europe/Berlin', Holidays::easterSunday(2026)->getTimezone()->getName(), 'Ostersonntag: Zeitzone Europe/Berlin');
        expect('UTC', Holidays::easterSunday(2026, 'UTC')->getTimezone()->getName(), 'Ostersonntag: Zeitzone als Argument');

        /* ---------------------------------------------------------------------------
         * Bavaria 2026, the complete list (names verbatim).
         * ------------------------------------------------------------------------- */

        $bayern2026 = [
            '2026-01-01' => 'Neujahr',
            '2026-01-06' => 'Heilige Drei Könige',
            '2026-04-03' => 'Karfreitag',
            '2026-04-06' => 'Ostermontag',
            '2026-05-01' => 'Tag der Arbeit',
            '2026-05-14' => 'Christi Himmelfahrt',
            '2026-05-25' => 'Pfingstmontag',
            '2026-06-04' => 'Fronleichnam',
            '2026-08-15' => 'Mariä Himmelfahrt',
            '2026-10-03' => 'Tag der Deutschen Einheit',
            '2026-11-01' => 'Allerheiligen',
            '2026-12-25' => '1. Weihnachtsfeiertag',
            '2026-12-26' => '2. Weihnachtsfeiertag',
        ];
        expect($bayern2026, Holidays::forYear(2026, Holidays::config('BY')), 'Bayern 2026 vollständig');
        expect(12, count(Holidays::forYear(2026, Holidays::config(['state' => 'BY', 'assumption' => false]))), 'Ohne Mariä Himmelfahrt: 12 Tage');
        expect(
            array_diff_key($bayern2026, ['2026-08-15' => true]),
            Holidays::forYear(2026, Holidays::config(['state' => 'BY', 'assumption' => false])),
            'Ohne Mariä Himmelfahrt: genau die übrigen 12'
        );
        expect(
            'Augsburger Friedensfest',
            Holidays::forYear(2026, Holidays::config(['state' => 'BY', 'augsburg' => true]))['2026-08-08'] ?? null,
            'Friedensfest nur mit Schalter'
        );
        expect(
            'Augsburger Friedensfest',
            Holidays::forYear(2026, Holidays::config(['land' => 'bayern', 'friedensfest' => true]))['2026-08-08'] ?? null,
            'Friedensfest mit altem Schlüssel friedensfest'
        );
        expect(
            null,
            Holidays::forYear(2026, Holidays::config('BY'))['2026-08-08'] ?? null,
            'Friedensfest ohne Schalter fehlt'
        );
        expect(true, isset(Holidays::forYear(2027, Holidays::config('BY'))['2027-05-27']), 'Fronleichnam 2027-05-27');

        /* ---------------------------------------------------------------------------
         * All 16 states, 2026. Written out from the rule table (SPEC §3.2) and the
         * official lists of the state governments and the Wikipedia article
         * "Gesetzliche Feiertage in Deutschland". Ascending by date.
         * Common to all states: Neujahr, Karfreitag, Ostermontag, Tag der Arbeit,
         * Christi Himmelfahrt, Pfingstmontag, Tag der Deutschen Einheit, both
         * Christmas days.
         * ------------------------------------------------------------------------- */

        $states2026 = [
            'BW' => [ // 12
                '2026-01-01' => 'Neujahr',
                '2026-01-06' => 'Heilige Drei Könige',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-06-04' => 'Fronleichnam',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-11-01' => 'Allerheiligen',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'BY' => $bayern2026, // 13
            'BE' => [ // 10
                '2026-01-01' => 'Neujahr',
                '2026-03-08' => 'Internationaler Frauentag',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'BB' => [ // 12
                '2026-01-01' => 'Neujahr',
                '2026-04-03' => 'Karfreitag',
                '2026-04-05' => 'Ostersonntag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-24' => 'Pfingstsonntag',
                '2026-05-25' => 'Pfingstmontag',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-10-31' => 'Reformationstag',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'HB' => [ // 10
                '2026-01-01' => 'Neujahr',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-10-31' => 'Reformationstag',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'HH' => [ // 10
                '2026-01-01' => 'Neujahr',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-10-31' => 'Reformationstag',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'HE' => [ // 10
                '2026-01-01' => 'Neujahr',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-06-04' => 'Fronleichnam',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'MV' => [ // 11
                '2026-01-01' => 'Neujahr',
                '2026-03-08' => 'Internationaler Frauentag',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-10-31' => 'Reformationstag',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'NI' => [ // 10
                '2026-01-01' => 'Neujahr',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-10-31' => 'Reformationstag',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'NW' => [ // 11
                '2026-01-01' => 'Neujahr',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-06-04' => 'Fronleichnam',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-11-01' => 'Allerheiligen',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'RP' => [ // 11
                '2026-01-01' => 'Neujahr',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-06-04' => 'Fronleichnam',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-11-01' => 'Allerheiligen',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'SL' => [ // 12
                '2026-01-01' => 'Neujahr',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-06-04' => 'Fronleichnam',
                '2026-08-15' => 'Mariä Himmelfahrt',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-11-01' => 'Allerheiligen',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'SN' => [ // 11
                '2026-01-01' => 'Neujahr',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-10-31' => 'Reformationstag',
                '2026-11-18' => 'Buß- und Bettag',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'ST' => [ // 11
                '2026-01-01' => 'Neujahr',
                '2026-01-06' => 'Heilige Drei Könige',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-10-31' => 'Reformationstag',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'SH' => [ // 10
                '2026-01-01' => 'Neujahr',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-10-31' => 'Reformationstag',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
            'TH' => [ // 11
                '2026-01-01' => 'Neujahr',
                '2026-04-03' => 'Karfreitag',
                '2026-04-06' => 'Ostermontag',
                '2026-05-01' => 'Tag der Arbeit',
                '2026-05-14' => 'Christi Himmelfahrt',
                '2026-05-25' => 'Pfingstmontag',
                '2026-09-20' => 'Weltkindertag',
                '2026-10-03' => 'Tag der Deutschen Einheit',
                '2026-10-31' => 'Reformationstag',
                '2026-12-25' => '1. Weihnachtsfeiertag',
                '2026-12-26' => '2. Weihnachtsfeiertag',
            ],
        ];

        $expectedCounts = [
            'BW' => 12, 'BY' => 13, 'BE' => 10, 'BB' => 12, 'HB' => 10, 'HH' => 10, 'HE' => 10, 'MV' => 11,
            'NI' => 10, 'NW' => 11, 'RP' => 11, 'SL' => 12, 'SN' => 11, 'ST' => 11, 'SH' => 10, 'TH' => 11,
        ];
        expect(16, count(Holidays::STATES), 'Holidays::STATES hat 16 Länder');
        expect(array_keys($expectedCounts), Holidays::STATES, 'Holidays::STATES in Reihenfolge der SPEC');
        foreach (Holidays::STATES as $code) {
            expect($expectedCounts[$code], count($states2026[$code]), "$code: Liste von Hand hat die Sollanzahl");
            expect(
                $states2026[$code],
                Holidays::forYear(2026, Holidays::config($code)),
                "$code 2026 vollständig"
            );
            expect(
                $expectedCounts[$code],
                count(Holidays::forYear(2026, Holidays::config($code))),
                "$code 2026: Anzahl"
            );
            expect(
                $states2026[$code],
                Holidays::forYear(2026, Holidays::config(strtolower($code))),
                "$code 2026 mit Kleinbuchstaben"
            );
            expect(
                $states2026[$code],
                Holidays::forYear(2026, Holidays::config('DE-' . $code)),
                "$code 2026 mit DE-Präfix"
            );
        }

        /* ---------------------------------------------------------------------------
         * Switches and their "no effect" cases.
         * ------------------------------------------------------------------------- */

        $bb2026NoSundays = array_diff_key($states2026['BB'], ['2026-04-05' => true, '2026-05-24' => true]);
        expect(
            $bb2026NoSundays,
            Holidays::forYear(2026, Holidays::config(['state' => 'BB', 'sundays' => false])),
            'BB ohne Sonntagsfeiertage: 10 Tage'
        );
        expect(10, count($bb2026NoSundays), 'BB ohne Sonntage: Sollanzahl 10');
        expect('Ostersonntag', Holidays::forYear(2026, Holidays::config('BB'))['2026-04-05'] ?? null, 'BB Ostersonntag per Default');
        expect('Pfingstsonntag', Holidays::forYear(2026, Holidays::config('BB'))['2026-05-24'] ?? null, 'BB Pfingstsonntag per Default');
        expect(null, Holidays::forYear(2026, Holidays::config('BY'))['2026-04-05'] ?? null, 'BY kennt keinen Ostersonntag');
        expect(null, Holidays::forYear(2026, Holidays::config('SN'))['2026-05-24'] ?? null, 'SN kennt keinen Pfingstsonntag');

        $sn2026NoCorpus = $states2026['SN'];
        expect(
            $sn2026NoCorpus,
            Holidays::forYear(2026, Holidays::config(['state' => 'SN', 'corpusChristi' => false])),
            'SN ohne Fronleichnam per Default'
        );
        $sn2026Corpus = $states2026['SN'] + ['2026-06-04' => 'Fronleichnam'];
        ksort($sn2026Corpus);
        expect(
            $sn2026Corpus,
            Holidays::forYear(2026, Holidays::config(['state' => 'SN', 'corpusChristi' => true])),
            'SN mit Schalter corpusChristi: Fronleichnam am 4.6.'
        );
        expect(12, count(Holidays::forYear(2026, Holidays::config(['state' => 'SN', 'corpusChristi' => true]))), 'SN mit Fronleichnam: 12');
        $th2026Corpus = $states2026['TH'] + ['2026-06-04' => 'Fronleichnam'];
        ksort($th2026Corpus);
        expect(
            $th2026Corpus,
            Holidays::forYear(2026, Holidays::config(['state' => 'TH', 'corpusChristi' => true])),
            'TH mit Schalter corpusChristi'
        );
        expect(
            $states2026['TH'],
            Holidays::forYear(2026, Holidays::config('TH')),
            'TH ohne Schalter: keine Fronleichnam'
        );
        expect(
            $states2026['BY'],
            Holidays::forYear(2026, Holidays::config(['state' => 'BY', 'corpusChristi' => false])),
            'corpusChristi false in BY: keine Wirkung'
        );
        expect(
            $states2026['SL'],
            Holidays::forYear(2026, Holidays::config(['state' => 'SL', 'assumption' => false])),
            'assumption false in SL: keine Wirkung (Mariä Himmelfahrt bleibt)'
        );
        expect(
            $states2026['BW'],
            Holidays::forYear(2026, Holidays::config(['state' => 'BW', 'augsburg' => true])),
            'augsburg in BW: keine Wirkung'
        );
        expect(
            $states2026['HE'],
            Holidays::forYear(2026, Holidays::config(['state' => 'HE', 'augsburg' => true, 'assumption' => true, 'sundays' => true])),
            'Schalter ohne Wirkung in HE'
        );

        /* ---------------------------------------------------------------------------
         * Introduction years.
         * ------------------------------------------------------------------------- */

        $ids = static fn (int $year, string $state) => Holidays::ids($year, $state);

        expect(false, isset($ids(2016, 'HB')['2016-10-31']), 'Reformationstag HB 2016: nein');
        expect('reformation', $ids(2017, 'HB')['2017-10-31'] ?? null, 'Reformationstag HB 2017: ja');
        expect('reformation', $ids(2018, 'HB')['2018-10-31'] ?? null, 'Reformationstag HB 2018: ja');
        expect(false, isset($ids(2016, 'NI')['2016-10-31']), 'Reformationstag NI 2016: nein');
        expect('reformation', $ids(2018, 'NI')['2018-10-31'] ?? null, 'Reformationstag NI 2018: ja');
        expect('reformation', $ids(2018, 'HH')['2018-10-31'] ?? null, 'Reformationstag HH 2018: ja');
        expect('reformation', $ids(2018, 'SH')['2018-10-31'] ?? null, 'Reformationstag SH 2018: ja');
        expect('reformation', $ids(2017, 'BY')['2017-10-31'] ?? null, 'Reformationstag 2017 bundesweit: BY');
        expect(false, isset($ids(2018, 'BY')['2018-10-31']), 'Reformationstag BY 2018: nein');
        expect('reformation', $ids(2016, 'SN')['2016-10-31'] ?? null, 'Reformationstag SN immer (2016)');
        expect('reformation', $ids(2016, 'BB')['2016-10-31'] ?? null, 'Reformationstag BB immer (2016)');

        expect(false, isset($ids(2018, 'TH')['2018-09-20']), 'Weltkindertag TH 2018: nein');
        expect('childrensDay', $ids(2019, 'TH')['2019-09-20'] ?? null, 'Weltkindertag TH 2019: ja');
        expect(false, isset($ids(2019, 'SN')['2019-09-20']), 'Weltkindertag nur TH: SN 2019 nein');

        expect(false, isset($ids(2018, 'BE')['2018-03-08']), 'Frauentag BE 2018: nein');
        expect('womensDay', $ids(2019, 'BE')['2019-03-08'] ?? null, 'Frauentag BE 2019: ja');
        expect(false, isset($ids(2022, 'MV')['2022-03-08']), 'Frauentag MV 2022: nein');
        expect('womensDay', $ids(2023, 'MV')['2023-03-08'] ?? null, 'Frauentag MV 2023: ja');
        expect(false, isset($ids(2026, 'BB')['2026-03-08']), 'Frauentag nicht in BB');
        expect(false, isset($ids(2026, 'NW')['2026-03-08']), 'Frauentag nicht in NW');

        expect('liberationDay', $ids(2020, 'BE')['2020-05-08'] ?? null, 'Tag der Befreiung BE 2020: ja');
        expect(false, isset($ids(2021, 'BE')['2021-05-08']), 'Tag der Befreiung BE 2021: nein');
        expect('liberationDay', $ids(2025, 'BE')['2025-05-08'] ?? null, 'Tag der Befreiung BE 2025: ja');
        expect(false, isset($ids(2020, 'BB')['2020-05-08']), 'Tag der Befreiung nie in BB (2020)');
        expect(false, isset($ids(2025, 'BB')['2025-05-08']), 'Tag der Befreiung nie in BB (2025)');
        expect(false, isset($ids(2026, 'BE')['2026-05-08']), 'Tag der Befreiung BE 2026: nein');

        /* Buß- und Bettag: Wednesday 16–22 November, only Saxony. */

        $repentance = [
            2026 => '2026-11-18',
            2027 => '2027-11-17',
            2028 => '2028-11-22',
            2029 => '2029-11-21',
            2030 => '2030-11-20',
        ];
        foreach ($repentance as $year => $date) {
            expect($date, Holidays::repentanceDay($year), "Buß- und Bettag $year");
            expect('repentance', $ids($year, 'SN')[$date] ?? null, "Buß- und Bettag $year in SN");
            foreach (Holidays::STATES as $code) {
                if ($code !== 'SN') {
                    expect(false, in_array('repentance', $ids($year, $code), true), "Buß- und Bettag $year nur SN, nicht $code");
                }
            }
        }

        /* ---------------------------------------------------------------------------
         * Extra days (forYear).
         * ------------------------------------------------------------------------- */

        $withExtras = Holidays::config(['state' => 'BY', 'extra' => ['06-24' => 'Stadtfest', '2027-07-02' => 'Dorffest']]);
        $bayern2026Extra = $bayern2026 + ['2026-06-24' => 'Stadtfest'];
        ksort($bayern2026Extra);
        expect($bayern2026Extra, Holidays::forYear(2026, $withExtras), 'Extra MM-DD recurs 2026, Y-m-d nur 2027');
        $bayern2027Extra = [
            '2027-01-01' => 'Neujahr',
            '2027-01-06' => 'Heilige Drei Könige',
            '2027-03-26' => 'Karfreitag',
            '2027-03-29' => 'Ostermontag',
            '2027-05-01' => 'Tag der Arbeit',
            '2027-05-06' => 'Christi Himmelfahrt',
            '2027-05-17' => 'Pfingstmontag',
            '2027-05-27' => 'Fronleichnam',
            '2027-06-24' => 'Stadtfest',
            '2027-07-02' => 'Dorffest',
            '2027-08-15' => 'Mariä Himmelfahrt',
            '2027-10-03' => 'Tag der Deutschen Einheit',
            '2027-11-01' => 'Allerheiligen',
            '2027-12-25' => '1. Weihnachtsfeiertag',
            '2027-12-26' => '2. Weihnachtsfeiertag',
        ];
        expect($bayern2027Extra, Holidays::forYear(2027, $withExtras), 'Bayern 2027 mit Extras, Easter 2027 = 28.3.');
        expect(false, isset(Holidays::forYear(2026, $withExtras)['2027-07-02']), 'Y-m-d-Extra nur im Jahr 2027');
        expect(
            'Tag der Deutschen Einheit',
            Holidays::forYear(2026, Holidays::config(['state' => 'BY', 'extra' => ['10-03' => 'Hoffest']]))['2026-10-03'] ?? null,
            'Extra am gesetzlichen Feiertag behält dessen Namen'
        );
        expect(
            'Heiligabend',
            Holidays::forYear(2026, Holidays::config(['state' => 'HH', 'extra' => ['2026-12-24' => 'Heiligabend']]))['2026-12-24'] ?? null,
            'Extra Y-m-d am 24.12.'
        );
        $hhWithEve = $states2026['HH'] + ['2026-12-24' => 'Heiligabend'];
        ksort($hhWithEve);
        expect(
            $hhWithEve,
            Holidays::forYear(2026, Holidays::config(['state' => 'HH', 'extra' => ['2026-12-24' => 'Heiligabend']])),
            'HH mit Heiligabend: sortiert'
        );

        /* ---------------------------------------------------------------------------
         * config(): every example of SPEC §3.2, exact normalised arrays.
         * ------------------------------------------------------------------------- */

        expect(null, Holidays::config(false), 'config(false) ist aus');
        expect(null, Holidays::config(null), 'config(null) ist aus');
        expect(
            ['state' => 'BY', 'assumption' => true, 'augsburg' => false, 'corpusChristi' => false, 'sundays' => true, 'extra' => []],
            Holidays::config('BY'),
            'config("BY") Standardwerte'
        );
        expect(
            ['state' => 'BY', 'assumption' => true, 'augsburg' => true, 'corpusChristi' => false, 'sundays' => true,
             'extra' => ['06-24' => 'Stadtfest', '2027-07-02' => 'Dorffest']],
            Holidays::config(['state' => 'BY', 'augsburg' => true, 'extra' => ['06-24' => 'Stadtfest', '2027-07-02' => 'Dorffest']]),
            'config(Array mit augsburg und extra)'
        );
        expect(
            ['state' => 'BY', 'assumption' => false, 'augsburg' => true, 'corpusChristi' => false, 'sundays' => true, 'extra' => []],
            Holidays::config(['land' => 'bayern', 'mariaeHimmelfahrt' => false, 'friedensfest' => true]),
            'config(legacy array)'
        );
        expect(
            ['state' => 'BB', 'assumption' => true, 'augsburg' => false, 'corpusChristi' => true, 'sundays' => false, 'extra' => []],
            Holidays::config(['state' => 'BB', 'corpusChristi' => true, 'sundays' => false]),
            'config(BB mit sundays false, corpusChristi true)'
        );
        expect(
            ['state' => 'BY', 'assumption' => true, 'augsburg' => false, 'corpusChristi' => false, 'sundays' => true, 'extra' => []],
            Holidays::config(['state' => 'BY', 'assumption' => null]),
            'config: null-Schalter nehmen den Standardwert'
        );

        foreach (['DE-BY', 'de-by', 'by', 'BY', 'bayern', 'Bayern', 'Bavaria', ' DE-BY '] as $variant) {
            expect('BY', Holidays::config($variant)['state'] ?? null, "config('$variant') → BY");
        }
        expect('BY', Holidays::config(['state' => 'de-by'])['state'], 'config(["state" => "de-by"])');
        expect('NW', Holidays::config(['state' => 'DE-NW'])['state'], 'config(["state" => "DE-NW"])');
        expect('BY', Holidays::config(['land' => 'bayern'])['state'], 'config(legacy land bayern)');
        expect('TH', Holidays::config('Thüringen')['state'], 'config("Thüringen")');
        expect('TH', Holidays::config('thueringen')['state'], 'config("thueringen")');

        expect(
            'HE',
            Holidays::config('hessen')['state'],
            'config("hessen") → HE (der alte Test "anderes Land: nichts" entfällt)'
        );
        expect(
            $states2026['HE'],
            Holidays::forYear(2026, Holidays::config('hessen')),
            'config("hessen") liefert die hessischen Feiertage'
        );

        $throws = [
            'true' => fn () => Holidays::config(true),
            '42' => fn () => Holidays::config(42),
            'ohne state' => fn () => Holidays::config(['assumption' => true]),
            "'XX'" => fn () => Holidays::config('XX'),
            "state 'XX'" => fn () => Holidays::config(['state' => 'XX']),
        ];
        foreach ($throws as $what => $fn) {
            $message = holidaysThrows($fn);
            holds($message !== null, "config($what) wirft InvalidArgumentException");
        }
        holds(str_contains((string) holidaysThrows(fn () => Holidays::config('XX')), 'XX'), 'Fehlermeldung nennt das unbekannte Land');

        /* Invalid extra entries are skipped and logged. */

        \Noren\Log::forget();
        $logFile = tempnam(sys_get_temp_dir(), 'noren-holidays-log-');
        $previousLog = (string) ini_get('error_log');
        ini_set('error_log', $logFile);
        $skipped = Holidays::config([
            'state' => 'BY',
            'extra' => [
                '13-45' => 'Falsches Datum',
                '06-24' => '',
                '2027-02-30' => 'Gibt es nicht',
                'Stadtfest' => 'Kein Datum',
                '07-02' => 'Gültig',
            ],
        ]);
        ini_set('error_log', $previousLog);
        $logged = (string) file_get_contents($logFile);
        unlink($logFile);

        expect(['07-02' => 'Gültig'], $skipped['extra'], 'ungültige Extras werden übersprungen, gültige bleiben');
        expect(
            4,
            substr_count($logged, 'Noren: noren.holidays extra entry'),
            'ungültige Extras werden protokolliert (4 Einträge)'
        );
        holds(str_contains($logged, "'13-45'") && str_contains($logged, "'2027-02-30'"), 'Protokoll nennt die verworfenen Schlüssel');
        holds(str_contains($logged, "'Stadtfest'") && str_contains($logged, "'06-24'"), 'Protokoll nennt leeren Namen und Kein-Datum-Schlüssel');
        expect($previousLog, (string) ini_get('error_log'), 'error_log wiederhergestellt');

        /* ---------------------------------------------------------------------------
         * checker(): name of the holiday on a day, translated on lookup.
         * ------------------------------------------------------------------------- */

        $checker = Holidays::checker(Holidays::config('BY'));
        expect('Tag der Deutschen Einheit', $checker(new DateTimeImmutable('2026-10-03 23:30', $berlin)), 'Prüfer findet 3.10. um 23:30');
        expect(null, $checker(new DateTimeImmutable('2026-10-02', $berlin)), 'Prüfer: normaler Tag');
        expect('Heilige Drei Könige', $checker(new DateTimeImmutable('2026-01-06', $berlin)), 'Prüfer: Heilige Drei Könige');
        expect(
            null,
            Holidays::checker(Holidays::config(['state' => 'BY', 'assumption' => false]))(new DateTimeImmutable('2026-08-15', $berlin)),
            'Prüfer ohne Schalter: 15.8. ist kein Feiertag'
        );
        expect(
            'Mariä Himmelfahrt',
            $checker(new DateTimeImmutable('2026-08-15', $berlin)),
            'Prüfer: Mariä Himmelfahrt mit Schalter'
        );
        expect(
            'Stadtfest',
            Holidays::checker(Holidays::config(['state' => 'BY', 'extra' => ['06-24' => 'Stadtfest']]))(new DateTimeImmutable('2027-06-24', $berlin)),
            'Prüfer: Extra-Tag'
        );
        expect(
            'Ostersonntag',
            Holidays::checker(Holidays::config('BB'))(new DateTimeImmutable('2026-04-05 00:00', $berlin)),
            'Prüfer: Ostersonntag in BB'
        );
        expect(
            'Fronleichnam',
            Holidays::checker(Holidays::config('BY'))(new DateTimeImmutable('2027-05-27', $berlin)),
            'Prüfer: Fronleichnam 2027'
        );
        expect(
            null,
            Holidays::checker(Holidays::config('BB'))(new DateTimeImmutable('2026-10-02', $berlin)),
            'Prüfer: normaler Tag in BB'
        );
        expect(
            'Tag der Deutschen Einheit',
            $checker(new DateTimeImmutable('2026-10-03 23:30', $berlin)),
            'Prüfer nach dem ersten Aufruf: gleiche Antwort (Cache)'
        );

        I18n::setLanguage('en');
        expect(
            'German Unity Day',
            $checker(new DateTimeImmutable('2026-10-03 23:30', $berlin)),
            'Prüfer nach Sprachwechsel: German Unity Day'
        );
        expect('Christmas Day', Holidays::name('christmas1'), 'Name christmas1 auf Englisch');
        I18n::setLanguage('de');
        expect(
            'Tag der Deutschen Einheit',
            $checker(new DateTimeImmutable('2026-10-03 23:30', $berlin)),
            'Prüfer nach Rückwechsel auf Deutsch'
        );

        /* ---------------------------------------------------------------------------
         * state(): codes, names, prefixes; unknown states throw.
         * ------------------------------------------------------------------------- */

        expect('NW', Holidays::state('de-nw'), "state('de-nw')");
        expect('TH', Holidays::state('Thüringen'), "state('Thüringen')");
        expect('BY', Holidays::state('DE-BY'), "state('DE-BY')");
        expect('BY', Holidays::state('bayern'), "state('bayern')");
        expect('BW', Holidays::state('Baden-Württemberg'), "state('Baden-Württemberg')");
        expect('ST', Holidays::state('sachsen-anhalt'), "state('sachsen-anhalt')");
        expect('SN', Holidays::state('saxony'), "state('saxony')");
        expect('BY', Holidays::state('bavaria'), "state('bavaria')");
        foreach (Holidays::STATES as $code) {
            expect($code, Holidays::state(strtolower($code)), "state('" . strtolower($code) . "')");
        }
        holds(holidaysThrows(fn () => Holidays::state('XX')) !== null, "state('XX') wirft");
        holds(holidaysThrows(fn () => Holidays::state('')) !== null, "state('') wirft");
        holds(holidaysThrows(fn () => Holidays::state('Atlantis')) !== null, "state('Atlantis') wirft");

        /* Names of the holidays (translation keys) exist in both languages. */

        expect('Tag der Deutschen Einheit', Holidays::name('germanUnity'), 'Name germanUnity (de)');
        expect('Weltkindertag', Holidays::name('childrensDay'), 'Name childrensDay (de)');


        /* --------------------------------------------------------------------------
         * Berlin 2028, legacy arrays without `land`,
         * boolean switches only.
         * -------------------------------------------------------------------------- */

        // BE 2028: the one-off 75th anniversary of the 1953 uprising, Saturday 17 June.
        expect('uprisingDay', Holidays::ids(2028, 'BE')['2028-06-17'] ?? null, 'BE 2028: 17 June is the uprising day');
        expect(11, count(Holidays::ids(2028, 'BE')), 'BE 2028: nine common days, Women\'s Day and the uprising day');
        holds(!array_key_exists('2027-06-17', Holidays::ids(2027, 'BE')), 'BE 2027: no 17 June holiday');
        holds(!array_key_exists('2028-06-17', Holidays::ids(2028, 'BY')), 'BY 2028: no 17 June holiday');
        expect('Jahrestag des Volksaufstands 1953', Holidays::name('uprisingDay'), 'name uprisingDay (de)');
        holds(array_key_exists('2028-06-17', Holidays::forYear(2028, Holidays::config('BE'))), 'forYear: BE 2028 lists 17 June');

        // Legacy array without `land`: Bavaria.
        $legacyNoLand = Holidays::config(['mariaeHimmelfahrt' => false]);
        expect('BY', $legacyNoLand['state'], 'legacy array with mariaeHimmelfahrt and no land counts as Bavaria');
        expect(false, $legacyNoLand['assumption'], 'legacy mariaeHimmelfahrt => false is read without land');
        expect(true, Holidays::config(['friedensfest' => true])['augsburg'], 'legacy friedensfest without land is read');
        holds(holidaysThrows(fn () => Holidays::config(['extra' => ['06-24' => 'Stadtfest']])) !== null, 'an array with neither state nor legacy keys still needs a state');

        // Switches: only real booleans (or 0/1) count; 'false' is not a switch.
        $flagLog = tempnam(sys_get_temp_dir(), 'noren-holiday-flags-');
        $previousFlagLog = (string) ini_get('error_log');
        ini_set('error_log', $flagLog);
        \Noren\Log::forget();
        $flags = Holidays::config(['state' => 'BY', 'assumption' => 'false', 'augsburg' => 1, 'corpusChristi' => 0]);
        ini_set('error_log', $previousFlagLog);
        $flagLogged = (string) file_get_contents($flagLog);
        unlink($flagLog);
        expect(true, $flags['assumption'], "assumption 'false' (a string) keeps the default true");
        expect(true, $flags['augsburg'], 'augsburg 1 (an int) switches on');
        expect(false, $flags['corpusChristi'], 'corpusChristi 0 (an int) stays off');
        holds(str_contains($flagLogged, 'noren.holidays.assumption must be true or false'), "a non-boolean switch is logged");
    }
}
