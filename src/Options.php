<?php

namespace Sayamaapps\Noren;

use Kirby\Cms\App;
use Kirby\Cms\Helpers;

/**
 * Reads Noren's options: `sayamaapps.noren.<key>`, falling back to the 1.x name `noren.<key>`.
 *
 * `null` counts as "not set", like Kirby's own option() (A::get() checks
 * isset() as well), so `'noren.timezone' => null` falls back to the default.
 *
 * A broken value is logged once per process and the default is used, except
 * with Kirby's `debug` option on: then it throws, so a developer sees it on
 * the page instead of in the log (README, Configuration).
 *
 * Both spellings work, flat (`'noren.active' => true`) and nested
 * (`'noren' => ['active' => true]`), because Kirby's option() finds both.
 *
 * Defaults are given here and at the call sites. The `options` block of the
 * plugin lists the keys with null: a default there would hide the 1.x spelling.
 */
final class Options
{
	/** Content field names on a location page (SPEC.md §6). Each key can be overridden in `noren.fields`. */
	public const FIELDS = [
		'areas'    => 'bereiche',
		'hours'    => 'zeiten',
		'closures' => 'schliessungen',
		'areaRef'  => 'zeitenschluessel',
		'short'    => 'kurzname',
	];

	/** Option prefix of 1.x, read as a fallback until 2.0. */
	public const LEGACY_PREFIX = 'noren.';

	/**
	 * Legacy zone names (tzdata "backward" links) mapped to the canonical zone
	 * with the same rules. Some tzdata builds, e.g. a new CI runner image, lack
	 * them, and PHP then rejects the old name.
	 */
	public const LEGACY_ZONES = [
		'Asia/Calcutta'  => 'Asia/Kolkata',
		'Asia/Katmandu'  => 'Asia/Kathmandu',
		'Asia/Saigon'    => 'Asia/Ho_Chi_Minh',
		'Europe/Kiev'    => 'Europe/Kyiv',
		'US/Central'     => 'America/Chicago',
		'US/Eastern'     => 'America/New_York',
		'US/Mountain'    => 'America/Denver',
		'US/Pacific'     => 'America/Los_Angeles',
	];

	/** Option prefix since 1.4 (OPT-1). */
	public const PREFIX = 'sayamaapps.noren.';

	/** Master switch: only a real `true` switches Noren on. */
	public static function active(App $kirby): bool
	{
		return self::get($kirby, 'active', false) === true;
	}

	/** Sets I18n's language for this request from language(). Called before any text is produced. */
	public static function applyLanguage(App $kirby): void
	{
		I18n::setLanguage(self::language($kirby));
	}

	/** `noren.closureLabel` (string), otherwise null = the translated default ("Betriebsferien"). */
	public static function closureLabel(App $kirby): string|null
	{
		$label = self::get($kirby, 'closureLabel');
		return is_string($label) && trim($label) !== '' ? trim($label) : null;
	}

	/**
	 * Field names, `noren.fields` merged over the defaults. Unknown keys are
	 * ignored, empty or non-string values keep the default.
	 *
	 * @return array{areas:string, hours:string, closures:string, areaRef:string, short:string}
	 */
	public static function fields(App $kirby): array
	{
		$fields = self::FIELDS;
		$option = self::get($kirby, 'fields');
		if (is_array($option)) {
			foreach ($fields as $key => $default) {
				$value = $option[$key] ?? null;
				if (is_string($value) && trim($value) !== '') {
					$fields[$key] = trim($value);
				}
			}
		}
		return $fields;
	}

	/**
	 * A boolean option. Only a real true or false counts: the string 'false'
	 * (typical for env-driven config) is not a switch, so it is reported and
	 * the default is used.
	 */
	public static function flag(App $kirby, string $key, bool $default): bool
	{
		$value = self::get($kirby, $key, $default);
		if (is_bool($value)) {
			return $value;
		}
		self::invalid($kirby, 'Noren: noren.' . $key . ' must be true or false — using ' . var_export($default, true) . '.');
		return $default;
	}

	public static function get(App $kirby, string $key, mixed $default = null): mixed
	{
		$value = $kirby->option(self::PREFIX . $key);
		if ($value !== null) {
			return $value;
		}

		// The 1.x spelling still works, with a deprecation (README, Deprecations).
		$legacy = $kirby->option(self::LEGACY_PREFIX . $key);
		if ($legacy !== null) {
			Helpers::deprecated(
				'Noren: noren.' . $key . ' is deprecated, use ' . self::PREFIX . $key,
				'option-prefix'
			);
		}

		return $legacy ?? $default;
	}

	/**
	 * A broken option: with Kirby's `debug` option on it throws, so the
	 * developer sees it on the page; otherwise it is logged once and the
	 * caller uses the default.
	 */
	public static function invalid(App $kirby, string $message): void
	{
		if ($kirby->option('debug') === true) {
			throw new \InvalidArgumentException($message);
		}
		Log::error($message);
	}

	private static function knowsZone(string $zone): bool
	{
		try {
			new \DateTimeZone($zone);
			return true;
		} catch (\Exception) {
			return false;
		}
	}

	/**
	 * Language of the plugin's text: `noren.language`, otherwise the current
	 * language of a multi-language site, otherwise its default language,
	 * otherwise the first two letters of Kirby's `locale` option, otherwise
	 * English. Only languages Noren ships (I18n::LANGUAGES) are returned.
	 */
	public static function language(App $kirby): string
	{
		$candidates = [self::get($kirby, 'language')];
		try {
			$candidates[] = $kirby->language()?->code();
			$candidates[] = $kirby->defaultLanguage()?->code();
		} catch (\Throwable) {
			// No languages configured.
		}
		$locale = $kirby->option('locale');
		if (is_array($locale)) {
			$locale = $locale[LC_ALL] ?? reset($locale);
		}
		$candidates[] = is_string($locale) ? substr($locale, 0, 2) : null;

		foreach ($candidates as $candidate) {
			if (is_string($candidate) && in_array(strtolower($candidate), I18n::LANGUAGES, true)) {
				return strtolower($candidate);
			}
		}
		return 'en';
	}

	/** The canonical zone of a legacy name (`Europe/Kiev` gives `Europe/Kyiv`), or null. */
	public static function legacyZone(string $zone): string|null
	{
		return self::LEGACY_ZONES[$zone] ?? null;
	}

	/**
	 * `noren.mergeAreas`: areas of one location with identical output become
	 * one unit, "Hofladen und Hofcafé" (Locations::units(), SPEC.md §3.7).
	 * Off by default: every area is its own unit.
	 */
	public static function mergeAreas(App $kirby): bool
	{
		return self::flag($kirby, 'mergeAreas', false);
	}

	/**
	 * `noren.overnightUntil`: latest end time ("HH:MM") on the next day up to
	 * which a block whose end lies before its start counts as overnight
	 * (Fri 18:00–02:00). Default "06:00". `false` switches overnight blocks
	 * off: a row that ends after midnight is then ignored, while a
	 * row ending at 00:00 still counts as "until midnight" (Schedule::block()).
	 * Returns minutes or null.
	 */
	public static function overnightUntil(App $kirby): int|null
	{
		$value = self::get($kirby, 'overnightUntil', '06:00');
		if ($value === false) {
			return null;
		}
		$minutes = is_string($value) ? Schedule::parseTime($value) : null;
		if ($minutes === null) {
			self::invalid($kirby, 'Noren: noren.overnightUntil must be "HH:MM" or false — using 06:00.');
			return Schedule::DEFAULT_OVERNIGHT_UNTIL;
		}
		return $minutes;
	}

	/**
	 * Every setting the logic and the snippets need, normalised, with the
	 * defaults of SPEC.md §4. Kirby-free consumers (Schedule, Presenter)
	 * receive this array or parts of it.
	 *
	 * @return array{
	 *   timezone: string, holidayEve: bool, lookahead: int, overnightUntil: ?int,
	 *   leadDays: int, holidayRow: bool, holidayNote: bool, holidayNoteDays: int,
	 *   closureLabel: ?string, compact: string, status: bool
	 * }
	 */
	public static function settings(App $kirby): array
	{
		return [
			'timezone'        => self::timezone($kirby),
			'holidayEve'      => self::flag($kirby, 'holidayEve', false),
			'lookahead'       => max(1, (int)self::get($kirby, 'lookahead', Schedule::DEFAULT_LOOKAHEAD)),
			'overnightUntil'  => self::overnightUntil($kirby),
			'leadDays'        => max(0, (int)self::get($kirby, 'leadDays', 14)),
			'holidayRow'      => self::flag($kirby, 'holidayRow', true),
			'holidayNote'     => self::flag($kirby, 'holidayNote', true),
			'holidayNoteDays' => max(0, (int)self::get($kirby, 'holidayNoteDays', 14)),
			'closureLabel'    => self::closureLabel($kirby),
			'compact'         => self::get($kirby, 'compact', 'grouped') === 'consecutive' ? 'consecutive' : 'grouped',
			'status'          => self::flag($kirby, 'status', true),
		];
	}

	/**
	 * Templates whose pages are locations: `noren.template`, a string or a
	 * list of strings. Default `['betrieb']`.
	 *
	 * @return list<string>
	 */
	public static function templates(App $kirby): array
	{
		$option = self::get($kirby, 'template', 'betrieb');
		$list = is_array($option) ? $option : [$option];
		$templates = [];
		foreach ($list as $template) {
			if (is_string($template) && trim($template) !== '') {
				$templates[] = trim($template);
			}
		}
		return $templates !== [] ? array_values(array_unique($templates)) : ['betrieb'];
	}

	/**
	 * `noren.timezone`, default Europe/Berlin. Any zone PHP accepts is valid,
	 * including the ones missing from DateTimeZone::listIdentifiers() (Etc/UTC,
	 * GMT). A legacy name PHP does not know resolves to its canonical zone
	 * (Europe/Kiev gives Europe/Kyiv). An invalid zone is logged and replaced by the default.
	 */
	public static function timezone(App $kirby): string
	{
		$zone = self::get($kirby, 'timezone', Schedule::DEFAULT_TIMEZONE);
		if (is_string($zone)) {
			if (self::knowsZone($zone)) {
				return $zone;
			}
			$canonical = self::legacyZone($zone);
			if ($canonical !== null && self::knowsZone($canonical)) {
				return $canonical;
			}
		}
		self::invalid($kirby, 'Noren: unknown noren.timezone ' . var_export($zone, true) . ' — using ' . Schedule::DEFAULT_TIMEZONE . '.');
		return Schedule::DEFAULT_TIMEZONE;
	}
}
