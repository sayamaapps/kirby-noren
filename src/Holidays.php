<?php

namespace Noren;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * German public holidays, calculated instead of maintained (SPEC.md §3.2).
 * The movable ones all hang on Easter Sunday, which follows a fixed formula
 * (Meeus/Jones/Butcher, no ext-calendar), so no list ever runs out silently.
 *
 * All 16 states, with the years in which a holiday was introduced. Days that
 * apply only in parts of a state are switches:
 *
 * - `assumption` (Mariä Himmelfahrt, 15.8.) in Bavaria: only in
 *   predominantly Catholic municipalities (list: Bayerisches Landesamt für
 *   Statistik). Default ON. Always in Saarland.
 * - `augsburg` (Augsburger Friedensfest, 8.8.): only in the city of Augsburg.
 *   Default off.
 * - `corpusChristi` (Fronleichnam) in Saxony and Thuringia: only in some
 *   Catholic municipalities (Sorbian Upper Lusatia, Eichsfeld). Default off.
 *   Always in BW, BY, HE, NW, RP, SL.
 * - `sundays` (Ostersonntag, Pfingstsonntag) in Brandenburg, the only state
 *   where they are statutory holidays. Default ON (a business open on
 *   Sundays enters special hours to stay open, like on any holiday).
 *
 * Kirby-free; tests/HolidaysTest.php.
 */
final class Holidays
{
    /** ISO 3166-2:DE subdivision codes without the "DE-" prefix. */
    public const STATES = ['BW', 'BY', 'BE', 'BB', 'HB', 'HH', 'HE', 'MV', 'NI', 'NW', 'RP', 'SL', 'SN', 'ST', 'SH', 'TH'];

    /** Lower-case state names accepted instead of the code (the legacy `land` => 'bayern'). */
    public const ALIASES = [
        'baden-württemberg' => 'BW', 'baden-wuerttemberg' => 'BW',
        'bayern' => 'BY', 'bavaria' => 'BY',
        'berlin' => 'BE',
        'brandenburg' => 'BB',
        'bremen' => 'HB',
        'hamburg' => 'HH',
        'hessen' => 'HE', 'hesse' => 'HE',
        'mecklenburg-vorpommern' => 'MV',
        'niedersachsen' => 'NI', 'lower saxony' => 'NI',
        'nordrhein-westfalen' => 'NW', 'north rhine-westphalia' => 'NW',
        'rheinland-pfalz' => 'RP', 'rhineland-palatinate' => 'RP',
        'saarland' => 'SL',
        'sachsen' => 'SN', 'saxony' => 'SN',
        'sachsen-anhalt' => 'ST', 'saxony-anhalt' => 'ST',
        'schleswig-holstein' => 'SH',
        'thüringen' => 'TH', 'thueringen' => 'TH', 'thuringia' => 'TH',
    ];

    /** Defaults of the switches. */
    public const FLAGS = ['assumption' => true, 'augsburg' => false, 'corpusChristi' => false, 'sundays' => true];

    /** Easter Sunday by the anonymous Gregorian algorithm (Meeus/Jones/Butcher). */
    public static function easterSunday(int $year, string $timezone = Schedule::DEFAULT_TIMEZONE): DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new DateTimeZone($timezone));
    }

    /**
     * Holiday ids of a year in a state: date (Y-m-d) => id (see
     * translations, `holiday.<id>`), ascending.
     *
     * @param array{assumption?: bool, augsburg?: bool, corpusChristi?: bool, sundays?: bool} $flags
     * @return array<string, string>
     */
    public static function ids(int $year, string $state, array $flags = []): array
    {
        $state = self::state($state);
        $flags += self::FLAGS;
        $easter = self::easterSunday($year, 'UTC');
        $plus = static fn (int $days) => $easter->modify(($days >= 0 ? '+' : '') . $days . ' day')->format('Y-m-d');
        $in = static fn (string ...$states) => in_array($state, $states, true);

        $days = [
            "$year-01-01" => 'newYear',
            $plus(-2)     => 'goodFriday',
            $plus(1)      => 'easterMonday',
            "$year-05-01" => 'labourDay',
            $plus(39)     => 'ascension',
            $plus(50)     => 'whitMonday',
            "$year-10-03" => 'germanUnity',
            "$year-12-25" => 'christmas1',
            "$year-12-26" => 'christmas2',
        ];

        if ($in('BW', 'BY', 'ST')) {
            $days["$year-01-06"] = 'epiphany';
        }
        if (($state === 'BE' && $year >= 2019) || ($state === 'MV' && $year >= 2023)) {
            $days["$year-03-08"] = 'womensDay';
        }
        if ($state === 'BE' && in_array($year, [2020, 2025], true)) {
            // One-off: 75th and 80th anniversary of the end of the war.
            $days["$year-05-08"] = 'liberationDay';
        }
        if ($state === 'BE' && $year === 2028) {
            // One-off: 75th anniversary of the 1953 uprising (Abgeordnetenhaus, 2024).
            $days["$year-06-17"] = 'uprisingDay';
        }
        if ($state === 'BB' && $flags['sundays'] === true) {
            $days[$plus(0)] = 'easterSunday';
            $days[$plus(49)] = 'whitSunday';
        }
        if ($in('BW', 'BY', 'HE', 'NW', 'RP', 'SL') || ($in('SN', 'TH') && $flags['corpusChristi'] === true)) {
            $days[$plus(60)] = 'corpusChristi';
        }
        if ($state === 'BY' && $flags['augsburg'] === true) {
            $days["$year-08-08"] = 'augsburgPeace';
        }
        if ($state === 'SL' || ($state === 'BY' && $flags['assumption'] === true)) {
            $days["$year-08-15"] = 'assumption';
        }
        if ($state === 'TH' && $year >= 2019) {
            $days["$year-09-20"] = 'childrensDay';
        }
        if ($in('BB', 'MV', 'SN', 'ST', 'TH') || ($in('HB', 'HH', 'NI', 'SH') && $year >= 2018) || $year === 2017) {
            // 2017: 500 years of the Reformation, once nationwide.
            $days["$year-10-31"] = 'reformation';
        }
        if ($in('BW', 'BY', 'NW', 'RP', 'SL')) {
            $days["$year-11-01"] = 'allSaints';
        }
        if ($state === 'SN') {
            $days[self::repentanceDay($year)] = 'repentance';
        }

        ksort($days);
        return $days;
    }

    /** Buß- und Bettag: the Wednesday before 23 November (16–22 November). */
    public static function repentanceDay(int $year): string
    {
        $day = new DateTimeImmutable("$year-11-22", new DateTimeZone('UTC'));
        while ((int) $day->format('N') !== 3) {
            $day = $day->modify('-1 day');
        }
        return $day->format('Y-m-d');
    }

    /**
     * All holidays of a year for a normalised config (see config()): date =>
     * translated name, including the `extra` days, ascending. A statutory
     * holiday keeps its name when an extra day falls on the same date.
     *
     * @return array<string, string>
     */
    public static function forYear(int $year, array $config): array
    {
        return array_map(
            static fn (array $entry) => $entry['id'] !== null ? self::name($entry['id']) : $entry['name'],
            self::entries($year, $config)
        );
    }

    /**
     * Untranslated entries of a year: date => ['id' => holiday id|null,
     * 'name' => extra name|null]. Translation happens on lookup, so a
     * language switch after the first lookup is respected.
     *
     * @return array<string, array{id: ?string, name: ?string}>
     */
    private static function entries(int $year, array $config): array
    {
        $entries = [];
        foreach (self::ids($year, $config['state'], $config) as $date => $id) {
            $entries[$date] = ['id' => $id, 'name' => null];
        }
        foreach ($config['extra'] ?? [] as $key => $name) {
            $date = strlen((string) $key) === 5 ? "$year-$key" : (string) $key;
            if (str_starts_with($date, "$year-") && !isset($entries[$date])) {
                $entries[$date] = ['id' => null, 'name' => $name];
            }
        }
        ksort($entries);
        return $entries;
    }

    /** Translated name of a holiday id ("germanUnity" → "Tag der Deutschen Einheit"). */
    public static function name(string $id): string
    {
        return I18n::t('holiday.' . $id);
    }

    /**
     * Normalises the `noren.holidays` option.
     *
     *   false | null                     → null (off, the default)
     *   'BY' | 'DE-BY' | 'bayern'        → ['state' => 'BY'] + defaults
     *   ['state' => 'BY', 'assumption' => true, 'augsburg' => false,
     *    'corpusChristi' => false, 'sundays' => true,
     *    'extra' => ['06-24' => 'Stadtfest', '2027-07-02' => 'Dorffest']]
     *   legacy array: ['land' => 'bayern',
     *    'mariaeHimmelfahrt' => true, 'friedensfest' => false]; without `land`
     *    the legacy keys mean Bavaria.
     *
     * Invalid `extra` entries (key neither MM-DD nor Y-m-d, empty name) are
     * skipped and logged. A flag that is not a real boolean (for example the
     * string 'false') is logged and the default is used.
     *
     * @return array{state: string, assumption: bool, augsburg: bool, corpusChristi: bool, sundays: bool, extra: array<string,string>}|null
     * @throws InvalidArgumentException unknown state, `true`, or any other type
     */
    public static function config(mixed $option): ?array
    {
        if ($option === null || $option === false) {
            return null;
        }
        if (is_string($option)) {
            $option = ['state' => $option];
        }
        if (!is_array($option)) {
            throw new InvalidArgumentException('noren.holidays must be false, a state code such as "BY", or an array with "state".');
        }

        $state = $option['state'] ?? $option['land'] ?? null;
        // A legacy array without `land` is Bavarian: its keys say so.
        if ($state === null && (array_key_exists('mariaeHimmelfahrt', $option) || array_key_exists('friedensfest', $option))) {
            $state = 'BY';
        }
        if (!is_string($state)) {
            throw new InvalidArgumentException('noren.holidays needs a "state" such as "BY".');
        }

        $config = ['state' => self::state($state)];
        $legacy = ['assumption' => 'mariaeHimmelfahrt', 'augsburg' => 'friedensfest'];
        foreach (self::FLAGS as $flag => $default) {
            $value = $option[$flag] ?? (isset($legacy[$flag]) ? ($option[$legacy[$flag]] ?? null) : null);
            $config[$flag] = self::flag($flag, $value, $default);
        }

        $config['extra'] = [];
        foreach ((array) ($option['extra'] ?? []) as $key => $name) {
            $key = trim((string) $key);
            $valid = (preg_match('/^\d{2}-\d{2}$/', $key) && checkdate((int) substr($key, 0, 2), (int) substr($key, 3, 2), 2024))
                || (preg_match('/^\d{4}-\d{2}-\d{2}$/', $key) && checkdate((int) substr($key, 5, 2), (int) substr($key, 8, 2), (int) substr($key, 0, 4)));
            if (!$valid || !is_string($name) || trim($name) === '') {
                Log::error('Noren: noren.holidays extra entry ' . var_export($key, true) . ' skipped — use "MM-DD" or "Y-m-d" => "Name".');
                continue;
            }
            $config['extra'][$key] = trim($name);
        }

        return $config;
    }

    /**
     * Check function for Schedule: name of the holiday on a day or null. The
     * holidays of a year are computed once per checker.
     *
     * @param array $config normalised, from config()
     * @return Closure(DateTimeImmutable): ?string
     */
    public static function checker(array $config): Closure
    {
        $cache = [];
        return static function (DateTimeImmutable $day) use ($config, &$cache): ?string {
            $year = (int) $day->format('Y');
            $cache[$year] ??= self::entries($year, $config);
            $entry = $cache[$year][$day->format('Y-m-d')] ?? null;
            if ($entry === null) {
                return null;
            }
            return $entry['id'] !== null ? self::name($entry['id']) : $entry['name'];
        };
    }

    /**
     * A switch of config(): null → default; a real boolean, or 0/1, is used.
     * Anything else (such as the string 'false') is logged and the default wins.
     */
    private static function flag(string $name, mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === 1) {
            return $value === 1;
        }
        Log::error('Noren: noren.holidays.' . $name . ' must be true or false — using ' . var_export($default, true) . '.');
        return $default;
    }

    /** "by", "DE-BY", "bayern" → "BY". */
    public static function state(string $state): string
    {
        $value = trim($state);
        $upper = strtoupper($value);
        if (str_starts_with($upper, 'DE-')) {
            $upper = substr($upper, 3);
        }
        if (in_array($upper, self::STATES, true)) {
            return $upper;
        }
        $alias = self::ALIASES[mb_strtolower($value)] ?? null;
        if ($alias !== null) {
            return $alias;
        }
        throw new InvalidArgumentException('Unknown German state ' . var_export($state, true) . ' — use one of ' . implode(', ', self::STATES) . '.');
    }
}
