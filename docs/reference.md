# Noren reference

Details behind the [README](../README.md): methods, return shapes, markup classes, blueprints and holiday rules.

## Methods

| Site method (`site()->…`) | Returns |
|---|---|
| `norenLocations()` | All location pages (`[]` when off). |
| `norenLocation()` | The first location page, or `null`. |
| `norenAreas()` | Areas of the first location: `key`, `name`, `short`. |
| `norenSchedule($area = null)` | `Sayamaapps\Noren\Schedule` of the first location, or `null`. |
| `norenUnits()` | One unit per area or location with hours (see [Units](#units)). |
| `norenStatus($area = null)` | Status array, or `null` without a plan. |
| `norenTable($area = null)` | Table array, or `null` without a plan. |
| `norenCompact($area = null, $mode = null)` | Compact array, or `null` without a plan. |
| `norenSchema($area = null)` | Schema.org opening hours, or `null` without a plan. |
| `norenHolidays($year = null)` | Holidays of a year as `date => name` (`[]` when off). |

| Page method (`page()->…`) | Returns |
|---|---|
| `norenSchedule($area = null)` | The page's schedule if it is a location; otherwise the first location's schedule for `$area` or `zeitenschluessel`. |
| `norenAreas()` | Areas of this location, or of the first location. |
| `norenHasHours($area = null)` | Whether the schedule has any hours. |
| `norenStatus`, `norenTable`, `norenCompact`, `norenSchema` | As the site methods, for this page. |

Status, table, compact and schema methods switch off Kirby's page cache, because they depend on the time. `norenSchedule()` is never cached, since two areas can share one page. In your own snippets, `Sayamaapps\Noren\Options::get(kirby(), 'name', $default)` reads a `sayamaapps.noren.<name>` option (the 1.x spelling `noren.<name>` still works). `noren_option()` is deprecated.

## Return shapes

Status (`norenStatus()`):

```php
[
    'open' => true, 'text' => 'Open now', 'detail' => 'open until 18:00',
    'state' => 'open',      // open | closed | holiday | closure
    'until' => '18:00',     // null when open around the clock
    'next' => null,         // 'Y-m-d\TH:i' while closed
    'occasion' => null, 'holiday' => null,
]
```

Table (`norenTable()`), seven entries in `days`, Monday first:

```php
[
    'days'     => [['index' => 0, 'name' => '…', 'short' => 'Mo', 'blocks' => [[780, 1080]],
                   'hours' => ['13:00 – 18:00'], 'closed' => false, 'note' => null, 'today' => true]],
    'holidays' => ['label' => '…', 'text' => '…', 'today' => false],   // null unless holidays are on
    'notes'    => [['type' => 'holiday', 'from' => '2026-10-03', 'to' => '2026-10-03', 'text' => '…', 'note' => null]],
]                    // note types: holiday, special, closedDay, closure
```

Compact (`norenCompact()`):

```php
['mode' => 'grouped', 'groups' => [['days' => [2, 4], 'label' => '…', 'blocks' => [[600, 1080]], 'hours' => '10–18', 'note' => null]], 'notes' => []]
```

Schema (`norenSchema()`):

```php
['openingHoursSpecification' => [['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '13:00', 'closes' => '18:00']],
 'specialOpeningHoursSpecification' => [['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-12-24', 'validThrough' => '2026-12-24', 'opens' => '08:30', 'closes' => '12:00']]]
```

A full day is `00:00` to `23:59`, a closed day `00:00` to `00:00`. Closures and special days are listed without lead time; holidays cover the next 365 days.

## Units

```php
[['id' => 'kontakt#laden', 'location' => $page, 'area' => 'laden', 'name' => 'Hofladen', 'short' => 'Laden', 'label' => 'Laden', 'schedule' => $schedule]]
```

With `mergeAreas`, a merged unit has the id `kontakt#laden+cafe` and an `areas` list of its keys.

## Schedule object

`norenSchedule()` returns a `Sayamaapps\Noren\Schedule`: `blocks($weekday)` (`[[from, to], …]` in minutes, Monday = 0), `note($weekday)`, `week()`, `maintained()`, `status($now)`, `blocksOn($day)`, `closureOn($day)`, `specialOn($day)`, `holidayOn($day)`, `upcomingClosures($from, $leadDays)`, `upcomingHolidays($from, $days = 365)` and `timezone()`.

## Blueprints

| Key | Content |
|---|---|
| `noren/pages/location` | Complete page blueprint with the tabs `bereiche` and `zeiten`. |
| `noren/tabs/areas`, `noren/tabs/hours` | The two tabs, for your own page blueprint. |
| `noren/fields/areas`, `hours`, `closures` | The structures; the `-info` variants hold their info texts. |

Add the hours tab under `tabs:` with `zeiten: { extends: noren/tabs/hours }`. The area selects use `query: page.bereiche.toStructure`; if you rename `areas`, copy the tab and change that query.

## Markup classes

Noren prints only `noren-*` classes. Style them yourself.

- Status: `noren-status`, `noren-status-line` (`data-open="true|false"`, `data-state`), `noren-status-label`, `noren-status-text`, `noren-status-detail`, `noren-status-more`.
- Table: `noren-table`, `noren-row` (`data-today`), `noren-row--holidays`, `noren-day`, `noren-hours`, `noren-closed`, `noren-note`, `noren-extra` (`data-holiday`, `data-special`), `noren-closure`.
- Compact: `noren-compact`, `noren-compact-title`, `noren-compact-list`, `noren-compact-days`, `noren-compact-hours`, `noren-compact-note`, `noren-compact-closure`.

## Public holidays by state

Counts are for 2026 with the default flags. Reformation Day was a holiday in every state in 2017.

| State | Holidays in addition to the nine | 2026 |
|---|---|---|
| BW Baden-Württemberg | Epiphany, Corpus Christi, All Saints' Day | 12 |
| BY Bayern | Epiphany, Corpus Christi, Assumption Day (`assumption`), All Saints' Day; Augsburg Peace Festival with `augsburg` | 13 |
| BE Berlin | International Women's Day (from 2019); Liberation Day (8 May) in 2020 and 2025; 17 June 2028 | 10 |
| BB Brandenburg | Easter Sunday and Whit Sunday (`sundays`), Reformation Day | 12 |
| HB Bremen | Reformation Day (from 2018) | 10 |
| HH Hamburg | Reformation Day (from 2018) | 10 |
| HE Hessen | Corpus Christi | 10 |
| MV Mecklenburg-Vorpommern | International Women's Day (from 2023), Reformation Day | 11 |
| NI Niedersachsen | Reformation Day (from 2018) | 10 |
| NW Nordrhein-Westfalen | Corpus Christi, All Saints' Day | 11 |
| RP Rheinland-Pfalz | Corpus Christi, All Saints' Day | 11 |
| SL Saarland | Corpus Christi, Assumption Day (always), All Saints' Day | 12 |
| SN Sachsen | Reformation Day, Day of Repentance and Prayer; Corpus Christi with `corpusChristi` | 11 |
| ST Sachsen-Anhalt | Epiphany, Reformation Day | 11 |
| SH Schleswig-Holstein | Reformation Day (from 2018) | 10 |
| TH Thüringen | World Children's Day (from 2019), Reformation Day; Corpus Christi with `corpusChristi` | 11 |

Easter is calculated with the anonymous Gregorian algorithm, so no date list has to be maintained.

## Edge cases

- **Overnight blocks.** An end up to `overnightUntil` (default `06:00`) on the next day belongs to the start day. Friday 18:00 to 2:00 gives "open until 2:00" on Saturday at 01:00. With `overnightUntil` set to `false`, such rows are ignored.
- **Full day.** `00:00` to `00:00` is open around the clock. The status follows the run across days, for example "open until 24:00" on a Saturday followed by a closed Sunday.
- **Special hours beat holidays.** A closure row with `oeffnet` and `schliesst` on a public holiday gives open at those hours. A holiday without special hours is closed.
- **No plan, no output.** A location without hours prints nothing and is never shown as closed: the status, table, compact and schema methods return `null`.
- **Lead time.** `leadDays` only limits what the site shows. schema.org always gets every closure and special day.
- **Back on.** For a closure range without `hinweis`, the status names the first open day after the range; rest days and holidays are skipped. A `hinweis` replaces the text.
- **Time zone.** All date logic runs in `timezone`. "Now" is the server clock converted to that zone, not the visitor's clock.
- **Caches in front of Kirby.** A reverse proxy or a CDN that caches whole pages can keep an old status. Exclude pages with a status line, or give them a short lifetime.
