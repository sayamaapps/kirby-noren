# Changelog

All notable changes to this project are documented here. Versions follow
[Semantic Versioning](https://semver.org) with these project rules:

- **A change to the content field contract** (the German field names and the
  row rules the Panel writes) **or to the default output** (what the snippets
  and schema.org show without configuration) is a **major** version: a site
  would show other hours after a routine update.

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

First release as a Composer package. Before 1.0.0 the hours logic lived as a
copy in each site: `site/plugins/betrieb` (kirby-starter, with the snippets
`zeiten-tabelle`, `statusband` and the hours part of `strukturdaten`), and
variants in a practice site (`Praxis\Sprechzeiten`), a restaurant site
(`Karte::zeitgruppen()`) and a bike-shop site (`zeiten-kurz`). The changes below are
measured against kirby-starter's `betrieb` copy.

### Changed — renamed to English (breaking)

Code, options, site and page methods, snippets, CSS classes and the blueprint
keys are English now. There are no fallbacks for the old names: a site switching
from the copy updates its calls and snippets once, using the table below.

| Before (`betrieb` copy) | Now (Noren) |
|---|---|
| `Betrieb\Oeffnungszeiten` | `Noren\Schedule` |
| `::ausFeld($zeiten, $schliessungen, $bereich, $label, $feiertag)` | `::fromFields($hours, $closures, $area, $closureLabel, $holiday, $settings)` |
| `::TAGE` | `::DAYS_DE` (display names: `I18n::day()`) |
| `::formatiere()` | `::formatTime()` |
| `bloecke()`, `hinweis()`, `gepflegt()` | `blocks()`, `note()`, `maintained()` |
| `schliessungAm()`, `sonderzeitAm()`, `bloeckeAm()` | `closureOn()`, `specialOn()`, `blocksOn()` |
| `bezeichnung()`, `schliessungLabel()` | `label()`, `closureLabel()` |
| `feiertagAm()` | `holidayOn()` |
| `kommendeFeiertage()` → `datum`, `name` | `upcomingHolidays()` → `date`, `name` |
| `kommendeSchliessungen()` → `von`, `bis`, `hinweis`, `anlass`, `zeiten` | `upcomingClosures()` → `from`, `to`, `note`, `occasion`, `hours` (+ `area`) |
| `status()` → `offen`, `text`, `detail` | `status()` → `open`, `text`, `detail` (+ `state`, `until`, `next`, `occasion`, `holiday`) |
| constructor `['bloecke' => …, 'hinweis' => …]` | `['blocks' => …, 'note' => …]` |
| `Betrieb\Feiertage::ostersonntag()` | `Noren\Holidays::easterSunday()` |
| `Feiertage::bayern($jahr, $mariae, $friedensfest)` | `Holidays::forYear($jahr, Holidays::config(['state' => 'BY', 'assumption' => …, 'augsburg' => …]))` |
| `Feiertage::pruefer(['land' => 'bayern'])` | `Holidays::checker(Holidays::config('BY'))` |
| option `betrieb.feiertage` | `noren.holidays` |
| option `betrieb.schliessungLabel` | `noren.closureLabel` |
| `$site->betrieb()` | `$site->norenLocation()` |
| `$site->bereiche()` (Structure) | `$site->norenAreas()` (array `key`, `name`, `short`) |
| `$site->oeffnungszeiten($bereich)` | `$site->norenSchedule($area)` |
| `$site->bereicheMitZeiten()` → `schluessel`, `name`, `plan` | `$site->norenUnits()` → `id`, `area`, `name`, `short`, `label`, `location`, `schedule` |
| `$page->oeffnungszeiten()`, `$page->hatZeiten()` | `$page->norenSchedule()`, `$page->norenHasHours()` |
| snippet `zeiten-tabelle` (`$bereich`, `$plan`) | `noren/table` (`$area`, `$schedule`) |
| snippet `statusband` (`$nur`, `$mehrLink`) | `noren/status` (`$only`, `$link`) |
| bike shop `zeiten-kurz` (`$plan`) | `noren/compact` (`$schedule`, `$mode`, `$title`) |
| restaurant `Karte::zeitgruppen($plan)` → `tage`, `zeiten` | `Grouping::consecutive($schedule)` → `label`, `hours` |
| `strukturdaten.php` week and special closures | `$site->norenSchema()` |
| CSS `zeiten-raster`, `zeile`, `zeile-tag`, `zeile-zeit`, `zeile-zu`, `zeile-hinweis`, `zeile--feiertage`, `zeiten-zusatz`, `schliessung`, `data-heute="ja"` | `noren-table`, `noren-row`, `noren-day`, `noren-hours`, `noren-closed`, `noren-note`, `noren-row--holidays`, `noren-extra`, `noren-closure`, `data-today="true"` |
| CSS `band-status`, `band-bereich`, `data-offen="ja"` | `noren-status-line`, `noren-status-label`, `data-open="true"` |
| blueprint tabs `Bereiche` and `Zeiten` (in the site's `betrieb.yml`) | `noren/pages/location` with the tabs `noren/tabs/areas` and `noren/tabs/hours` (same field names) |

Parts of the `betrieb` copy that are out of scope stay with the site:
`telefonUri`, `betriebsangabe` and the address fields.

### Added

- Package `sayamaapps/kirby-noren`, Kirby plugin id `sayamaapps/noren`, PHP
  namespace `Noren\`, installed to `site/plugins/noren/`.
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

### Changed behaviour

These seven changes against the `betrieb` copy are deliberate. Each one is
visible on the site after the switch.

1. **Overnight blocks are read.** Before, a row whose end lay before its start
   was ignored. Now Friday 18:00 to 2:00 is a block on Friday that is open until
   2:00 on Saturday. The default `overnightUntil` (`06:00`) keeps an end such as
   08:00 as a typo. `noren.overnightUntil` = `false` ignores rows that end after
   midnight, as the copy did. A row ending at 00:00 (Friday 18:00 to 00:00) is
   read as "until midnight" in every case, also with `false`; the copy ignored it.
2. **A business without areas gets a status line.** The copy's
   `bereicheMitZeiten()` returned nothing when no areas were defined, so the
   status band stayed empty.
3. **The status label uses the area's short name** (`kurzname`), as its Panel
   help always promised.
4. **A holiday with special hours is no longer listed as closed** in the table
   note and in schema.org. Special hours beat holidays everywhere.
5. **Holidays for all 16 states**, not only Bavaria. An unknown state in
   `noren.holidays` is logged and holidays stay off; before it silently meant
   "no holidays".
6. **The texts follow the language of the site.** `noren.language`, else the
   current or default language of a multi-language site, else Kirby's `locale`,
   else English. The copy always printed German. A German single-language site
   without a `locale` must set `noren.language` => `de` when it switches.
7. **Touching and overlapping blocks are one opening.** `08:00–12:00` and
   `12:00–18:00` on the same day show as `08:00 – 18:00`, and the status says
   "until 18:00". The copy listed two rows and said "until 12:00".

### Unchanged (data continuity)

- **No content has to change.** The German field names (`bereiche`, `zeiten`,
  `schliessungen`, `zeitenschluessel`, `kurzname`), the row names (`bereich`,
  `tag`, `von1`/`bis1`, `von2`/`bis2`, legacy `vm_`/`nm_`, `hinweis`,
  `nm_hinweis`) and the closure names (`von`, `bis`, `bereich`, `anlass`,
  `hinweis`, `oeffnet`, `schliesst`) are the contract of
  [README](README.md#content-field-contract).
- The default template `betrieb` and the blueprint file `betrieb.yml` are
  unchanged.
- The old option values move verbatim: `betrieb.feiertage` is read as
  `noren.holidays` (its legacy keys `land`, `mariaeHimmelfahrt`,
  `friedensfest` are accepted; without `land` the legacy keys mean Bavaria),
  `betrieb.schliessungLabel` becomes `noren.closureLabel`.
- Hours, closures and special days of existing content produce the same
  status, table and notes as before, apart from the five changes above.
- Bavarian holidays are calculated with the same flags as before. Holiday names
  are the same German names.
- Both plugins can be installed side by side during the switch.

### [privacy text]

- `DATENSCHUTZ.md` (German text block for a site's privacy policy) and
  `PRIVACY.md` (English summary) are new. They state what the plugin does not do:
  no cookies, no browser storage, no requests to third parties, no visitor data.
  The status is computed on the server for each page request, holidays are
  calculated locally, and the schema.org output contains only public business
  data.
