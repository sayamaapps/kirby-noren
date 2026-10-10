<?php

namespace Noren;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Kirby\Cms\App;
use Kirby\Cms\Page;

/**
 * The Kirby side (SPEC.md §3.7): which pages are locations, their areas,
 * schedules built from their fields with the site's options, the units for
 * the status band, and the null-safe wrappers behind the site and page
 * methods registered in index.php.
 *
 * OWNER: task `kirby`.
 *
 * NEVER memoise a schedule keyed on the page id (two areas share a page).
 */
final class Locations
{
    /** All location pages, in order. [] when Noren is off. @return list<Page> */
    public static function pages(App $kirby): array
    {
        if (!Options::active($kirby)) {
            return [];
        }

        $option = Options::get($kirby, 'locations');
        $candidates = [];
        if ($option !== null && $option !== false) {
            // A Closure may return Pages, a Page, or a list of Pages or ids.
            // A list of ids is resolved here.
            $value = $option instanceof Closure ? $option() : $option;
            $candidates = self::candidates($value);
        } else {
            $templates = Options::templates($kirby);
            foreach ($kirby->site()->index() as $page) {
                if (in_array($page->intendedTemplate()->name(), $templates, true)) {
                    $candidates[] = $page;
                }
            }
        }

        $pages = [];
        $seen = [];
        foreach ($candidates as $item) {
            $page = is_string($item) ? $kirby->page($item) : $item;
            if (!$page instanceof Page || isset($seen[$page->id()])) {
                continue;
            }
            $seen[$page->id()] = true;
            $pages[] = $page;
        }
        return $pages;
    }

    /** The first location page, or null. */
    public static function primary(App $kirby): ?Page
    {
        return self::pages($kirby)[0] ?? null;
    }

    /** Is this page one of pages()? */
    public static function isLocation(Page $page): bool
    {
        $kirby = App::instance();
        foreach (self::pages($kirby) as $location) {
            if ($location->id() === $page->id()) {
                return true;
            }
        }
        return false;
    }

    /** Areas of a location page. @return list<array{key: string, name: string, short: string}> */
    public static function areas(Page $page): array
    {
        $kirby = App::instance();
        if (!Options::active($kirby)) {
            return [];
        }

        $areas = [];
        foreach (self::rows($page, Options::fields($kirby)['areas']) as $row) {
            $key = trim((string) $row->schluessel());
            if ($key === '') {
                continue;
            }
            $name = trim((string) $row->name());
            $short = trim((string) $row->kurzname());
            $name = $name !== '' ? $name : $key;
            $areas[] = [
                'key'   => $key,
                'name'  => $name,
                'short' => $short !== '' ? $short : $name,
            ];
        }
        return $areas;
    }

    /** Schedule from THIS page's own fields (area filter applied). null when Noren is off. */
    public static function schedule(Page $page, ?string $area = null): ?Schedule
    {
        $kirby = App::instance();
        if (!Options::active($kirby)) {
            return null;
        }

        // The schedule's text (closure label, status) is produced later, so the
        // language is set here, not only in the wrappers.
        Options::applyLanguage($kirby);
        $fields = Options::fields($kirby);
        return Schedule::fromFields(
            self::structure($page, $fields['hours']),
            self::structure($page, $fields['closures']),
            $area,
            Options::closureLabel($kirby),
            self::holidayChecker($kirby),
            self::scheduleSettings($kirby),
        );
    }

    /**
     * Page-method semantics: a location page → its own schedule; any other
     * page → the primary location's schedule for $area, else for the area
     * named in this page's `areaRef` field, else for all rows. null when
     * Noren is off or there is no location.
     */
    public static function scheduleFor(Page $page, ?string $area = null): ?Schedule
    {
        $kirby = App::instance();
        if (!Options::active($kirby)) {
            return null;
        }

        if (self::isLocation($page)) {
            return self::schedule($page, $area);
        }

        $location = self::primary($kirby);
        if ($location === null) {
            return null;
        }

        if ($area === null) {
            $reference = trim((string) $page->content()->get(Options::fields($kirby)['areaRef']));
            $area = $reference !== '' ? $reference : null;
        }
        return self::schedule($location, $area);
    }

    /**
     * Every (location, area) with maintained hours. [] when off.
     *
     * @return list<array{id: string, location: Page, area: ?string, name: string, short: string, label: string, schedule: Schedule}>
     */
    public static function units(App $kirby): array
    {
        if (!Options::active($kirby)) {
            return [];
        }

        $locations = self::pages($kirby);
        $several = count($locations) > 1;
        $units = [];

        foreach ($locations as $page) {
            $title = trim((string) $page->title());
            $locationShort = trim((string) $page->content()->get(Options::fields($kirby)['short']));
            $locationShort = $locationShort !== '' ? $locationShort : $title;
            $areas = self::areas($page);

            if ($areas === []) {
                $schedule = self::schedule($page, null);
                if ($schedule === null || !$schedule->maintained()) {
                    continue;
                }
                $units[] = [
                    'id'       => $page->id(),
                    'location' => $page,
                    'area'     => null,
                    'name'     => $title,
                    'short'    => $locationShort,
                    'label'    => $locationShort,
                    'schedule' => $schedule,
                ];
                continue;
            }

            foreach ($areas as $area) {
                $schedule = self::schedule($page, $area['key']);
                if ($schedule === null || !$schedule->maintained()) {
                    // No hours: "closed" would send people to a door that may be open.
                    continue;
                }
                $units[] = [
                    'id'       => $page->id() . '#' . $area['key'],
                    'location' => $page,
                    'area'     => $area['key'],
                    'name'     => $area['name'],
                    'short'    => $area['short'],
                    'label'    => $several ? $locationShort . ' · ' . $area['short'] : $area['short'],
                    'schedule' => $schedule,
                ];
            }
        }
        return $units;
    }

    /**
     * Holidays::config(noren.holidays); invalid → logged once, null. With
     * Kirby's `debug` option on, the error is thrown instead (README, Configuration).
     */
    public static function holidayConfig(App $kirby): ?array
    {
        try {
            return Holidays::config(Options::get($kirby, 'holidays', false));
        } catch (InvalidArgumentException $e) {
            if ($kirby->option('debug') === true) {
                throw $e;
            }
            Log::error('Noren: ' . $e->getMessage() . ' — public holidays are off.');
            return null;
        }
    }

    /** Holidays::checker() for holidayConfig(), or null. */
    public static function holidayChecker(App $kirby): ?Closure
    {
        $config = self::holidayConfig($kirby);
        return $config !== null ? Holidays::checker($config) : null;
    }

    /**
     * $schedule->status() with the site language set. null when there is no
     * schedule or no weekly plan: Noren does not claim "closed" for a plan it
     * does not have.
     */
    public static function status(?Schedule $schedule, ?DateTimeImmutable $now = null): ?array
    {
        if ($schedule === null || !$schedule->maintained()) {
            return null;
        }
        $kirby = App::instance();
        Options::applyLanguage($kirby);
        self::live($kirby);
        return $schedule->status($now);
    }

    /** Presenter::table() with the options of Options::settings(); null for no plan (see status()). */
    public static function table(?Schedule $schedule, ?DateTimeImmutable $now = null): ?array
    {
        if ($schedule === null || !$schedule->maintained()) {
            return null;
        }
        $kirby = App::instance();
        Options::applyLanguage($kirby);
        self::live($kirby);
        $settings = Options::settings($kirby);
        return Presenter::table($schedule, $now, [
            'holidayRow'      => $settings['holidayRow'],
            'holidayNote'     => $settings['holidayNote'],
            'holidayNoteDays' => $settings['holidayNoteDays'],
            'leadDays'        => $settings['leadDays'],
        ]);
    }

    /** Presenter::compact() with mode ($mode ?? noren.compact) and leadDays; null for no plan (see status()). */
    public static function compact(?Schedule $schedule, ?string $mode = null, ?DateTimeImmutable $now = null): ?array
    {
        if ($schedule === null || !$schedule->maintained()) {
            return null;
        }
        $kirby = App::instance();
        Options::applyLanguage($kirby);
        self::live($kirby);
        $settings = Options::settings($kirby);
        // An unknown mode falls back to the site's setting instead of reaching Presenter.
        $mode = in_array($mode, ['grouped', 'consecutive'], true) ? $mode : $settings['compact'];
        return Presenter::compact($schedule, $now, [
            'mode'     => $mode,
            'leadDays' => $settings['leadDays'],
        ]);
    }

    /** SchemaOrg::forSchedule(); null for no plan (see status()). */
    public static function schema(?Schedule $schedule): ?array
    {
        if ($schedule === null || !$schedule->maintained()) {
            return null;
        }
        $kirby = App::instance();
        Options::applyLanguage($kirby);
        self::live($kirby);
        return SchemaOrg::forSchedule($schedule);
    }

    /**
     * Output that depends on the time (the open status, the notes, the closures
     * still ahead) must not be kept by Kirby's page cache: a page rendered on
     * Monday would otherwise say "open until 18:00" on Sunday.
     */
    private static function live(App $kirby): void
    {
        $kirby->response()->cache(false);
    }

    /** Holidays of a year (default: this year in noren.timezone): date => name. [] when off. */
    public static function holidays(App $kirby, ?int $year = null): array
    {
        if (!Options::active($kirby)) {
            return [];
        }
        $config = self::holidayConfig($kirby);
        if ($config === null) {
            return [];
        }
        Options::applyLanguage($kirby);
        $year ??= (int) (new DateTimeImmutable('now', new DateTimeZone(Options::timezone($kirby))))->format('Y');
        return Holidays::forYear($year, $config);
    }

    /**
     * Settings for Schedule::fromFields(), a subset of Options::settings().
     *
     * @return array{timezone: string, holidayEve: bool, lookahead: int, overnightUntil: ?int}
     */
    private static function scheduleSettings(App $kirby): array
    {
        $settings = Options::settings($kirby);
        return [
            'timezone'       => $settings['timezone'],
            'holidayEve'     => $settings['holidayEve'],
            'lookahead'      => $settings['lookahead'],
            'overnightUntil' => $settings['overnightUntil'],
        ];
    }

    /**
     * A structure field of a page, or null when it is missing or is not valid
     * structure data (a plain-text `zeiten` field, a hand-edited list). The
     * latter is logged once; the page then simply has no such rows, instead of
     * every page with the status band failing.
     */
    private static function structure(Page $page, string $field): ?object
    {
        $value = $page->content()->get($field);
        // No method_exists(): Kirby's Field provides toStructure() via __call.
        if (!is_object($value)) {
            return null;
        }
        try {
            $value->toStructure();
        } catch (\Throwable) {
            Log::error('Noren: field ' . $field . ' on page ' . $page->id() . ' is not structure data — ignored.');
            return null;
        }
        return $value;
    }

    /**
     * Rows of a structure field of a page as StructureObjects. A missing or
     * non-structure value gives [].
     *
     * @return iterable<object>
     */
    private static function rows(Page $page, string $field): iterable
    {
        $value = self::structure($page, $field);
        return $value !== null ? ($value->toStructure() ?? []) : [];
    }

    /**
     * Normalises the value of noren.locations to a flat list of Page objects
     * or page ids (strings), in order.
     *
     * @return list<Page|string>
     */
    private static function candidates(mixed $value): array
    {
        if ($value instanceof Page || is_string($value)) {
            return [$value];
        }
        if (!is_iterable($value)) {
            return [];
        }
        $list = [];
        foreach ($value as $item) {
            if ($item instanceof Page || is_string($item)) {
                $list[] = $item;
            }
        }
        return $list;
    }
}
