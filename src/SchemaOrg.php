<?php

namespace Noren;

use DateTimeImmutable;

/**
 * schema.org opening hours as plain arrays (SPEC.md §3.6), for the site's own
 * JSON-LD snippet or a separate SEO plugin. Kirby-free, no JSON encoding.
 * OWNER: task `kirby`.
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
     * One OpeningHoursSpecification per block of the weekly plan, Monday
     * first. A day without blocks gets no entry (schema.org's "closed").
     *
     * @return list<array{'@type': string, dayOfWeek: string, opens: string, closes: string}>
     */
    public static function openingHours(Schedule $schedule): array
    {
        $specs = [];
        foreach (self::DAYS as $weekday => $name) {
            foreach ($schedule->blocks($weekday) as [$from, $to]) {
                $specs[] = [
                    '@type'     => 'OpeningHoursSpecification',
                    'dayOfWeek' => 'https://schema.org/' . $name,
                    'opens'     => self::time($from),
                    'closes'    => self::time($to),
                ];
            }
        }
        return $specs;
    }

    /**
     * ALL upcoming closures and special days (no lead time — Maps should know
     * early), then the upcoming holidays within $holidayDays, as
     * OpeningHoursSpecification with validFrom/validThrough. Closed days are
     * opens "00:00" / closes "00:00" (Google's notation for closed all day).
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
                'opens'        => $hours !== null ? self::time($hours[0]) : '00:00',
                'closes'       => $hours !== null ? self::time($hours[1]) : '00:00',
            ];
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
