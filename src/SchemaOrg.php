<?php

namespace Noren;

use DateTimeImmutable;

/**
 * schema.org opening hours as plain arrays (SPEC.md §3.6), for the site's own
 * JSON-LD snippet or a separate SEO plugin. Kirby-free, no JSON encoding.
 */
final class SchemaOrg
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /** "HH:MM", zero-padded, modulo one day: 510 → "08:30", 1440 → "00:00", 1560 → "02:00". */
    public static function time(int $minutes): string
    {
        $minutes %= Schedule::MINUTES_PER_DAY;
        if ($minutes < 0) {
            $minutes += Schedule::MINUTES_PER_DAY;
        }
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Opens/closes of one block. A full day ([0, 1440]) is 00:00 to 23:59,
     * because 00:00 to 00:00 means closed in Google's LocalBusiness notation.
     *
     * @return array{opens: string, closes: string}
     */
    private static function range(int $from, int $to): array
    {
        if ($from === 0 && $to >= Schedule::MINUTES_PER_DAY) {
            return ['opens' => '00:00', 'closes' => '23:59'];
        }
        return ['opens' => self::time($from), 'closes' => self::time($to)];
    }

    /**
     * One block of one weekday as entries. A block from 00:00 that runs past
     * midnight is a full day plus the rest on the next weekday: Monday
     * [0, 1560] is Monday 00:00–23:59 and Tuesday 00:00–02:00. Any other block
     * is one entry on its own weekday (an evening block keeps its start day).
     *
     * @return list<array{'@type': string, dayOfWeek: string, opens: string, closes: string}>
     */
    private static function entries(int $weekday, int $from, int $to): array
    {
        $entry = static fn (int $day, array $hours): array => [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => 'https://schema.org/' . self::DAYS[$day],
        ] + $hours;

        if ($from === 0 && $to > Schedule::MINUTES_PER_DAY) {
            return [
                $entry($weekday, self::range(0, Schedule::MINUTES_PER_DAY)),
                ...self::entries(($weekday + 1) % 7, 0, $to - Schedule::MINUTES_PER_DAY),
            ];
        }
        return [$entry($weekday, self::range($from, $to))];
    }

    /**
     * One OpeningHoursSpecification per block of the weekly plan, Monday
     * first. A day without blocks gets no entry (schema.org's "closed"); a
     * full day is opens "00:00" / closes "23:59", and a block from 00:00 past
     * midnight continues on the next weekday (see entries()).
     *
     * @return list<array{'@type': string, dayOfWeek: string, opens: string, closes: string}>
     */
    public static function openingHours(Schedule $schedule): array
    {
        $specs = [];
        foreach (array_keys(self::DAYS) as $weekday) {
            foreach ($schedule->blocks($weekday) as [$from, $to]) {
                array_push($specs, ...self::entries($weekday, $from, $to));
            }
        }
        return $specs;
    }

    /**
     * ALL upcoming closures and special days (no lead time — Maps should know
     * early), then the upcoming holidays within $holidayDays, as
     * OpeningHoursSpecification with validFrom/validThrough. Closed days are
     * opens "00:00" / closes "00:00" (Google's notation for closed all day); special
     * hours covering a full day are 00:00 to 23:59.
     *
     * @return list<array{'@type': string, validFrom: string, validThrough: string, opens: string, closes: string}>
     */
    public static function specialOpeningHours(Schedule $schedule, ?DateTimeImmutable $from = null, int $holidayDays = 365): array
    {
        $from ??= new DateTimeImmutable('now', $schedule->timezone());
        $specs = [];

        foreach ($schedule->upcomingClosures($from) as $closure) {
            $hours = $closure['hours'];
            $specs[] = [
                '@type'        => 'OpeningHoursSpecification',
                'validFrom'    => $closure['from'],
                'validThrough' => $closure['to'],
            ] + ($hours !== null ? self::range($hours[0], $hours[1]) : ['opens' => '00:00', 'closes' => '00:00']);
        }

        foreach ($schedule->upcomingHolidays($from, $holidayDays) as $holiday) {
            $specs[] = [
                '@type'        => 'OpeningHoursSpecification',
                'validFrom'    => $holiday['date'],
                'validThrough' => $holiday['date'],
                'opens'        => '00:00',
                'closes'       => '00:00',
            ];
        }

        return $specs;
    }

    /**
     * @return array{openingHoursSpecification: list<array>, specialOpeningHoursSpecification: list<array>}
     */
    public static function forSchedule(Schedule $schedule, ?DateTimeImmutable $from = null): array
    {
        return [
            'openingHoursSpecification'        => self::openingHours($schedule),
            'specialOpeningHoursSpecification' => self::specialOpeningHours($schedule, $from),
        ];
    }
}
