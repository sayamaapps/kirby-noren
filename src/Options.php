<?php

namespace Noren;

use Kirby\Cms\App;

/**
 * Reads `noren.*` options. Shared file — owned by the architect (SPEC.md §4).
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
 * Defaults are given here and at the call sites, never in an `options`
 * block of the plugin (Kirby would file them under sayamaapps.noren.*).
 */
final class Options
{
    public const PREFIX = 'noren.';

    /** Content field names on a location page (SPEC.md §6). Each key can be overridden in `noren.fields`. */
    public const FIELDS = [
        'areas'    => 'bereiche',
        'hours'    => 'zeiten',
        'closures' => 'schliessungen',
        'areaRef'  => 'zeitenschluessel',
        'short'    => 'kurzname',
    ];

    public static function get(App $kirby, string $key, mixed $default = null): mixed
    {
        return $kirby->option(self::PREFIX . $key) ?? $default;
    }

    /** Master switch: only a real `true` switches Noren on. */
    public static function active(App $kirby): bool
    {
        return self::get($kirby, 'active', false) === true;
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
     * Templates whose pages are locations: `noren.template`, a string or a
     * list of strings. Default `['betrieb']` (the starter's template).
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
            'lookahead'       => max(1, (int) self::get($kirby, 'lookahead', Schedule::DEFAULT_LOOKAHEAD)),
            'overnightUntil'  => self::overnightUntil($kirby),
            'leadDays'        => max(0, (int) self::get($kirby, 'leadDays', 14)),
            'holidayRow'      => self::flag($kirby, 'holidayRow', true),
            'holidayNote'     => self::flag($kirby, 'holidayNote', true),
            'holidayNoteDays' => max(0, (int) self::get($kirby, 'holidayNoteDays', 14)),
            'closureLabel'    => self::closureLabel($kirby),
            'compact'         => self::get($kirby, 'compact', 'grouped') === 'consecutive' ? 'consecutive' : 'grouped',
            'status'          => self::flag($kirby, 'status', true),
        ];
    }

    /**
     * `noren.timezone`, default Europe/Berlin. Any zone PHP accepts is valid,
     * including the ones missing from DateTimeZone::listIdentifiers() (Etc/UTC,
     * GMT, Europe/Kiev). An invalid zone is logged and replaced by the default.
     */
    public static function timezone(App $kirby): string
    {
        $zone = self::get($kirby, 'timezone', Schedule::DEFAULT_TIMEZONE);
        if (is_string($zone)) {
            try {
                new \DateTimeZone($zone);
                return $zone;
            } catch (\Exception) {
                // Reported below.
            }
        }
        self::invalid($kirby, 'Noren: unknown noren.timezone ' . var_export($zone, true) . ' — using ' . Schedule::DEFAULT_TIMEZONE . '.');
        return Schedule::DEFAULT_TIMEZONE;
    }

    /**
     * `noren.overnightUntil`: latest end time ("HH:MM") on the next day up to
     * which a block whose end lies before its start counts as overnight
     * (Fri 18:00–02:00). Default "06:00". `false` switches overnight blocks
     * off: a row that ends after midnight is then ignored, as before, while a
     * row ending at 00:00 still counts as "until midnight" (Schedule::block()).
     * Returns minutes or null.
     */
    public static function overnightUntil(App $kirby): ?int
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

    /** `noren.closureLabel` (string), otherwise null = the translated default ("Betriebsferien"). */
    public static function closureLabel(App $kirby): ?string
    {
        $label = self::get($kirby, 'closureLabel');
        return is_string($label) && trim($label) !== '' ? trim($label) : null;
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

    /** Sets I18n's language for this request from language(). Called before any text is produced. */
    public static function applyLanguage(App $kirby): void
    {
        I18n::setLanguage(self::language($kirby));
    }
}
