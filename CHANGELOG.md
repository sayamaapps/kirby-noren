# Changelog

All notable changes to this project are documented here. Versions follow
[Semantic Versioning](https://semver.org) with these project rules:

- **A change to the content field contract** (the German field names and the
  row rules the Panel writes) **or to the default output** (what the snippets
  and schema.org show without configuration) is a **major** version: a site
  would show other hours after a routine update.

## [Unreleased]

No breaking changes in 1.x. The items under "Planned for 2.0" come with the next major.

### Changed

- Options are read as `sayamaapps.noren.<name>`. The 1.x names `noren.<name>` keep working and are read without a warning. If both are set, the new one wins.
- Classes live in the `Sayamaapps\Noren` namespace, loaded through a class map. The `Noren\` names stay as class aliases in `legacy.php`.
- Classes are no longer `final`.
- Warnings about broken options name the key as the site wrote it, also for `holidays`.
- `Options::OPTION_PREFIX` is new. `Options::PREFIX` keeps its 1.x value.
- `Locations::status()`, `table()`, `compact()` and `schema()` take an optional `$kirby` argument. `Holidays::config()` takes an optional `$optionName`. Without them, the behaviour is the same as before.
- Tests run on PHPUnit. CI runs on PHP 8.2 to 8.5 with the lowest and the locked dependencies.
- New public methods only: `Options::setKey()`, `Locations::pageAreas()`, `Locations::pageHasHours()`, `Locations::siteAreas()`, `Locations::siteSchedule()`.
- `Grouping::rangeLabel([])` raises no PHP warning any more. The return value is unchanged.

### Planned for 2.0

- Runtime deprecation notices for the 1.x option names and `noren_option()`.
- Kirby's `InvalidArgumentException` for invalid options in debug mode.
- Removal of the 1.x option names, `noren_option()`, the `Noren\` aliases and `Log::forget()`.

## [1.3.1] — 2026-10-26

### Fixed

- **schema.org hours past midnight.** A block that starts at 00:00 and runs past
  midnight lost its end: two rows that touch at midnight (00:00–18:00 and
  18:00–02:00) gave Monday "00:00" to "23:59" and nothing for the two hours
  after. The schema now carries the overhang on the next weekday: Monday
  00:00–23:59 and Tuesday 00:00–02:00. Sites with such a row get one more
  `OpeningHoursSpecification` entry; the status and the table already showed the
  right hours.

## [1.3.0] — 2026-10-26

### Added

- **`norenUnits($now, $merge)`**: `$now` is the moment `noren.mergeAreas`
  compares the areas at (default now); `$merge = false` returns one unit per
  area even with `noren.mergeAreas` on, for consumers that need every area's
  own data, such as schema.org or llms.txt.

### Fixed

- **Date previews with `noren.mergeAreas`.** The status snippet grouped the
  areas by the real time, also when `$now` named another date. An area with a
  closure beyond the lead time stayed in the group and showed the group's
  hours: `only => 'cafe'` on the café's closure day read "open". The snippet
  now groups at its `$now`.

## [1.2.2] — 2026-10-21

### Fixed

- **An occasion that already says "closed"** stands alone. A one-day closure
  "Geschlossene Gesellschaft" read "Geschlossene Gesellschaft: geschlossen" in
  the status and "23.10. Geschlossene Gesellschaft: geschlossen" in the table
  notes; now "Geschlossene Gesellschaft" and "23.10. Geschlossene Gesellschaft".
  The check uses the closed word of the current language, case-insensitive.
  Occasions like "Silvester" keep ": geschlossen".

### Removed

- **`DATENSCHUTZ.md` and `PRIVACY.md`.** Noren processes no visitor data, so it has no place in a site's privacy policy. The README says so under "Privacy".

## [1.2.1] — 2026-10-20

### Fixed

- **`noren.timezone`** accepts legacy names (`Europe/Kiev`, `US/Eastern`, and
  a few more `US/*` and `Asia/*` names) also on servers whose tzdata lacks
  them. They resolve to the current zone with the same rules (`Europe/Kyiv`,
  `America/New_York`), so the hours do not change. Before, such a site fell
  back to `Europe/Berlin` with a logged error. Names the server's tzdata knows
  are kept as before.

## [1.2.0] — 2026-10-19

### Added

- **`noren.mergeAreas`** (default `false`, opt-in). Areas of one location with
  identical status and table become one unit: "Hofladen und Hofcafé", three
  areas "Hofladen, Hofcafé und Werkstatt" (English: "and"). Equal means equal
  output, so a closure for one area splits the group again. Units have an
  `areas` list when the option is on, a merged unit's `area` is its first key.
  `norenSchedule()`, `norenSchema()` and the schema.org data stay per area. With
  the default, output is unchanged.

## [1.1.0] — 2026-10-17

### Fixed

- **Special hours spanning several days** show both dates in the table and
  compact notes ("6.7. bis 10.7.2026 Sommerwoche: 10:00–14:00 Uhr"). Before,
  the note named only the first day, so a running week of short hours read
  like a single past date. New translation key `note.dateRange`.
- **Continuous opening across days.** The status said "until 48:00" for three
  full days in a row (and nonsense for a plan open around the clock). It now
  names the day the run ends: "noch bis Mittwoch um 24:00 Uhr", "noch bis morgen
  um 18:00 Uhr". A plan whose run still continues after today and the following
  seven days says "rund um die Uhr geöffnet" and `until` is `null`. The day is
  named whenever the run ends later than 06:00 of the next day, whatever
  `overnightUntil` is set to. schema.org gives full days as `00:00`–`23:59`,
  because `00:00`–`00:00` means closed in Google's LocalBusiness notation
  (weekly plan and special hours).

### Changed

- `00:00` to `00:00` is read as a full day `[0, 1440]`, in a weekly row and in
  the `oeffnet`/`schliesst` fields of a closure (special hours all day). Before,
  it was ignored as equal times, so such a closure row counted as closed. The
  Panel help says so now.

### Added

- Translation keys `detail.untilTomorrow`, `detail.untilTomorrowOccasion`,
  `detail.untilWeekday`, `detail.untilWeekdayOccasion`, `detail.untilDate`,
  `detail.untilDateOccasion`, `detail.alwaysOpen` and
  `detail.alwaysOpenOccasion`.

## [1.0.0] — 2026-10-11

First release as a Composer package.

### Added

- Package `sayamaapps/kirby-noren`, Kirby plugin id `sayamaapps/noren`, PHP
  namespace `Noren\`, installed to `site/plugins/noren/`.
- **English names** for code, options, site and page methods, snippets, CSS
  classes and blueprint keys. The German content field names are unchanged.
- **Several blocks per day** (lunch break, split shifts) and a **weekly plan**
  with one schedule per area and per location.
- **Areas and locations**: one location with several areas (`bereiche`), or
  several location pages (`noren.template`, `noren.locations`), with
  `noren.fields` to rename page fields and `zeitenschluessel` to point a page
  at an area.
- **Closures and special days**: multi-day closures with a "back on" date,
  single closed days with an occasion (`anlass`), special hours with
  `oeffnet`/`schliesst`, notes (`hinweis`) and area scope (`bereich`).
- **German public holidays for all 16 states**, calculated (Easter included),
  with the flags `assumption`, `augsburg`, `corpusChristi` and `sundays`, extra
  days (`MM-DD` or `Y-m-d`), and the classes `Holidays` and the option
  `noren.holidays`. Holidays are off unless the option is set.
- **Status** per unit: `open`, `text`, `detail`, `state` (`open`, `closed`,
  `holiday`, `closure`), `until`, `next`, `occasion`, `holiday`.
- **Eve notice** for public holidays, opt-in with `noren.holidayEve`.
- **Table** view model and `noren/table` snippet, with a holidays row, notes
  for the next holiday and for closures (options `holidayRow`, `holidayNote`,
  `holidayNoteDays`, `leadDays`).
- **Compact** view model and `noren/compact` snippet, grouped (`grouped`) or
  only neighbouring days (`consecutive`), option `noren.compact`.
- **schema.org** arrays: `openingHoursSpecification` and
  `specialOpeningHoursSpecification` (`norenSchema()`), plain PHP, no JSON
  encoding.
- **Snippet `noren/status`** (one line per unit, `$only`, `$link`).
- **Site methods** `norenLocations()`, `norenLocation()`, `norenAreas()`,
  `norenSchedule()`, `norenUnits()`, `norenStatus()`, `norenTable()`,
  `norenCompact()`, `norenSchema()`, `norenHolidays()`; **page methods**
  `norenSchedule()`, `norenAreas()`, `norenHasHours()`, `norenStatus()`,
  `norenTable()`, `norenCompact()`, `norenSchema()`; helper `noren_option()`.
- **Options**: `active`, `template`, `locations`, `fields`, `timezone`,
  `holidays`, `holidayEve`, `holidayRow`, `holidayNote`, `holidayNoteDays`,
  `leadDays`, `lookahead`, `overnightUntil`, `closureLabel`, `compact`,
  `status`, `language`. Defaults are listed in the README.
- **Blueprints** for `extends:`: `noren/pages/location`, `noren/tabs/areas`,
  `noren/tabs/hours`, `noren/fields/*` (structures and info texts), with every
  label as a translation key.
- **English and German** texts, also registered as Kirby translations
  (`noren.*`). `noren.language` selects the language; otherwise the site's
  language, then `locale`, then English.
- Standalone PHP test suite (`composer test`), PHP 8.2–8.4.
- **Debug mode**: with Kirby's `debug` option on, a broken `noren.holidays`,
  `noren.timezone` or `noren.overnightUntil` throws instead of being logged.
  Boolean options accept only `true` and `false`. Every log message is written
  once per process.
- **Page cache**: the status, table, compact and schema output switch Kirby's
  page cache off for their response, because they depend on the time.
- **Robust content**: a hours or closures field that is not structure data is
  logged and treated as empty, and a closure with a date that is not `Y-m-d`, or
  whose `von` lies after `bis`, is skipped and logged. A plan without hours gives
  `null` from the status, table, compact and schema methods.
- **Berlin 2028**: 17 June (75th anniversary of the 1953 uprising) is a one-off
  holiday in 2028, as passed by the Abgeordnetenhaus in July 2024.
- Time zones: any zone PHP accepts is valid (`Etc/UTC`, `GMT`, `Europe/Kiev`).

### Behaviour

The following behaviour is deliberate and visible on the site:

1. **Overnight blocks are read.** Friday 18:00 to 2:00 is a block on Friday that
   is open until 2:00 on Saturday. The default `overnightUntil` (`06:00`) keeps an
   end such as 08:00 as a typo. `noren.overnightUntil` = `false` ignores rows that
   end after midnight. A row ending at 00:00 (Friday 18:00 to 00:00) is read as
   "until midnight" in every case, also with `false`.
2. **A business without areas gets a status line.**
3. **The status label uses the area's short name** (`kurzname`), as its Panel
   help says.
4. **A holiday with special hours is not listed as closed** in the table note and
   in schema.org. Special hours beat holidays everywhere.
5. **Holidays for all 16 states.** An unknown state in `noren.holidays` is logged
   and holidays stay off.
6. **The texts follow the language of the site.** `noren.language`, else the
   current or default language of a multi-language site, else Kirby's `locale`,
   else English. A German single-language site without a `locale` must set
   `noren.language` => `de`.
7. **Touching and overlapping blocks are one opening.** `08:00–12:00` and
   `12:00–18:00` on the same day show as `08:00 – 18:00`, and the status says
   "until 18:00".

### Content

- The German field names (`bereiche`, `zeiten`, `schliessungen`, `zeitenschluessel`,
  `kurzname`), the row names (`bereich`, `tag`, `von1`/`bis1`, `von2`/`bis2`, legacy
  `vm_`/`nm_`, `hinweis`, `nm_hinweis`) and the closure names (`von`, `bis`,
  `bereich`, `anlass`, `hinweis`, `oeffnet`, `schliesst`) are the contract of
  [README](README.md#content-field-contract).
- The default template name is `betrieb`.
- The legacy holidays array keys `land`, `mariaeHimmelfahrt` and `friedensfest` are
  accepted by `noren.holidays`; without `land` they mean Bavaria.

### [privacy text]

- `DATENSCHUTZ.md` (German text block for a site's privacy policy) and
  `PRIVACY.md` (English summary) are new. They state what the plugin does not do:
  no cookies, no browser storage, no requests to third parties, no visitor data.
  The status is computed on the server for each page request, holidays are
  calculated locally, and the schema.org output contains only public business
  data.
