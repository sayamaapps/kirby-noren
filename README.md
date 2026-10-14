# Noren

Opening hours for [Kirby CMS](https://getkirby.com) 5: a weekly plan with several blocks per day, areas and locations, closures and special hours, German public holidays for all 16 states, and an "open now" status. No styling.

*Noren* (暖簾) is the cloth curtain hung in the doorway of a Japanese shop or restaurant while it is open, and taken in when it closes. The plugin does the same job on the website: it answers "are you open right now, and if not, when again?"

- **Several blocks per day.** A lunch break is one day with two blocks. A block that runs past midnight (Friday 18:00 to 2:00) stays on its start day.
- **Areas and locations.** One business with separate hours for the shop and the café, or several sites with a plan each. Both can be combined.
- **Closures and special hours.** Multi-day closures ("Betriebsferien") with a "back on" date, single days such as New Year's Eve, and short opening hours on a day that is otherwise closed.
- **German public holidays for all 16 states.** Calculated locally from the rules of each state, with switches for the regional days and extra days of your own. Easter is computed, not listed.
- **An "open now" status** per area, computed on the server for each request in the business's time zone.
- **Table, compact list and schema.org.** Snippets for the status line, the full week and a short line, plus plain arrays for your own markup and JSON-LD.
- **No styling.** Only `noren-*` classes. Style them yourself.
- **English and German.** The texts are also registered as Kirby translations (`noren.*`).
- **No cookies, no browser storage, no requests to third parties.** See [Privacy](#privacy).

## Requirements

- PHP 8.2–8.4
- Kirby 5

## Installation

```sh
composer require sayamaapps/kirby-noren
```

Composer installs the plugin into `site/plugins/noren/` (via `getkirby/composer-installer`, which ships with `getkirby/cms`). Add `/site/plugins/noren/` to `.gitignore` and make sure your deploy ships it, just as it ships `kirby/` and `vendor/`.

Until the package is on Packagist, add the repository first:

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/simsimmal/kirby-noren" }]
```

Manual installation (download into `site/plugins/noren/`) also works.

## Quick start

1. Switch it on in `site/config/config.php`:

   ```php
   return [
       'noren.active'   => true,
       'noren.language' => 'de',   // German texts, see the note below
       'noren.holidays' => 'BY',   // Bavarian public holidays; optional
   ];
   ```

   > **Language:** Noren's texts are English unless it learns otherwise. It follows `noren.language`, then the current language of a multi-language site, then Kirby's `locale` option. **A German single-language site without a `locale` must set `'noren.language' => 'de'`**, or its status line reads "Open now" instead of "Jetzt geöffnet".

2. Give the location pages a blueprint, for example `site/blueprints/pages/betrieb.yml`:

   ```yaml
   title: noren.panel.page.title
   extends: noren/pages/location
   ```

   The `title` line is needed: Kirby keeps its own fallback title for a page blueprint that only extends another one, so the title of `noren/pages/location` does not reach the page on its own (Kirby 5.6.1).

   Location pages use the template `betrieb` by default (see [`template`](#configuration)). On a multi-language site their content files are `betrieb.de.txt` and `betrieb.en.txt`.

3. Place the snippets in your templates:

   ```php
   <?php snippet('noren/status') ?>    <!-- "open now" line, one per area -->
   <?php snippet('noren/table') ?>     <!-- the full week, closed days included -->
   <?php snippet('noren/compact') ?>   <!-- short lines, days with equal hours grouped -->
   ```

The snippets print unstyled markup. Nothing is printed while `noren.active` is off or while no page has hours.

## Concepts

- **Location**: a page with the location template (default `betrieb`). A business has one location, or several (one page each).
- **Area**: a part of a location with its own hours, such as `laden` (shop) and `cafe`. Areas are defined on the location page (`bereiche`).
- **Unit**: one area of a location, or the location itself when it has no areas. The status snippet shows one line per unit.
- **Schedule**: the plan of one unit: weekly blocks, closures, special hours. Internally a `Noren\Schedule` object.

## Configuration

Every option is read as `noren.<name>`, either flat (`'noren.active' => true`) or nested (`'noren' => ['active' => true]`). `null` counts as "not set".

Boolean options take a real `true` or `false`. Any other value (the string `'false'`, for example) is logged and the default is used.

A broken value (an unknown time zone, an unknown holiday state, an `overnightUntil` that is not `HH:MM`) is logged once per process and the default is used. With Kirby's `debug` option set to `true` it throws an exception instead, so the error shows on the page while you develop.

| Option | Default | Meaning |
|---|---|---|
| `active` | `false` | Master switch. Only `true` switches Noren on. Off: the site and page methods return `null` or `[]`, and the snippets print nothing. |
| `template` | `'betrieb'` | Template name of the location pages: a string or a list of strings. |
| `locations` | `null` | A Closure or a list of page ids. Overrides `template`. |
| `fields` | see [Content field contract](#content-field-contract) | Page field names, as a map: `areas` (`bereiche`), `hours` (`zeiten`), `closures` (`schliessungen`), `areaRef` (`zeitenschluessel`), `short` (`kurzname`). Give only the keys you change. |
| `timezone` | `'Europe/Berlin'` | Time zone of all date logic: any zone PHP accepts, such as `'Etc/UTC'`. An invalid value is logged and the default is used. |
| `holidays` | `false` | Public holidays. Off by default. See [Public holidays](#public-holidays). |
| `holidayEve` | `false` | Adds a notice to the status when tomorrow is a public holiday. |
| `holidayRow` | `true` | The "Public holidays: closed" row in the table. Shown only when holidays are on. |
| `holidayNote` | `true` | The note on the next public holiday under the table. Shown only when holidays are on. |
| `holidayNoteDays` | `14` | How far ahead that note looks, counted like `leadDays`: a holiday 14 days ahead is shown, one 15 days ahead is not. |
| `leadDays` | `14` | Closures and special days appear on the site this many days before they start. schema.org always gets all of them. |
| `lookahead` | `90` | How many days the status searches for the next opening. |
| `overnightUntil` | `'06:00'` | Latest end, as `'HH:MM'`, of a block that runs past midnight. `false` switches overnight blocks off, and rows that end after midnight are then ignored. A row ending at `00:00` still counts as "until midnight". See [Overnight blocks](#overnight-blocks). |
| `closureLabel` | translated: `Betriebsferien` (de) / `Closed for holidays` (en) | Name of a multi-day closure, for example `'Praxisurlaub'` or `'Winterpause'`. |
| `compact` | `'grouped'` | Default mode of `noren/compact`: `grouped` (any days with equal hours) or `consecutive` (only neighbouring days). |
| `status` | `true` | `false` hides the `noren/status` snippet. |
| `language` | site language | `en` or `de`. Otherwise: the current language of a multi-language site, its default language, the first two letters of Kirby's `locale` option, and English. **A German site without multi-language setup and without a `locale` must set `'noren.language' => 'de'`**, otherwise its texts are English. |

A complete example with the options most sites change:

```php
// site/config/config.php
return [
    'noren.active'         => true,
    'noren.timezone'       => 'Europe/Berlin',
    'noren.holidays'       => [
        'state'          => 'BY',
        'augsburg'       => true,
        'extra'          => ['06-24' => 'Stadtfest', '2027-07-02' => 'Dorffest'],
    ],
    'noren.holidayEve'     => true,
    'noren.overnightUntil' => '02:00',
    'noren.compact'        => 'consecutive',
];
```

## Content field contract

The German field names are a compatibility contract: existing content from earlier sites works unchanged. The page-level names can be changed with `noren.fields`; the row-level names cannot.

| Level | Field | Option key | Default name | Content |
|---|---|---|---|---|
| page | areas | `fields.areas` | `bereiche` | structure: `schluessel` (key, required), `name`, `kurzname` |
| page | hours | `fields.hours` | `zeiten` | structure, rows below |
| page | closures | `fields.closures` | `schliessungen` | structure, rows below (practice: `urlaub`) |
| page | area reference | `fields.areaRef` | `zeitenschluessel` | text on a non-location page naming an area |
| page | short name | `fields.short` | `kurzname` | text on a location page (the status label) |
| hours row | `bereich` | | | area key; empty = no areas |
| hours row | `tag` | | | `Montag` … `Sonntag`; also English names and `1`–`7` (Monday = 1) |
| hours row | `von1`, `bis1`, `von2`, `bis2` | | | `HH:MM` or `HH:MM:SS` |
| hours row | `vm_von`, `vm_bis`, `nm_von`, `nm_bis` | | | legacy names: `vm_` stands for the first pair (`von1`/`bis1`), `nm_` for the second (`von2`/`bis2`), read when that pair is empty |
| hours row | `hinweis` (legacy `nm_hinweis`) | | | note for the weekday |
| closure row | `von`, `bis` | | | `Y-m-d`, both required, inclusive. A timestamp is cut to its date. Any other date, or `von` after `bis`, is skipped and logged. |
| closure row | `bereich` | | | only this area; empty = all areas |
| closure row | `anlass` | | | occasion, e.g. "Silvester" |
| closure row | `hinweis` | | | replaces the automatic "back on …" |
| closure row | `oeffnet`, `schliesst` | | | short hours instead of closed (both required) |

Reading rules:

- Rows for the same weekday are added together, never overwritten. Blocks are sorted by their start.
- A block needs **both** times and an end after the start. A half-filled pair is ignored. No information is better than wrong information.
- Blocks that touch or overlap, such as `08:00–12:00` and `12:00–18:00` on the same day, are one opening: `08:00 – 18:00`.
- A day without rows is closed. A closed day needs no row.
- Day names are accepted in German, in English (any case) or as ISO numbers `1`–`7`. Anything else is ignored.
- Legacy `vm_`/`nm_` columns are read only when the matching pair is empty, so a row with the old names works unchanged.
- With an area (`norenSchedule('laden')`), only rows whose `bereich` equals `laden` count. Without an area, all rows count, whatever their `bereich`. A business without areas needs no area; a business with areas should always ask for one.

## Locations and areas

Two patterns cover the common cases.

### One location, several areas

One page holds all hours. The areas are listed in the `bereiche` structure, and each hours row names its area. This is the shop-and-café shape: a shop and a café on one page, the café closed on Mondays.

The Panel writes the structure as YAML, which looks like this:

```yaml
bereiche:
  - schluessel: laden
    name: Hofladen
    kurzname: Laden
  - schluessel: cafe
    name: Hofcafé
```

`$site->norenUnits()` then returns one unit per area that has hours (`kontakt#laden`, `kontakt#cafe`). The status line shows the short names as labels. An area without any hours is left out, because "closed" for a missing plan would send people to a door that may be open.

Closures can name an area in their `bereich` field. A closure with an empty area applies to every area.

### Several locations, one plan each

Each location is its own page with its own hours. A medical-practice setup uses the template `standort` for every site:

```php
// site/config/config.php
return [
    'noren.active'   => true,
    'noren.template' => 'standort',
    'noren.fields'   => ['closures' => 'urlaub'],   // this site calls its closure field "urlaub"
];
```

Or select the pages yourself. The closure runs on each call and may return a `Pages` collection, a single page, an array of pages or an array of page ids:

```php
'noren.locations' => fn () => site()->children()->listed()->filterBy('intendedTemplate', 'standort'),
```

A list of page ids works as well. Ids that do not resolve are skipped:

```php
'noren.locations' => ['kontakt', 'standorte/au'],
```

Locations are listed in index order, or in the order your closure or list returns them. Every location with hours gets one unit (one per area with hours, when it has areas). The label is the short name alone when there is one location. With several locations it is the location's short name, joined by " · " to the area's short name when the location has areas. The site methods (`norenSchedule()`, `norenStatus()`, `norenTable()` and so on) use the **first** location.

### Pages that point to an area

A page that is not a location can name the area whose hours apply to it, in the field `zeitenschluessel` (`fields.areaRef`). A workshop page with `zeitenschluessel: cafe` then shows the café's hours. The page methods read it, see [Own markup](#own-markup).

## Snippets

All three snippets are unstyled, use only `noren-*` classes and escape every output. Each one prints nothing while `noren.active` is off, and nothing when the schedule has no hours at all. None of them prints "closed" for a missing plan.

### `noren/status`

The "open now" line, one per unit. Variables:

| Variable | Default | Meaning |
|---|---|---|
| `$units` | `site()->norenUnits()` | The units to show. |
| `$only` | none | Keeps only the units whose `id`, `area` or `location->id()` equals this string. |
| `$link` | none | URL of a page with all hours. Adds a "more" link. |
| `$now` | now | `DateTimeImmutable`, for tests and previews. |

```html
<div class="noren-status">
  <p class="noren-status-line" data-open="true" data-state="open"><span class="noren-status-label">Laden</span> <b class="noren-status-text">Jetzt geöffnet</b> <span class="noren-status-detail">noch bis 18:00 Uhr</span></p>
  <p class="noren-status-more"><a href="/kontakt">Alle Öffnungszeiten</a></p>
</div>
```

- `data-open` is `"true"` or `"false"`. `data-state` is `open`, `closed`, `holiday` or `closure`.
- The label span appears only when two or more units are shown.
- The detail span appears only when there is a detail.
- The "more" paragraph appears only with `$link`.
- Nothing is printed when `noren.status` is `false` or when no unit is left.

### `noren/table`

The full week, with closed days printed (a missing row would not answer "open on Mondays?"). Variables:

| Variable | Default | Meaning |
|---|---|---|
| `$schedule` | `site()->norenSchedule($area ?? null)` | The schedule to show. |
| `$area` | none | Area key, used when `$schedule` is not given. |
| `$now` | now | For tests and previews. |

```html
<div class="noren-table">
  <div class="noren-row" data-today="true"><div class="noren-day">Montag</div><div class="noren-hours">13:00 – 18:00<br></div></div>
  <div class="noren-row" data-today="false"><div class="noren-day">Samstag</div><div class="noren-hours">8:00 – 12:00<br>14:00 – 18:00<br><span class="noren-note">warme Küche bis 14 Uhr</span></div></div>
  <div class="noren-row" data-today="false"><div class="noren-day">Sonntag</div><div class="noren-hours"><span class="noren-closed">geschlossen</span></div></div>
  <div class="noren-row noren-row--holidays" data-today="false"><div class="noren-day">Feiertage</div><div class="noren-hours"><span class="noren-closed">geschlossen</span></div></div>
</div>
<p class="noren-extra" data-holiday>3.10. Tag der Deutschen Einheit: geschlossen</p>
<p class="noren-extra" data-special>24.12. Heiligabend: 8:30–12:00 Uhr · Hinweis</p>
<p class="noren-closure"><strong>Betriebsferien 2.8. bis 15.8.2027</strong><br>Hinweis</p>
```

- `data-today="true"` marks the weekday of today. On a closing holiday the "today" marker moves to the holidays row, because the weekday row would show the hours of a day that is closed.
- The holidays row appears only when holidays are on and `holidayRow` is not `false`.
- `data-holiday` marks the next holiday note; `data-special` marks special hours and single closed days, with the note appended after " · "; `noren-closure` marks range closures, with the note after a line break.
- Nothing is printed for a schedule without hours.

### `noren/compact`

Short lines for a sidebar or a footer. Days with equal hours are grouped. Variables:

| Variable | Default | Meaning |
|---|---|---|
| `$schedule` | `site()->norenSchedule($area ?? null)` | The schedule to show. |
| `$area` | none | Area key, used when `$schedule` is not given. |
| `$mode` | `noren.compact` | `grouped` or `consecutive`. |
| `$title` | none | Heading above the list. |
| `$now` | now | For tests and previews. |

```html
<div class="noren-compact">
  <p class="noren-compact-title">Werkstatt-Zeiten</p>
  <ul class="noren-compact-list">
    <li><span class="noren-compact-days">Mi, Fr</span> <span class="noren-compact-hours">10–18 Uhr</span> <span class="noren-compact-note">nach Vereinbarung</span></li>
  </ul>
  <p class="noren-compact-closure">Betriebsferien 2.8. bis 15.8.2027 — Hinweis</p>
</div>
```

- The title paragraph appears only with `$title`; the note span only when a group has a note.
- The closure paragraphs come from the `notes` of the compact view model; at most one is shown by default, and " — " separates the note.
- `grouped` puts any days with equal hours together (`Mo–Mi, Fr`). `consecutive` groups only neighbouring days with equal hours, so a Tuesday and a Thursday with the same hours stay two lines.

### Overriding a snippet

Create a file with the same name in your site. Kirby takes the site's copy before the plugin's:

```
site/snippets/noren/status.php
site/snippets/noren/table.php
site/snippets/noren/compact.php
```

Data passed to a snippet wins over its defaults, for example `snippet('noren/compact', ['mode' => 'consecutive', 'title' => 'Werkstatt'])`.

## Own markup

Build your own markup from the site and page methods. Every method returns `null` (or `[]`) when Noren is off, and no page or schedule is cached, so each call reads the page again.

### Site methods

| Method | Returns |
|---|---|
| `norenLocations(): array` | All location pages (`Locations::pages()`). |
| `norenLocation(): ?Page` | The first location page. |
| `norenAreas(): array` | Areas of the first location: `['key', 'name', 'short']`. |
| `norenSchedule(?string $area = null): ?Schedule` | Schedule of the first location for an area. `null` when off or without a location. |
| `norenUnits(): array` | Every unit with hours (see below). |
| `norenStatus(?string $area = null): ?array` | Status array of the first location, or `null` without a plan. |
| `norenTable(?string $area = null): ?array` | Table view model. |
| `norenCompact(?string $area = null, ?string $mode = null): ?array` | Compact view model. |
| `norenSchema(?string $area = null): ?array` | `openingHoursSpecification` and `specialOpeningHoursSpecification`. |
| `norenHolidays(?int $year = null): array` | Public holidays of a year, `date => name`. `[]` when holidays are off. |

### Page methods

| Method | Returns |
|---|---|
| `norenSchedule(?string $area = null): ?Schedule` | The page's own schedule (a location), or the first location's schedule for `$area` or the page's `zeitenschluessel`. |
| `norenAreas(): array` | Areas of this location page, or of the first location. |
| `norenHasHours(?string $area = null): bool` | Whether the schedule has any hours. |
| `norenStatus(?string $area = null): ?array` | As the site method, for this page; `null` without a plan. |
| `norenTable(?string $area = null): ?array` | As the site method, for this page. |
| `norenCompact(?string $area = null, ?string $mode = null): ?array` | As the site method, for this page. |
| `norenSchema(?string $area = null): ?array` | As the site method, for this page. |

The helper `noren_option('name', $default)` reads a `noren.<name>` option from a snippet.

### Return shapes

Status (`norenStatus()`, `null` when there is no location or no weekly plan):

```php
$status = site()->norenStatus('laden');
// [
//   'open'     => true,                  // bool
//   'text'     => 'Jetzt geöffnet',      // headline, translated
//   'detail'   => 'noch bis 18:00 Uhr',  // '' when there is nothing to say
//   'state'    => 'open',                // 'open' | 'closed' | 'holiday' | 'closure'
//   'until'    => '18:00',               // end of the running block, else null
//   'next'     => null,                  // next opening 'Y-m-d\TH:i' while closed
//   'occasion' => null,                  // occasion of the special day or closure in effect
//   'holiday'  => null,                  // holiday today, or tomorrow with holidayEve
// ]
```

Table (`norenTable()`), shortened:

```php
[
  'days' => [   // always 7 entries, Monday first, from the weekly plan
    ['index' => 0, 'name' => 'Montag', 'short' => 'Mo', 'blocks' => [[780, 1080]],
     'hours' => ['13:00 – 18:00'], 'closed' => false, 'note' => null, 'today' => true],
    // …
    ['index' => 6, 'name' => 'Sonntag', 'short' => 'So', 'blocks' => [], 'hours' => [],
     'closed' => true, 'note' => null, 'today' => false],
  ],
  'holidays' => ['label' => 'Feiertage', 'text' => 'geschlossen', 'today' => false],   // null when off
  'notes' => [  // next holiday first (at most one), then closure notes
    ['type' => 'holiday', 'from' => '2026-10-03', 'to' => '2026-10-03',
     'text' => '3.10. Tag der Deutschen Einheit: geschlossen', 'note' => null],
  ],
]
```

Note types: `holiday`, `special` (a day with hours), `closedDay` (a single closed day), `closure` (a range).

Compact (`norenCompact()`):

```php
[
  'mode'   => 'grouped',
  'groups' => [
    ['days' => [2, 4], 'label' => 'Mi, Fr', 'blocks' => [[600, 1080]], 'hours' => '10–18 Uhr', 'note' => null],
  ],
  'notes'  => [],   // at most `closures` entries (default 1)
]
```

Schema (`norenSchema()`):

```php
[
  'openingHoursSpecification' => [
    ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '13:00', 'closes' => '18:00'],
    // one entry per block, Monday to Sunday; closed days have none
  ],
  'specialOpeningHoursSpecification' => [
    ['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-12-24', 'validThrough' => '2026-12-24', 'opens' => '08:30', 'closes' => '12:00'],
    ['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-12-25', 'validThrough' => '2026-12-25', 'opens' => '00:00', 'closes' => '00:00'],
    // all upcoming closures and special days, then the holidays of the next 365 days (closed = 00:00 to 00:00)
  ],
]
```

The arrays are plain PHP. Encode them yourself into a `<script type="application/ld+json">`, and use the flags below:

```php
<script type="application/ld+json">
<?= json_encode(site()->norenSchema(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
</script>
```

`JSON_HEX_TAG` writes `<` and `>` as `\u003C` and `\u003E`. Without it, a value that contains `</script>` would end the script element early. The flags are the ones kirby-starter uses.

Units (`norenUnits()`):

```php
[
  ['id' => 'kontakt#laden', 'location' => $page, 'area' => 'laden',
   'name' => 'Hofladen', 'short' => 'Laden', 'label' => 'Laden',
   'schedule' => $schedule],   // Noren\Schedule
  // …
]
```

Holidays (`norenHolidays(2026)`):

```php
['2026-01-01' => 'Neujahr', '2026-04-03' => 'Karfreitag', '2026-10-03' => 'Tag der Deutschen Einheit', /* … */]
```

### The Schedule object

`$page->norenSchedule()` and `$site->norenSchedule()` return a `Noren\Schedule`. Useful methods:

| Method | Returns |
|---|---|
| `blocks(int $weekday)` | Blocks of a weekday, `[[from, to], …]` in minutes since 0:00. Monday is 0. |
| `note(int $weekday)` | The note of a weekday, or `null`. |
| `week()` | All seven weekdays with `blocks` and `note`. |
| `maintained()` | Whether any block exists. |
| `status(?DateTimeImmutable $now = null)` | The status array. |
| `blocksOn(DateTimeImmutable $day)` | Special hours of a day, or the weekly plan. |
| `closureOn(DateTimeImmutable $day)`, `specialOn(…)`, `holidayOn(…)` | The closure, special day or holiday name of a day, or `null`. |
| `upcomingClosures(?DateTimeImmutable $from = null, ?int $leadDays = null)` | Closures and special days, ascending. |
| `upcomingHolidays(?DateTimeImmutable $from = null, int $days = 365)` | `[['date' => 'Y-m-d', 'name' => …], …]`. |
| `timezone()` | The `DateTimeZone` of the schedule. |

## Blueprints

Noren ships Panel blueprints for the hours and areas. A site uses them with `extends:`.

| Key | Content |
|---|---|
| `noren/pages/location` | The complete page blueprint: the tabs `bereiche` and `zeiten`. |
| `noren/tabs/areas` | The areas tab (`bereicheHinweis`, `bereiche`). |
| `noren/tabs/hours` | The hours tab (`zeitenHinweis`, `zeiten`, the separator, `schliessungenHinweis`, `schliessungen`). |
| `noren/fields/areas`, `areas-info` | The areas structure and its info text. |
| `noren/fields/hours`, `hours-info` | The hours structure and its info text. |
| `noren/fields/closures`, `closures-info` | The closures structure and its info text. |

A complete location blueprint:

```yaml
# site/blueprints/pages/betrieb.yml
title: noren.panel.page.title
extends: noren/pages/location
```

Only the hours tab, next to the site's own tabs:

```yaml
# site/blueprints/pages/standort.yml
title: Standort
tabs:
  zeiten:
    extends: noren/tabs/hours
```

Labels, help texts and option texts are translation keys (`noren.panel.*`, `noren.day.*`). A site can override a label after `extends:`.

**Area select query.** The area selects use `query: page.bereiche.toStructure`. If you rename the areas field with `noren.fields.areas`, these selects keep reading `bereiche`. Copy the tab into your own blueprint and change the query there.

## Public holidays

Holidays are off by default. Turn them on with `noren.holidays`, then they are calculated for every state, every year from 1995 on.

### Forms

```php
'noren.holidays' => false,                 // off (default)
'noren.holidays' => 'BY',                  // a state code; also 'DE-BY' or 'bayern'
'noren.holidays' => [
    'state'          => 'BY',
    'assumption'     => true,              // Mariä Himmelfahrt (BY, default true)
    'augsburg'       => false,             // Augsburger Friedensfest (BY, default false)
    'corpusChristi'  => false,             // Fronleichnam in SN and TH (default false)
    'sundays'        => true,              // Easter and Whit Sunday in BB (default true)
    'extra'          => [
        '06-24'      => 'Stadtfest',       // MM-DD: every year
        '2027-07-02' => 'Dorffest',        // Y-m-d: once
    ],
],
```

The legacy form of kirby-starter's `betrieb.feiertage` is accepted as well: `['land' => 'bayern', 'mariaeHimmelfahrt' => true, 'friedensfest' => false]`. Without `land`, an array with the legacy keys means Bavaria, as it did in the starter. An array with neither a state nor a legacy key is an error: add `'state' => 'BY'` (or the state of the business) to it.

- An unknown state (for example `'XX'`) or a value that is neither `false`, nor a string, nor an array with a state is an error. The error is logged (`Noren: …— public holidays are off.`) and holidays stay off. Nothing is guessed.
- An invalid `extra` entry (neither `MM-DD` nor `Y-m-d`, or an empty name) is skipped and logged.
- A flag that is not a real boolean (`'false'` as a string, for example) is logged and the default is used.
- An extra day on the date of a statutory holiday keeps the statutory name.

### The flags

| Flag | Default | Applies to | Meaning |
|---|---|---|---|
| `assumption` | `true` | Bavaria; always in Saarland | Mariä Himmelfahrt (15 Aug). Only in predominantly Catholic municipalities. Set `false` for a business in a Protestant municipality. |
| `augsburg` | `false` | Bavaria | Augsburger Friedensfest (8 Aug), only in the city of Augsburg. |
| `corpusChristi` | `false` | Saxony and Thuringia | Fronleichnam, only in some Catholic municipalities. Always in BW, BY, HE, NW, RP and SL. |
| `sundays` | `true` | Brandenburg | Ostersonntag and Pfingstsonntag, the statutory holidays of Brandenburg. Set `false` to drop them. |

### What is a holiday in each state

Nine days apply in every state: New Year's Day (1 Jan), Good Friday, Easter Monday, Labour Day (1 May), Ascension Day, Whit Monday, German Unity Day (3 Oct), Christmas Day and the Second Day of Christmas (25 and 26 Dec).

| State | Holidays in addition to the nine (default flags) | Holidays in 2026 |
|---|---|---|
| BW Baden-Württemberg | Epiphany (6 Jan), Corpus Christi, All Saints' Day (1 Nov) | 12 |
| BY Bayern | Epiphany, Corpus Christi, Mariä Himmelfahrt (`assumption`, default on), All Saints' Day; Augsburger Friedensfest with `augsburg` | 13 |
| BE Berlin | International Women's Day (8 Mar, since 2019); Liberation Day (8 May) in 2020 and 2025 only; 17 June in 2028 only (anniversary of the 1953 uprising) | 10 |
| BB Brandenburg | Easter Sunday and Whit Sunday (`sundays`, default on), Reformation Day (31 Oct) | 12 |
| HB Bremen | Reformation Day (since 2018) | 10 |
| HH Hamburg | Reformation Day (since 2018) | 10 |
| HE Hessen | Corpus Christi | 10 |
| MV Mecklenburg-Vorpommern | International Women's Day (since 2023), Reformation Day | 11 |
| NI Niedersachsen | Reformation Day (since 2018) | 10 |
| NW Nordrhein-Westfalen | Corpus Christi, All Saints' Day | 11 |
| RP Rheinland-Pfalz | Corpus Christi, All Saints' Day | 11 |
| SL Saarland | Corpus Christi, Mariä Himmelfahrt (always), All Saints' Day | 12 |
| SN Sachsen | Reformation Day, Buß- und Bettag (the Wednesday from 16 to 22 Nov); Corpus Christi with `corpusChristi` | 11 |
| ST Sachsen-Anhalt | Epiphany, Reformation Day | 11 |
| SH Schleswig-Holstein | Reformation Day (since 2018) | 10 |
| TH Thüringen | World Children's Day (20 Sep, since 2019), Reformation Day; Corpus Christi with `corpusChristi` | 11 |

Reformation Day was a holiday in every state in 2017 (500 years). Buß- und Bettag is a holiday only in Saxony. It was a nationwide holiday until 1994, and the holiday rules are modelled for 1995 onward; earlier years are not modelled.

### Easter and Whit Sunday

Easter Sunday and Whit Sunday are included only for Brandenburg, the one state where they are statutory holidays. Elsewhere they are not holidays, and the weekly plan applies on those Sundays as on any other. A Brandenburg site that opens on those Sundays and does not want the two days as holidays sets `sundays` to `false`.

Easter is computed with the anonymous Gregorian algorithm (Meeus/Jones/Butcher), so no date list has to be maintained. Ascension, Whit Sunday, Whit Monday and Corpus Christi hang on it.

### Names

Names come from the translations (`holiday.<id>`), so `I18n` language changes are respected: German "Tag der Deutschen Einheit", English "German Unity Day".

### Using holidays

- The status says "Public holiday today" when the business would be open that day, and it names the next open day in its detail.
- A holiday with special hours (for example a Hoffest on 3 Oct) is open, not closed. Special hours beat holidays everywhere: status, table and schema.org.
- A holiday on a day the business is closed anyway needs no action.
- `Holidays::forYear()` and `norenHolidays()` list the holidays of a year, including the extra days.

## Edge cases

### Overnight blocks

A block that ends after midnight is entered on its **start day**: Friday 18:00 to 2:00 goes into Friday's row, with the end as `02:00`. The table shows `18:00 – 2:00`, schema.org gives Friday `opens 18:00 closes 02:00`, and the status is open on Saturday until 2:00.

- `overnightUntil` (default `'06:00'`) is the latest end on the next day. An end later than that, such as `18:00` to `08:00`, is most likely a typo and is ignored.
- `18:00` to `00:00` is a block until midnight. The status shows `until 24:00`, or later if the next day's first block continues the run.
- `false` switches overnight blocks off. Such a row (ending after midnight) is then ignored. A row ending at `00:00` still counts as until midnight.
- The status follows the opening as far as it runs. Friday `18:00–00:00` and Saturday `00:00–02:00` are one run, so on Friday at 23:30 the status says `until 2:00`. The run stops at midnight when the next day does not open at `00:00`, and a next day that is closed or a holiday without special hours stops it as well.
- The part after midnight is cancelled when the **start day** (Friday) is a full closure, or a holiday without special hours. A closure that begins on Saturday does not cancel Friday's block, because the block belongs to Friday.

### Special hours beat holidays

A day with special hours (`oeffnet`/`schliesst` on a closure row) is open at those hours, even on a public holiday. A holiday on a day with no special hours is closed. A holiday on a day when the weekly plan is closed anyway does not change the status: it falls through to "closed today".

### Half-filled rows are ignored

- An hours row with only a start, or only an end, contributes no block.
- A closure row with only one of `oeffnet` and `schliesst` is a full-day closure.

### An empty plan shows nothing, never "closed"

A location without any hours prints nothing from the snippets, has no entry in `norenUnits()`, and `norenHasHours()` returns `false`. `norenStatus()`, `norenTable()`, `norenCompact()` and `norenSchema()` return `null` for it. Noren does not claim "closed" for a plan it does not have.

A field that is not valid structure data (a plain-text `zeiten` field, for example) is treated as an empty plan and logged once. The page still renders.

### Lead time and schema.org

`leadDays` (default 14) controls only what the site shows: a closure or special day appears in the table and the notes this many days before it starts, so the visitor is not warned about a week in October while it is still June. schema.org gets **all** upcoming closures and special days, without a lead time, because maps and search engines should know early. The holidays in schema.org cover the next 365 days.

### "Back on" skips rest days

For a closure range without a `hinweis`, the status says "back on" with the first **open** day after the end of the closure. Rest days and directly following closures and holidays are skipped. A `hinweis` replaces the automatic text.

### Eve notice (opt-in)

With `holidayEve` set to `true`, the status adds a notice when tomorrow is a public holiday, for example "morgen Feiertag (Tag der Deutschen Einheit) · öffnet am Montag um 13:00 Uhr". The notice is shown only when tomorrow has no special hours and no closure, and the weekly plan would be open on that day. It is off by default.

### Time zone and daylight saving

All date logic runs in `noren.timezone` (default `Europe/Berlin`). "Now" is the server's clock converted into that zone. It is not the visitor's device clock, so a visitor in another time zone sees the business's status. Daylight saving changes are handled by PHP's time zone rules; block times are wall-clock times in the configured zone.

### Caching

Schedules are not cached inside Noren. Two areas can live on the same page, and a cache keyed on the page would hand the second area the first plan. Each call reads the page again.

The open status, the table, the compact list and the schema.org arrays depend on the time. Whenever Noren renders one of them (the snippets, or the `norenStatus()`, `norenTable()`, `norenCompact()` and `norenSchema()` methods), it switches off Kirby's **page cache** for that response, so a page rendered on Monday does not say "open until 18:00" on Sunday. Pages without Noren output keep their page cache.

Caches in front of Kirby do not know this. A reverse proxy, a CDN or a static HTML cache (for example Cloudflare's "cache everything") may still keep the HTML with the status. Exclude pages with a status band from such caches, or give them a short lifetime.

### Closures per area

A closure with a `bereich` applies only to that area: a closure for the café leaves the shop open. A closure without a `bereich` applies to every area.

## Upgrading from site/plugins/betrieb

This section is for sites that run the kirby-starter copy `site/plugins/betrieb`. Noren replaces it. Nothing is aliased: the new names are the only names.

### Coexistence

Both plugins can stay installed during the switch. They have different plugin ids, namespaces and method names, so they load side by side without conflict. Switch one template or snippet at a time, then delete `site/plugins/betrieb/`, its snippets and its option values.

### Why there are no aliases

Noren does not register the old German method names (`oeffnungszeiten`, `bereiche`, `bereicheMitZeiten`, `betrieb`, `hatZeiten`). While `site/plugins/betrieb` is still installed, identical names would collide, and the objects they return have different method names anyway. Old snippets would break either way, so the switch is done once per snippet, using the table below.

### Content stays unchanged

No content needs to change. The field names of the [content field contract](#content-field-contract) are the old ones. The option values move to the new names:

| Before | Now |
|---|---|
| `betrieb.feiertage` | `noren.holidays` (the legacy array keys are accepted; without `land` they mean Bavaria) |
| `betrieb.schliessungLabel` | `noren.closureLabel` |

Noren does not handle phone numbers, addresses or the `betriebsangabe` field. Keep those in the site as before.

### Steps

1. Install Noren and add `noren.active` (and `noren.holidays`) to `site/config/config.php`. A site with German texts and no Kirby `locale` also sets `'noren.language' => 'de'`, otherwise the status and table texts are English.
2. Change the page blueprint to `title: noren.panel.page.title` plus `extends: noren/pages/location`, or extend the two tabs (`noren/tabs/areas` and `noren/tabs/hours`) in the site's own blueprint.
3. Replace the calls with the new names (see the rename table below) and the snippets `zeiten-tabelle` and `statusband` with `noren/table` and `noren/status`.
4. Delete `site/plugins/betrieb/` once nothing calls it any more.

### Behaviour changes

The switch is not byte-identical in all cases. Seven changes, each listed in the [CHANGELOG](CHANGELOG.md#100--2026-10-11):

1. **Overnight blocks** are read. Before, a row whose end lay before its start was ignored. A Friday 18:00 to 2:00 is now open on Saturday until 2:00. A row ending at 00:00 is read as until midnight in every case, also with `overnightUntil` set to `false`.
2. **A business without areas gets a status line.** The starter's `bereicheMitZeiten()` returned nothing when no areas were defined, so the status band stayed empty.
3. **The status label uses the area's short name** (`kurzname`), as its Panel help always promised.
4. **A holiday with special hours is no longer listed as closed** in the table note and in schema.org.
5. **Holidays for all 16 states**, not only Bavaria. An unknown state is logged instead of silently meaning "no holidays".
6. **The texts follow `noren.language`, then `locale`, then English.** The copy was always German. A German site without a Kirby `locale` must set `noren.language`, see the steps above.
7. **Touching and overlapping blocks are merged.** The copy listed `08:00–12:00` and `12:00–18:00` as two rows and its status said "until 12:00". Noren shows one opening, `08:00 – 18:00`, and says "until 18:00".

### Rename table

| Before | Now |
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

## Privacy

Noren sets no cookies, uses no browser storage, makes no request to a third party and processes no visitor data. The status is computed on your server for each page request from the business's own content. [PRIVACY.md](PRIVACY.md) describes the implementation in English, and [DATENSCHUTZ.md](DATENSCHUTZ.md) holds the German text block for a site's privacy policy. Both describe the implementation and are not legal advice.

## Development

```sh
composer install --ignore-platform-req=ext-gd   # Kirby is a dev dependency, used by the Kirby tests
composer test                                   # or: sh tests/run.sh
```

The tests are plain PHP scripts: each `tests/*Test.php` is a standalone script, exit code 0 means pass. Run one with `php tests/ScheduleTest.php`. The Kirby-free tests load the source classes directly; the Kirby tests boot a real Kirby instance from the development dependency. None needs a network connection.

## Maintenance

This module is open source under the MIT license and is maintained for the author's own projects. Issues and pull requests are welcome, but there is no support promise and no release schedule. Report security problems privately through GitHub's private vulnerability reporting (the "Report a vulnerability" button on the Security tab).

## License

MIT, see [LICENSE](LICENSE).
