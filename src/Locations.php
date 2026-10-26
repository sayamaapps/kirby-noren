<?php

namespace Sayamaapps\Noren;

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
 * NEVER memoise a schedule keyed on the page id (two areas share a page).
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-noren
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
final class Locations
{
	/** Areas of a location page. @return list<array{key: string, name: string, short: string}> */
	public static function areas(Page $page): array
	{
		$kirby = App::instance();
		if (!Options::active($kirby)) {
			return [];
		}

		$areas = [];
		foreach (self::rows($page, Options::fields($kirby)['areas']) as $row) {
			$key = trim((string)$row->schluessel());
			if ($key === '') {
				continue;
			}
			$name = trim((string)$row->name());
			$short = trim((string)$row->kurzname());
			$name = $name !== '' ? $name : $key;
			$areas[] = [
				'key'   => $key,
				'name'  => $name,
				'short' => $short !== '' ? $short : $name,
			];
		}
		return $areas;
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

	/** Presenter::compact() with mode ($mode ?? noren.compact) and leadDays; null for no plan (see status()). */
	public static function compact(Schedule|null $schedule, string|null $mode = null, DateTimeImmutable|null $now = null): array|null
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

	/** Holidays::checker() for holidayConfig(), or null. */
	public static function holidayChecker(App $kirby): Closure|null
	{
		$config = self::holidayConfig($kirby);
		return $config !== null ? Holidays::checker($config) : null;
	}

	/**
	 * Holidays::config(noren.holidays); invalid → logged once, null. With
	 * Kirby's `debug` option on, the error is thrown instead (README, Configuration).
	 */
	public static function holidayConfig(App $kirby): array|null
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

	/** Holidays of a year (default: this year in noren.timezone): date => name. [] when off. */
	public static function holidays(App $kirby, int|null $year = null): array
	{
		if (!Options::active($kirby)) {
			return [];
		}
		$config = self::holidayConfig($kirby);
		if ($config === null) {
			return [];
		}
		Options::applyLanguage($kirby);
		$year ??= (int)(new DateTimeImmutable('now', new DateTimeZone(Options::timezone($kirby))))->format('Y');
		return Holidays::forYear($year, $config);
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

	/** ["A", "B", "C"] → "A, B und C" (de) / "A, B and C" (en), the word from compact.and. */
	private static function joinNames(array $names): string
	{
		$last = (string)array_pop($names);
		if ($names === []) {
			return $last;
		}
		return implode(', ', $names) . ' ' . trim(I18n::t('compact.and')) . ' ' . $last;
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

	/**
	 * `noren.mergeAreas` (SPEC.md §3.7): the areas of ONE location whose
	 * output is identical at $now (default: now) become one unit, named
	 * "Hofladen und Hofcafé". Identical means status() and Presenter::table() (with notes,
	 * closures ahead and the holiday note), so a closure for one area splits
	 * them again. A group sits where its first area was. The group's schedule
	 * is the first area's; schema.org stays per area, see SPEC.md §3.7.
	 *
	 * @param list<array> $found units of one location, in area order
	 * @return list<array>
	 */
	private static function merge(array $found, Page $page, string $locationShort, bool $several, App $kirby, DateTimeImmutable|null $now): array
	{
		$settings = Options::settings($kirby);
		$now ??= new DateTimeImmutable('now', $found[0]['schedule']->timezone());

		$groups = [];
		foreach ($found as $unit) {
			$schedule = $unit['schedule'];
			$output = serialize([
				$schedule->status($now),
				Presenter::table($schedule, $now, [
					'holidayRow'      => $settings['holidayRow'],
					'holidayNote'     => $settings['holidayNote'],
					'holidayNoteDays' => $settings['holidayNoteDays'],
					'leadDays'        => $settings['leadDays'],
				]),
			]);
			$groups[$output][] = $unit;
		}

		$units = [];
		foreach ($groups as $members) {
			$first = $members[0];
			$keys = array_column($members, 'area');
			if (count($members) === 1) {
				$units[] = $first + ['areas' => $keys];
				continue;
			}
			$short = self::joinNames(array_column($members, 'short'));
			$units[] = [
				'id'       => $page->id() . '#' . implode('+', $keys),
				'location' => $page,
				'area'     => $first['area'],
				'areas'    => $keys,
				'name'     => self::joinNames(array_column($members, 'name')),
				'short'    => $short,
				'label'    => $several ? $locationShort . ' · ' . $short : $short,
				'schedule' => $first['schedule'],
			];
		}
		return $units;
	}

	/** Areas of a location page, or of the first location for any other page (page methods). */
	public static function pageAreas(Page $page): array
	{
		if (self::isLocation($page)) {
			return self::areas($page);
		}
		return self::siteAreas($page->kirby());
	}

	/** Whether the page has (or points to) maintained hours (page methods). */
	public static function pageHasHours(Page $page, string|null $area = null): bool
	{
		return self::scheduleFor($page, $area)?->maintained() === true;
	}

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
	public static function primary(App $kirby): Page|null
	{
		return self::pages($kirby)[0] ?? null;
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

	/** Schedule from THIS page's own fields (area filter applied). null when Noren is off. */
	public static function schedule(Page $page, string|null $area = null): Schedule|null
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
	public static function scheduleFor(Page $page, string|null $area = null): Schedule|null
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
			$reference = trim((string)$page->content()->get(Options::fields($kirby)['areaRef']));
			$area = $reference !== '' ? $reference : null;
		}
		return self::schedule($location, $area);
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

	/** SchemaOrg::forSchedule(); null for no plan (see status()). */
	public static function schema(Schedule|null $schedule): array|null
	{
		if ($schedule === null || !$schedule->maintained()) {
			return null;
		}
		$kirby = App::instance();
		Options::applyLanguage($kirby);
		self::live($kirby);
		return SchemaOrg::forSchedule($schedule);
	}

	/** Areas of the first location (site methods). */
	public static function siteAreas(App $kirby): array
	{
		$location = self::primary($kirby);
		return $location !== null ? self::areas($location) : [];
	}

	/** Schedule of the first location for an area, or null (site methods). */
	public static function siteSchedule(App $kirby, string|null $area = null): Schedule|null
	{
		$location = self::primary($kirby);
		return $location !== null ? self::schedule($location, $area) : null;
	}

	/**
	 * $schedule->status() with the site language set. null when there is no
	 * schedule or no weekly plan: Noren does not claim "closed" for a plan it
	 * does not have.
	 */
	public static function status(Schedule|null $schedule, DateTimeImmutable|null $now = null): array|null
	{
		if ($schedule === null || !$schedule->maintained()) {
			return null;
		}
		$kirby = App::instance();
		Options::applyLanguage($kirby);
		self::live($kirby);
		return $schedule->status($now);
	}

	/**
	 * A structure field of a page, or null when it is missing or is not valid
	 * structure data (a plain-text `zeiten` field, a hand-edited list). The
	 * latter is logged once; the page then simply has no such rows, instead of
	 * every page with the status band failing.
	 */
	private static function structure(Page $page, string $field): object|null
	{
		$value = $page->content()->get($field);
		// No method_exists(): Kirby's Field provides toStructure() via __call.
		if (!is_object($value)) {
			return null;
		}
		try {
			/** @psalm-suppress UndefinedMagicMethod Kirby's Field forwards toStructure() through __call(). */
			$value->toStructure();
		} catch (\Throwable) {
			Log::error('Noren: field ' . $field . ' on page ' . $page->id() . ' is not structure data — ignored.');
			return null;
		}
		return $value;
	}

	/** Presenter::table() with the options of Options::settings(); null for no plan (see status()). */
	public static function table(Schedule|null $schedule, DateTimeImmutable|null $now = null): array|null
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

	/**
	 * Every (location, area) with maintained hours. [] when off.
	 *
	 * $now: the moment `noren.mergeAreas` compares the areas at — the moment
	 * the caller shows (status snippet's `$now`), default now. $merge false:
	 * one unit per area even with `noren.mergeAreas` on, for consumers that
	 * need every area's own data (schema.org, llms.txt), not the display.
	 *
	 * @return list<array{id: string, location: Page, area: ?string, name: string, short: string, label: string, schedule: Schedule}>
	 */
	public static function units(App $kirby, DateTimeImmutable|null $now = null, bool $merge = true): array
	{
		if (!Options::active($kirby)) {
			return [];
		}

		$locations = self::pages($kirby);
		$several = count($locations) > 1;
		$units = [];

		foreach ($locations as $page) {
			$title = trim((string)$page->title());
			$locationShort = trim((string)$page->content()->get(Options::fields($kirby)['short']));
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

			$found = [];
			foreach ($areas as $area) {
				$schedule = self::schedule($page, $area['key']);
				if ($schedule === null || !$schedule->maintained()) {
					// No hours: "closed" would send people to a door that may be open.
					continue;
				}
				$found[] = [
					'id'       => $page->id() . '#' . $area['key'],
					'location' => $page,
					'area'     => $area['key'],
					'name'     => $area['name'],
					'short'    => $area['short'],
					'label'    => $several ? $locationShort . ' · ' . $area['short'] : $area['short'],
					'schedule' => $schedule,
				];
			}
			$units = array_merge($units, $merge && Options::mergeAreas($kirby) && $found !== []
				? self::merge($found, $page, $locationShort, $several, $kirby, $now)
				: $found);
		}
		return $units;
	}
}
