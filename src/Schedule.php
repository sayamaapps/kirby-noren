<?php

namespace Noren;

use Closure;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Answers the one question visitors really ask the site: "Are you open right
 * now, and if not — when again?" (SPEC.md §3.1)
 *
 * Port of Betrieb\Oeffnungszeiten (kirby-starter, site/plugins/betrieb).
 * Kirby-free on purpose: the class works on plain arrays; reading the Panel
 * structure fields happens in fromFields(), which only needs objects with
 * toStructure() and field methods. tests/ScheduleTest.php checks the logic
 * without booting Kirby.
 *
 * ONE SCHEDULE = ONE AREA OF ONE LOCATION. A business can have several areas
 * with DIFFERENT hours (shop open, café closed), and several locations. The
 * class always knows exactly one plan; whoever shows two areas builds two
 * objects. NEVER memoise schedules keyed on the page id: shop and café live on
 * the SAME page, and the second call would silently get the first plan.
 *
 * Times are minutes since 0:00 of the block's day. A block may end after
 * midnight (end > 1440): it belongs to the day it starts on (overnight block,
 * SPEC.md §3.1.4).
 */
final class Schedule
{
    /** Weekday names as stored in content (the field contract), index 0 = Monday. */
    public const DAYS_DE = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

    /** English weekday names, also accepted in content. */
    public const DAYS_EN = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public const DEFAULT_TIMEZONE = 'Europe/Berlin';

    /** Days searched for the next opening. Longer than any plausible closure. */
    public const DEFAULT_LOOKAHEAD = 90;

    /** 06:00 — latest next-day end of an overnight block read from content. */
    public const DEFAULT_OVERNIGHT_UNTIL = 360;

    public const MINUTES_PER_DAY = 1440;

    /** @var array<int, array{blocks: list<array{0:int,1:int}>, note: ?string}> */
    private array $days;

    /** @var list<array{from: string, to: string, note: ?string, occasion: ?string, hours: ?array{0:int,1:int}, area: ?string}> */
    private array $closures;

    private DateTimeZone $timezone;
    private bool $holidayEve;
    private int $lookahead;

    /**
     * @param array<int, array{blocks?: list<array{0:int,1:int}>, note?: ?string}> $days
     *        Index 0 = Monday … 6 = Sunday. Missing days count as closed.
     * @param list<array{from: string, to: string, note?: ?string, occasion?: ?string, hours?: ?array{0:int,1:int}, area?: ?string}> $closures
     *        Company holidays and special days, dates as Y-m-d (inclusive).
     *        `occasion` names a special day ("Silvester"). With `hours` the
     *        day is not closed but open with these hours.
     * @param string|null $closureLabel What a running closure is called
     *        ("Betriebsferien", "Praxisurlaub"). null = translation `closure.label`.
     * @param (Closure(DateTimeImmutable): ?string)|null $holiday
     *        Name of the public holiday on a day, or null (Holidays::checker()).
     *        null = no public holidays.
     * @param array{timezone?: string|DateTimeZone, holidayEve?: bool, lookahead?: int} $settings
     */
    public function __construct(
        array $days,
        array $closures = [],
        private readonly ?string $closureLabel = null,
        private readonly ?Closure $holiday = null,
        array $settings = [],
    ) {
        $this->days = [];
        for ($i = 0; $i < 7; $i++) {
            $note = $days[$i]['note'] ?? null;
            $this->days[$i] = [
                'blocks' => self::mergeBlocks($days[$i]['blocks'] ?? []),
                'note'   => is_string($note) && $note !== '' ? $note : null,
            ];
        }

        $this->closures = [];
        foreach ($closures as $c) {
            $this->closures[] = [
                'from'     => (string) $c['from'],
                'to'       => (string) $c['to'],
                'note'     => $c['note'] ?? null,
                'occasion' => $c['occasion'] ?? null,
                'hours'    => $c['hours'] ?? null,
                'area'     => $c['area'] ?? null,
            ];
        }

        $zone = $settings['timezone'] ?? self::DEFAULT_TIMEZONE;
        $this->timezone = $zone instanceof DateTimeZone ? $zone : new DateTimeZone($zone);
        $this->holidayEve = ($settings['holidayEve'] ?? false) === true;
        $this->lookahead = max(1, (int) ($settings['lookahead'] ?? self::DEFAULT_LOOKAHEAD));
    }

    /**
     * Reads the Panel structure fields (field contract: SPEC.md §6).
     *
     * @param mixed       $hoursField    Structure with tag/von1/bis1/von2/bis2/hinweis
     *                                   (legacy vm_von/vm_bis/nm_von/nm_bis/nm_hinweis)
     *                                   and optionally a column `bereich`.
     * @param mixed       $closuresField Structure with von/bis/bereich/anlass/hinweis/oeffnet/schliesst.
     * @param string|null $area          Only rows of this area. null = all rows
     *                                   (a business without areas).
     * @param array{timezone?: string|DateTimeZone, holidayEve?: bool, lookahead?: int, overnightUntil?: ?int} $settings
     *        `overnightUntil`: minutes; a block whose end is before its start
     *        counts as overnight when the end is <= this value. Key missing =
     *        DEFAULT_OVERNIGHT_UNTIL; null = overnight blocks off.
     */
    public static function fromFields(
        mixed $hoursField,
        mixed $closuresField = null,
        ?string $area = null,
        ?string $closureLabel = null,
        ?Closure $holiday = null,
        array $settings = [],
    ): self {
        $overnightUntil = array_key_exists('overnightUntil', $settings)
            ? $settings['overnightUntil']
            : self::DEFAULT_OVERNIGHT_UNTIL;

        $days = array_fill(0, 7, ['blocks' => [], 'note' => null]);

        foreach (self::rows($hoursField) as $row) {
            // Area filter first: a row for the shop must not affect the café.
            if ($area !== null && trim((string) $row->bereich()) !== $area) {
                continue;
            }

            $i = self::dayIndex((string) $row->tag());
            if ($i === null) {
                continue;
            }

            // `von1/bis1` and `von2/bis2` are the contract. Deliberately not
            // `vm_/nm_` (morning/afternoon): a shop open only 13–18 has no
            // morning. `vm_/nm_` stays readable as a fallback per pair.
            foreach ([['von1', 'bis1', 'vm_von', 'vm_bis'], ['von2', 'bis2', 'nm_von', 'nm_bis']] as [$a, $b, $oldA, $oldB]) {
                $rawFrom = trim((string) $row->$a());
                $rawTo = trim((string) $row->$b());
                if ($rawFrom === '' && $rawTo === '') {
                    $rawFrom = trim((string) $row->$oldA());
                    $rawTo = trim((string) $row->$oldB());
                }
                $block = self::block(self::parseTime($rawFrom), self::parseTime($rawTo), $overnightUntil);
                if ($block !== null) {
                    // APPEND, never overwrite: several rows per weekday are allowed.
                    $days[$i]['blocks'][] = $block;
                }
            }

            $note = trim((string) $row->hinweis());
            if ($note === '') {
                $note = trim((string) $row->nm_hinweis());
            }
            if ($note !== '') {
                $days[$i]['note'] = $note;
            }
        }

        $closures = [];
        foreach (self::rows($closuresField) as $row) {
            $from = trim((string) $row->von());
            $to = trim((string) $row->bis());
            if ($from === '' || $to === '') {
                continue;
            }
            // A closure may be limited to one area. Empty = all areas.
            $only = trim((string) $row->bereich());
            if ($area !== null && $only !== '' && $only !== $area) {
                continue;
            }
            // Dates must be Y-m-d and in order, or the closure would never apply
            // (a string comparison) or apply forever (a German date).
            if (!self::isDate(substr($from, 0, 10)) || !self::isDate(substr($to, 0, 10)) || substr($from, 0, 10) > substr($to, 0, 10)) {
                Log::error('Noren: closure ' . var_export($from, true) . ' to ' . var_export($to, true) . ' skipped — dates must be Y-m-d and from must not be after to.');
                continue;
            }
            $note = trim((string) $row->hinweis());
            $occasion = trim((string) $row->anlass());
            // Short hours instead of closed: only when both times make sense.
            // Half-filled counts as closed, as before.
            $hours = self::block(self::parseTime((string) $row->oeffnet()), self::parseTime((string) $row->schliesst()), $overnightUntil);
            $closures[] = [
                'from'     => substr($from, 0, 10),
                'to'       => substr($to, 0, 10),
                'note'     => $note !== '' ? $note : null,
                'occasion' => $occasion !== '' ? $occasion : null,
                'hours'    => $hours,
                'area'     => $only !== '' ? $only : null,
            ];
        }

        return new self($days, $closures, $closureLabel, $holiday, $settings);
    }

    /**
     * Sorted by start; blocks that touch or overlap become one block, so
     * "08:00–12:00" and "12:00–18:00" are one opening, 08:00–18:00. Several rows
     * per weekday are allowed, and their order in the content does not matter.
     *
     * @param list<array{0:int,1:int}> $blocks
     * @return list<array{0:int,1:int}>
     */
    public static function mergeBlocks(array $blocks): array
    {
        usort($blocks, static fn (array $x, array $y) => $x[0] <=> $y[0]);
        $merged = [];
        foreach ($blocks as [$from, $to]) {
            $last = array_key_last($merged);
            if ($last !== null && $from <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $to);
            } else {
                $merged[] = [$from, $to];
            }
        }
        return $merged;
    }

    /** Is this a real calendar date in Y-m-d form? */
    private static function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** @return iterable<object> */
    private static function rows(mixed $field): iterable
    {
        // No method_exists() check: Kirby's Field provides toStructure()
        // through __call (field methods), method_exists() says false.
        if (!is_object($field)) {
            return [];
        }
        try {
            return $field->toStructure() ?? [];
        } catch (\Throwable) {
            // Locations reports the field by name; this is the backstop for direct callers.
            Log::error('Noren: a hours or closures field is not structure data — its rows are ignored.');
            return [];
        }
    }

    /**
     * A block from two parsed times, or null.
     *
     * - end after start: a normal block.
     * - end 00:00 (and start after 00:00): open until midnight, [start, 1440].
     * - end before start and end <= $overnightUntil: overnight, [start, end + 1440].
     * - anything else (equal times, end 08:00 after start 18:00 with the
     *   default limit 06:00 — most likely a typo): null, ignored.
     *
     * @return array{0:int,1:int}|null
     */
    public static function block(?int $from, ?int $to, ?int $overnightUntil = self::DEFAULT_OVERNIGHT_UNTIL): ?array
    {
        if ($from === null || $to === null) {
            return null;
        }
        if ($to > $from) {
            return [$from, $to];
        }
        if ($to === 0 && $from > 0) {
            return [$from, self::MINUTES_PER_DAY];
        }
        if ($overnightUntil !== null && $to < $from && $to <= $overnightUntil) {
            return [$from, $to + self::MINUTES_PER_DAY];
        }
        return null;
    }

    /** "08:00" or "08:00:00" → 480. Empty or unreadable → null. */
    public static function parseTime(string $time): ?int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', trim($time), $t)) {
            return null;
        }
        $hour = (int) $t[1];
        $minute = (int) $t[2];
        if ($hour > 23 || $minute > 59) {
            return null;
        }
        return $hour * 60 + $minute;
    }

    /**
     * Weekday index (0 = Monday) from a content value: German name
     * ("Montag", the stored contract), English name ("Monday"), both
     * case-insensitive, or ISO number "1"–"7". Anything else → null.
     */
    public static function dayIndex(string $value): ?int
    {
        $value = trim($value);
        if (preg_match('/^[1-7]$/', $value)) {
            return (int) $value - 1;
        }
        $lower = mb_strtolower($value);
        foreach ([self::DAYS_DE, self::DAYS_EN] as $names) {
            foreach ($names as $i => $name) {
                if (mb_strtolower($name) === $lower) {
                    return $i;
                }
            }
        }
        return null;
    }

    /**
     * 510 → "8:30", 1080 → "18:00", 1440 → "24:00", 1560 → "2:00"
     * (an overnight end is shown as the clock time on the next day).
     */
    public static function formatTime(int $minutes): string
    {
        if ($minutes > self::MINUTES_PER_DAY) {
            $minutes -= self::MINUTES_PER_DAY;
        }
        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** The blocks of a weekday (0 = Monday), sorted by start. */
    public function blocks(int $weekday): array
    {
        return $this->days[$weekday]['blocks'] ?? [];
    }

    public function note(int $weekday): ?string
    {
        return $this->days[$weekday]['note'] ?? null;
    }

    /** @return array<int, array{blocks: list<array{0:int,1:int}>, note: ?string}> index 0–6 */
    public function week(): array
    {
        return $this->days;
    }

    /** Is any weekly plan maintained at all? Empty means "show nothing", never "closed". */
    public function maintained(): bool
    {
        for ($day = 0; $day < 7; $day++) {
            if ($this->blocks($day) !== []) {
                return true;
            }
        }
        return false;
    }

    /** Are public holidays switched on for this schedule? */
    public function hasHolidays(): bool
    {
        return $this->holiday !== null;
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    /** All closure entries, as stored (normalised). */
    public function closures(): array
    {
        return $this->closures;
    }

    /** Closed all day by a closure (an entry without hours)? The entry, else null. */
    public function closureOn(DateTimeImmutable $day): ?array
    {
        $date = $this->local($day)->format('Y-m-d');
        foreach ($this->closures as $c) {
            if ($c['hours'] === null && $date >= $c['from'] && $date <= $c['to']) {
                return $c;
            }
        }
        return null;
    }

    /** Special (short) hours on this day? The entry, else null. */
    public function specialOn(DateTimeImmutable $day): ?array
    {
        $date = $this->local($day)->format('Y-m-d');
        foreach ($this->closures as $c) {
            if ($c['hours'] !== null && $date >= $c['from'] && $date <= $c['to']) {
                return $c;
            }
        }
        return null;
    }

    /**
     * The blocks of a concrete date: special hours before the weekly plan.
     * Use this wherever a date is known, otherwise Christmas Eve would say
     * "open until 18:00" instead of 12:00.
     */
    public function blocksOn(DateTimeImmutable $day): array
    {
        $special = $this->specialOn($day);
        return $special !== null ? [$special['hours']] : $this->blocks($this->weekday($day));
    }

    /**
     * What a closure is called: its occasion ("Silvester"), else for a single
     * day simply "Geschlossen", else the closure label ("Betriebsferien").
     * A single day is not company holidays.
     */
    public function label(array $closure): string
    {
        return $closure['occasion']
            ?? ($closure['from'] === $closure['to'] ? I18n::t('closure.singleDay') : $this->closureLabel());
    }

    /** What closures are called on this site ("Betriebsferien", "Praxisurlaub"). */
    public function closureLabel(): string
    {
        return $this->closureLabel ?? I18n::t('closure.label');
    }

    /** Name of the public holiday on this day, else null. */
    public function holidayOn(DateTimeImmutable $day): ?string
    {
        return $this->holiday ? ($this->holiday)($this->local($day)) : null;
    }

    /** Closed because of a closure or a holiday (not because of the weekly plan). */
    private function exceptionOn(DateTimeImmutable $day): bool
    {
        // Entered special hours beat the holiday: whoever enters hours on a
        // public holiday is open.
        return $this->closureOn($day) !== null
            || ($this->holidayOn($day) !== null && $this->specialOn($day) === null);
    }

    /**
     * Public holidays in the next $days days on which the weekly plan would
     * otherwise be open. A holiday on a rest day changes nothing and is not
     * reported; neither is one with special hours entered (then it is open).
     *
     * @return list<array{date: string, name: string}>
     */
    public function upcomingHolidays(?DateTimeImmutable $from = null, int $days = 365): array
    {
        $from = $from !== null ? $this->local($from) : $this->now()->setTime(0, 0);
        $found = [];
        for ($d = 0; $d < $days; $d++) {
            $day = $from->modify("+$d day");
            $name = $this->holidayOn($day);
            if ($name !== null && $this->specialOn($day) === null && $this->blocks($this->weekday($day)) !== []) {
                $found[] = ['date' => $day->format('Y-m-d'), 'name' => $name];
            }
        }
        return $found;
    }

    /**
     * Closures (and special hours) that are still ahead or running.
     *
     * Decisive is `to`, not `from`: a closure that began yesterday and runs
     * three more days is ahead — it is the most important information on the
     * page.
     *
     * $leadDays limits to entries starting at most that many days ahead. The
     * site shows notices only 14 days before (otherwise "New Year's Eve:
     * closed" would be visible in October); schema.org gets all.
     *
     * @return list<array{from: string, to: string, note: ?string, occasion: ?string, hours: ?array{0:int,1:int}, area: ?string}>
     *         Ascending by start.
     */
    public function upcomingClosures(?DateTimeImmutable $from = null, ?int $leadDays = null): array
    {
        $from = $from !== null ? $this->local($from) : $this->now()->setTime(0, 0);
        $today = $from->format('Y-m-d');
        $limit = $leadDays !== null ? $from->modify("+$leadDays day")->format('Y-m-d') : null;

        $found = array_values(array_filter(
            $this->closures,
            static fn (array $c) => $c['to'] >= $today && ($limit === null || $c['from'] <= $limit)
        ));
        usort($found, static fn (array $a, array $b) => $a['from'] <=> $b['from']);

        return $found;
    }

    /**
     * The opening status at $now (default: now in the schedule's timezone).
     *
     * Order (SPEC.md §3.1.3): overnight block from yesterday → closure →
     * holiday (only if the plan would be open) → inside a block → later today
     * → next open day within the lookahead.
     *
     * @return array{
     *   open: bool, text: string, detail: string,
     *   state: 'open'|'closed'|'holiday'|'closure',
     *   until: ?string, next: ?string, occasion: ?string, holiday: ?string
     * }
     */
    public function status(?DateTimeImmutable $now = null): array
    {
        $now = $now !== null ? $this->local($now) : $this->now();
        $today = $now->setTime(0, 0);
        $minutes = (int) $now->format('G') * 60 + (int) $now->format('i');

        // 0. An overnight block of yesterday that is still running. It belongs
        //    to yesterday, so today's closure or holiday does not end it. Its
        //    end is the end of the run: today's blocks that follow directly
        //    extend it, unless today is closed.
        $yesterday = $today->modify('-1 day');
        if (!$this->exceptionOn($yesterday)) {
            foreach ($this->blocksOn($yesterday) as [$from, $to]) {
                if ($to > self::MINUTES_PER_DAY) {
                    $end = $to - self::MINUTES_PER_DAY;
                    if (!$this->exceptionOn($today)) {
                        $end = $this->runEnd($today, $end);
                    }
                    if ($minutes < $end) {
                        return $this->openResult($end, $this->specialOn($yesterday)['occasion'] ?? null);
                    }
                }
            }
        }

        // 1. Closure today.
        if ($closure = $this->closureOn($now)) {
            // A single day (New Year's Eve) reads like a holiday:
            // "Silvester: geschlossen — öffnet am 2.1. um 8:30 Uhr".
            if ($closure['from'] === $closure['to']) {
                $next = $this->nextOpenDay($today);
                return $this->result(
                    false,
                    $closure['occasion'] !== null
                        ? I18n::t('status.occasionClosed', ['occasion' => $closure['occasion']])
                        : I18n::t('status.closedToday'),
                    $closure['note'] ?? ($next !== null ? $this->opens($today, $next) : ''),
                    'closure',
                    next: $this->nextIso($next),
                    occasion: $closure['occasion'],
                );
            }
            // Not simply "end + 1 day": if the closure ends on Saturday and
            // Sunday is a rest day, the business opens on Monday. Another
            // closure may follow directly. Without a known opening day (no
            // plan) no date rather than a wrong one.
            $end = DateTimeImmutable::createFromFormat('!Y-m-d', $closure['to'], $this->timezone);
            $next = $end ? $this->nextOpenDay($end) : null;
            return $this->result(
                false,
                $this->label($closure),
                $closure['note'] ?? ($next !== null ? I18n::t('detail.backOn', ['date' => I18n::date($next)]) : ''),
                'closure',
                next: $this->nextIso($next),
                occasion: $closure['occasion'],
            );
        }

        // 2. Public holiday: like a single-day closure. Only when the plan
        //    would be open today; on a rest day it stays "Heute geschlossen".
        $holiday = $this->specialOn($now) === null ? $this->holidayOn($now) : null;
        if ($holiday !== null && $this->blocks($this->weekday($now)) !== []) {
            $next = $this->nextOpenDay($today);
            return $this->result(
                false,
                I18n::t('status.holidayToday'),
                $next !== null ? $this->opens($today, $next) : $holiday,
                'holiday',
                next: $this->nextIso($next),
                holiday: $holiday,
            );
        }

        $todayBlocks = $this->blocksOn($now);
        $special = $this->specialOn($now);

        // 3. Inside a block: until the end of the run, which may continue
        //    after midnight (Friday 18:00–00:00 and Saturday 00:00–02:00).
        foreach ($todayBlocks as [$from, $to]) {
            if ($minutes >= $from && $minutes < $to) {
                return $this->openResult($this->runEnd($today, $to), $special['occasion'] ?? null);
            }
        }

        // 4. Later today.
        foreach ($todayBlocks as [$from]) {
            if ($minutes < $from) {
                return $this->result(
                    false,
                    I18n::t('status.closedNow'),
                    I18n::t('detail.opensToday', ['time' => self::formatTime($from)]),
                    'closed',
                    next: $today->setTime(intdiv($from, 60), $from % 60)->format('Y-m-d\TH:i'),
                );
            }
        }

        // 5. The next day with hours, closures and holidays skipped.
        $next = $this->nextOpenDay($today);
        $detail = $next !== null ? $this->opens($today, $next) : '';
        $tomorrowHoliday = null;

        // Evening before a holiday (medical practice, opt-in): name the reason,
        // otherwise "öffnet am Montag" on Friday evening before 3 October
        // makes people wonder why not tomorrow. Only when tomorrow would be
        // open by the weekly plan and no closure or special hours apply.
        if ($this->holidayEve) {
            $tomorrow = $today->modify('+1 day');
            $name = $this->holidayOn($tomorrow);
            if ($name !== null
                && $this->specialOn($tomorrow) === null
                && $this->closureOn($tomorrow) === null
                && $this->blocks($this->weekday($tomorrow)) !== []
            ) {
                $tomorrowHoliday = $name;
                $notice = I18n::t('detail.holidayTomorrow', ['name' => $name]);
                $detail = $detail !== '' ? $notice . I18n::t('detail.separator') . $detail : $notice;
            }
        }

        return $this->result(
            false,
            $todayBlocks === [] ? I18n::t('status.closedToday') : I18n::t('status.closedNow'),
            $detail,
            'closed',
            next: $this->nextIso($next),
            holiday: $tomorrowHoliday,
        );
    }

    /**
     * End of the opening run that reaches $end: minutes since 0:00 of $day,
     * may exceed 1440 for an overnight end. Blocks that start at or before the
     * end extend it, on this day and across midnight, as long as the next day
     * is not closed by a closure or a holiday. Returns minutes of $day's frame.
     */
    private function runEnd(DateTimeImmutable $day, int $end): int
    {
        $shift = 0;
        for ($hop = 0; $hop < 8; $hop++) {
            do {
                $extended = false;
                foreach ($this->blocksOn($day) as [$from, $to]) {
                    if ($from <= $end && $to > $end) {
                        $end = $to;
                        $extended = true;
                    }
                }
            } while ($extended);

            if ($end < self::MINUTES_PER_DAY) {
                break;
            }
            $next = $day->modify('+1 day');
            if ($this->exceptionOn($next)) {
                break;
            }
            $end -= self::MINUTES_PER_DAY;
            $shift += self::MINUTES_PER_DAY;
            $day = $next;
        }
        return $end + $shift;
    }

    /**
     * First day AFTER $day with hours and without closure or holiday, or null
     * when none comes within the lookahead (for example without a plan).
     */
    private function nextOpenDay(DateTimeImmutable $day): ?DateTimeImmutable
    {
        $day = $day->setTime(0, 0);
        for ($d = 1; $d <= $this->lookahead; $d++) {
            $candidate = $day->modify("+$d day");
            if (!$this->exceptionOn($candidate) && $this->blocksOn($candidate) !== []) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * "öffnet morgen um …", "öffnet am Montag um …" or "öffnet am 3.10. um …".
     * A weekday name only carries within the coming week; "am Montag" for a
     * day two and a half weeks away reads like the day after tomorrow.
     */
    private function opens(DateTimeImmutable $today, DateTimeImmutable $day): string
    {
        $d = (int) $today->setTime(0, 0)->diff($day->setTime(0, 0))->days;
        $time = self::formatTime($this->blocksOn($day)[0][0]);
        return match (true) {
            $d === 1 => I18n::t('detail.opensTomorrow', ['time' => $time]),
            $d <= 6  => I18n::t('detail.opensWeekday', ['weekday' => I18n::day($this->weekday($day)), 'time' => $time]),
            default  => I18n::t('detail.opensDate', ['date' => I18n::date($day), 'time' => $time]),
        };
    }

    private function nextIso(?DateTimeImmutable $day): ?string
    {
        if ($day === null) {
            return null;
        }
        $start = $this->blocksOn($day)[0][0];
        return $day->setTime(intdiv($start, 60), $start % 60)->format('Y-m-d\TH:i');
    }

    private function openResult(int $to, ?string $occasion): array
    {
        $time = self::formatTime($to);
        return $this->result(
            true,
            I18n::t('status.openNow'),
            $occasion !== null
                ? I18n::t('detail.untilOccasion', ['time' => $time, 'occasion' => $occasion])
                : I18n::t('detail.until', ['time' => $time]),
            'open',
            until: $time,
            occasion: $occasion,
        );
    }

    private function result(
        bool $open,
        string $text,
        string $detail,
        string $state,
        ?string $until = null,
        ?string $next = null,
        ?string $occasion = null,
        ?string $holiday = null,
    ): array {
        return [
            'open'     => $open,
            'text'     => $text,
            'detail'   => $detail,
            'state'    => $state,
            'until'    => $until,
            'next'     => $next,
            'occasion' => $occasion,
            'holiday'  => $holiday,
        ];
    }

    private function weekday(DateTimeImmutable $day): int
    {
        return (int) $this->local($day)->format('N') - 1;
    }

    private function local(DateTimeImmutable $day): DateTimeImmutable
    {
        return $day->getTimezone()->getName() === $this->timezone->getName() ? $day : $day->setTimezone($this->timezone);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
