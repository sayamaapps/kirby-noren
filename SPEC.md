# Noren — build specification

Noren (暖簾, the shop curtain hung out when a shop is open) is a Kirby 5 plugin
for opening hours: weekly plan with several blocks per day, areas, locations,
closures and special hours, German public holidays for all 16 states, an
"open now" status, compact and table output, schema.org arrays. **No styling.**

Package `sayamaapps/kirby-noren`, plugin id `sayamaapps/noren`, namespace
`Noren`, option prefix `noren.`, directory
this repository.

It replaces `site/plugins/betrieb` of kirby-starter (`Betrieb\Oeffnungszeiten`,
`Betrieb\Feiertage`, the snippets `zeiten-tabelle`, `statusband`, the hours part
of `strukturdaten`) and the variants in praxisbeispiel (`Praxis\Sprechzeiten`),
gasthaus-muster (`Karte::zeitgruppen()`) and radladen (`zeiten-kurz`).
`Telefon.php`, address fields and `betriebsangabe` are **out of scope**.

This document is the contract. Section numbers are referenced from code
comments and task prompts.

---

## 1. Ground rules for every builder

- PHP 8.2 compatible code (no typed class constants, no `readonly` classes,
  no 8.3+ functions). Run with `ASDF_PHP_VERSION=8.5.3 php …` (asdf otherwise
  says "No version is set").
- Code, identifiers, comments, README in **English**. German only in
  `translations/de.php` and `DATENSCHUTZ.md`.
- Never hard-code `Europe/Berlin` in logic: use `Schedule::DEFAULT_TIMEZONE`,
  `$schedule->timezone()` or `Options::timezone()`.
- Every user-facing string comes from `I18n::t()` (keys in §5). If you need a
  key that does not exist, do NOT add it — write it into `NOTES-<task>.md`
  and use the closest existing key.
- Only edit files your task owns (§2). Need a change in a file you do not
  own (a bug in Schedule.php, a missing translation, a helper)? Write it into
  `NOTES-<task>.md` in the plugin root: file, problem, proposed change. Do not
  work around it with a copy of the logic.
- Tests: standalone PHP scripts `tests/<Name>Test.php`, exit code 0 = pass, no
  PHPUnit. Use `tests/helpers.php` (`expect()`, `holds()`, `only()`, `at()`,
  `field()`, `finish()`, Kirby fixtures `noren_site()`, `noren_kirby()`,
  `noren_page()`, `noren_remove_tree()`). Kirby-free tests require
  `src/I18n.php` and the classes they need directly, never `index.php`.
  Kirby tests never require src files; Kirby loads the plugin through the
  symlink `noren_site()` creates.
- Do not push, do not create GitHub repos, do not touch any other repository
  (the sources in §0 are read-only). Never delete files outside the plugin
  directory. Do not run `git commit`.
- No memoisation of schedules keyed on the page id, anywhere (two areas live
  on the same page; the second call would silently get the first plan).

### 0. Sources (read-only)

| What | Path |
|---|---|
| Original plugin | `../kirby-starter/site/plugins/betrieb/{index.php,src/Oeffnungszeiten.php,src/Feiertage.php}` |
| Original snippets | `../kirby-starter/site/snippets/{zeiten-tabelle.php,statusband.php,strukturdaten.php}` |
| Original blueprint, config | `../kirby-starter/site/blueprints/pages/betrieb.yml`, `site/config/betrieb.example.php` |
| Original tests | `../kirby-starter/tests/{OeffnungszeitenTest.php,FeiertageTest.php}`, `../mustergasthaus/tests/OeffnungszeitenTest.php` (one extra check "Ohne Feiertagsprüfung wäre derselbe Samstag offen") |
| Several locations, eve-of-holiday notice | `../praxisbeispiel/site/plugins/praxis/{index.php,src/Sprechzeiten.php}`, `praxisbeispiel/site/blueprints/pages/standort.yml` |
| Consecutive grouping | `../gasthaus-muster/site/plugins/gasthaus/src/Karte.php` (`zeitgruppen()`), `gasthaus-muster/tests/GasthausKarteTest.php` |
| Non-consecutive grouping | `../radladen/site/snippets/zeiten-kurz.php` |
| Family conventions | `../kirby-koe` (README, CHANGELOG, PRIVACY, DATENSCHUTZ, tests), `../kirby-kizami` |

---

## 2. File tree and owners

`architect` = written before the build, shared, **builders never edit**.
`architect → <task>` = written by the architect as working code; from now on
owned by that task, which verifies it with tests and may fix it.

```
kirby-noren/
├── composer.json                    architect
├── composer.lock, vendor/           generated (composer install), not committed
├── .gitignore, .gitattributes       architect
├── LICENSE                          architect
├── .github/workflows/ci.yml         architect
├── SPEC.md                          architect
├── index.php                        architect   (complete registration, §3.9)
├── src/
│   ├── I18n.php                     architect   (§3.3)
│   ├── Options.php                  architect   (§3.3, §4)
│   ├── Schedule.php                 architect → schedule      (§3.1)
│   ├── Holidays.php                 architect → holidays      (§3.2)
│   ├── Grouping.php                 stub → presentation       (§3.4)
│   ├── Presenter.php                stub → presentation       (§3.5)
│   ├── SchemaOrg.php                stub → kirby              (§3.6)
│   └── Locations.php                stub → kirby              (§3.7)
├── snippets/noren/
│   ├── status.php                   placeholder → presentation (§3.8)
│   ├── table.php                    placeholder → presentation (§3.8)
│   └── compact.php                  placeholder → presentation (§3.8)
├── blueprints/
│   ├── fields/areas.yml             placeholder → panel (§7)
│   ├── fields/areas-info.yml        placeholder → panel
│   ├── fields/hours.yml             placeholder → panel
│   ├── fields/hours-info.yml        placeholder → panel
│   ├── fields/closures.yml          placeholder → panel
│   ├── fields/closures-info.yml     placeholder → panel
│   ├── tabs/areas.yml               placeholder → panel
│   ├── tabs/hours.yml               placeholder → panel
│   └── pages/location.yml           placeholder → panel
├── translations/en.php, de.php      architect   (§5)
├── tests/
│   ├── run.sh                       architect
│   ├── helpers.php                  architect
│   ├── I18nTest.php                 architect
│   ├── ScheduleTest.php             schedule      (§9.1)
│   ├── HolidaysTest.php             holidays      (§9.2)
│   ├── GroupingTest.php             presentation  (§9.3)
│   ├── PresenterTest.php            presentation  (§9.4)
│   ├── SnippetsTest.php             presentation  (§9.5)
│   ├── SchemaOrgTest.php            kirby         (§9.6)
│   ├── KirbyTest.php                kirby         (§9.7)
│   └── BlueprintsTest.php           panel         (§9.8)
├── README.md                        docs   (§10)
├── CHANGELOG.md                     docs
├── PRIVACY.md                       docs
└── DATENSCHUTZ.md                   docs
```

Run everything: `cd this repository && ASDF_PHP_VERSION=8.5.3 sh tests/run.sh`.

---

## 3. Architecture and public API

```
            content (structure fields, German field names = contract §6)
                                │
   Kirby side                   ▼
   ┌──────────────┐   Schedule::fromFields()   ┌──────────────────────────┐
   │ Locations    │ ─────────────────────────▶ │ Schedule (one area of    │◀── Holidays::checker()
   │ (pages, areas│   settings from Options    │ one location)            │
   │  units, glue)│                            └──────────────────────────┘
   └──────┬───────┘                                  │        │        │
          │ site/page methods (index.php)            ▼        ▼        ▼
          │                                       Grouping  Presenter  SchemaOrg
          ▼                                           (plain arrays, Kirby-free)
      snippets/noren/{status,table,compact}.php  ◀─────────┘
```

Everything except `Locations`, `Options` and the snippets is Kirby-free and
tested without booting Kirby. Weekday indices are **0 = Monday … 6 = Sunday**
everywhere in PHP; translation keys use ISO numbers (`day.1` = Monday).
Times are **minutes since 0:00** of the block's day (`int`); a block is
`[from, to]` with `to > from`; `to` may exceed 1440 (overnight, §3.1.4).

### 3.1 `Noren\Schedule` (src/Schedule.php — written, owner `schedule`)

Port of `Betrieb\Oeffnungszeiten`. Read the file; its docblocks are part of
this spec. Public surface:

```php
final class Schedule {
    const DAYS_DE = ['Montag', …, 'Sonntag'];    // stored content values
    const DAYS_EN = ['Monday', …, 'Sunday'];     // also accepted in content
    const DEFAULT_TIMEZONE = 'Europe/Berlin';
    const DEFAULT_LOOKAHEAD = 90;                // days searched for the next opening
    const DEFAULT_OVERNIGHT_UNTIL = 360;         // 06:00
    const MINUTES_PER_DAY = 1440;

    __construct(array $days, array $closures = [], ?string $closureLabel = null,
                ?Closure $holiday = null, array $settings = [])
    static fromFields(mixed $hoursField, mixed $closuresField = null, ?string $area = null,
                      ?string $closureLabel = null, ?Closure $holiday = null, array $settings = []): self
    static block(?int $from, ?int $to, ?int $overnightUntil = 360): ?array
    static parseTime(string $time): ?int          // "08:00", "08:00:00" → 480; else null
    static dayIndex(string $value): ?int          // "Montag"/"monday"/"1" → 0
    static formatTime(int $minutes): string       // 510 "8:30", 1440 "24:00", 1560 "2:00"

    blocks(int $weekday): array                   // weekly plan, sorted
    note(int $weekday): ?string
    week(): array                                 // [0..6 => ['blocks' => …, 'note' => …]]
    maintained(): bool                            // any block at all
    hasHolidays(): bool
    timezone(): DateTimeZone
    closures(): array                             // normalised entries
    closureOn(DateTimeImmutable $day): ?array     // full-day closure (hours === null)
    specialOn(DateTimeImmutable $day): ?array     // entry with hours
    blocksOn(DateTimeImmutable $day): array       // special hours before weekly plan
    label(array $closure): string                 // occasion ?? "Geschlossen" (1 day) ?? closureLabel()
    closureLabel(): string                        // ctor value ?? t('closure.label')
    holidayOn(DateTimeImmutable $day): ?string
    upcomingHolidays(?DateTimeImmutable $from = null, int $days = 365): array   // [['date' => 'Y-m-d', 'name' => …]]
    upcomingClosures(?DateTimeImmutable $from = null, ?int $leadDays = null): array
    status(?DateTimeImmutable $now = null): array
}
```

Constructor input:

```php
$days = [0 => ['blocks' => [[780, 1080]], 'note' => null], 1 => [...], …];  // missing days = closed
$closures = [
    ['from' => '2026-12-24', 'to' => '2026-12-24', 'occasion' => 'Heiligabend',
     'hours' => [510, 720], 'note' => null, 'area' => null],
    ['from' => '2027-08-02', 'to' => '2027-08-15'],              // optional keys default to null
];
$settings = ['timezone' => 'Europe/Berlin', 'holidayEve' => false, 'lookahead' => 90];
// fromFields() additionally: 'overnightUntil' => 360 (minutes; null = overnight off; key missing = 360)
```

#### 3.1.1 Reading content (fromFields)

- Rows for the same weekday are **appended**, never overwritten; blocks are
  sorted by start.
- Area filter: with `$area !== null` only rows whose `bereich` equals it;
  `$area === null` takes all rows (business without areas).
- Per pair: `von1/bis1`, `von2/bis2`; when both of a pair are empty the
  legacy `vm_von/vm_bis` resp. `nm_von/nm_bis` are read. A block needs both
  times (half-filled = ignored). Note: `hinweis`, fallback `nm_hinweis`.
- Day values: German names (stored contract), English names
  (case-insensitive) or ISO digits 1–7. Others ignored.
- Closure rows: `von`, `bis` (both required, first 10 chars), `bereich`
  (empty = all areas; with an `$area` given, rows for other areas are
  skipped), `anlass` → occasion, `hinweis` → note, `oeffnet`/`schliesst` →
  `hours` via `block()` (half-filled = full closure).

#### 3.1.2 Blocks (`block()`)

| from | to | overnightUntil | result |
|---|---|---|---|
| 08:00 | 12:00 | any | `[480, 720]` |
| 18:00 | 00:00 | any (even null) | `[1080, 1440]` — until midnight |
| 18:00 | 02:00 | 360 (default) | `[1080, 1560]` — overnight |
| 18:00 | 08:00 | 360 | `null` — most likely a typo, ignored |
| 18:00 | 02:00 | null | `null` — overnight off |
| 10:00 | 10:00 | any | `null` |

#### 3.1.3 Status

`status(?DateTimeImmutable $now = null)` — `$now` is converted into the
schedule's timezone; default now there. Returns

```php
[
    'open'     => true,                 // bool
    'text'     => 'Jetzt geöffnet',     // headline, translated
    'detail'   => 'noch bis 18:00 Uhr', // '' when nothing to say
    'state'    => 'open',               // 'open' | 'closed' | 'holiday' | 'closure'
    'until'    => '18:00',              // formatTime() of the block end while open, else null
    'next'     => null,                 // next opening 'Y-m-d\TH:i' while closed, null if open/unknown
    'occasion' => null,                 // occasion of the special day / closure in effect
    'holiday'  => null,                 // holiday today (state holiday) or tomorrow (eve notice)
]
```

Order of evaluation (first match wins):

0. **Overnight spill-over**: yesterday is not an exception day (no full
   closure, no holiday without special hours) and one of
   `blocksOn(yesterday)` ends after midnight with `now + 1440 < end` → open,
   `detail.until` (or `detail.untilOccasion` with yesterday's special
   occasion). Today's closures/holidays do not end it (the block belongs to
   yesterday).
1. **Closure today** (full day):
   - single day (`from === to`): text `status.occasionClosed` (with
     occasion) or `status.closedToday`; detail = closure note, else
     "öffnet …" of the next open day, else ''. state `closure`.
   - range: text `label()`; detail = note, else `detail.backOn` with the
     first open day **after `to`** (skips rest days and directly following
     closures/holidays), else ''. state `closure`.
2. **Holiday today** — only when no special hours today AND the weekly plan
   would be open on this weekday: text `status.holidayToday`, detail =
   "öffnet …" of the next open day, else the holiday name. state `holiday`.
   A holiday on a rest day falls through to "Heute geschlossen".
3. **Inside a block** of `blocksOn(today)` (`from <= now < to`): open,
   `detail.until` / `detail.untilOccasion` (special day occasion).
4. **Later today**: first block with `now < from` → `status.closedNow`,
   `detail.opensToday`.
5. **Next open day** within `lookahead` days (exception days and days
   without `blocksOn()` skipped): text `status.closedToday` if today has no
   blocks, else `status.closedNow`; detail "öffnet …" or ''.
   **Eve notice** (only with setting `holidayEve` true): tomorrow is a
   holiday, has no special hours, no closure, and the weekly plan would be
   open → detail = `detail.holidayTomorrow` + `detail.separator` + detail
   ("morgen Feiertag (Tag der Deutschen Einheit) · öffnet am Montag um 13:00
   Uhr"), `holiday` = tomorrow's name. Default off (the starter's tests
   expect the plain detail).

Day wording of "öffnet …" (days between today's and the target's midnight):
1 → `detail.opensTomorrow`; 2–6 → `detail.opensWeekday` (full weekday name);
≥ 7 → `detail.opensDate` (`format.date`, "3.10."). Time = first block of
`blocksOn(target)`.

Special opening beats holiday everywhere (status, next open day,
`upcomingHolidays()` skips days with special hours — a fix over the
original, which listed such a day as closed in table and schema.org).

#### 3.1.4 Overnight blocks

A block like Fri 18:00–02:00 is stored as `[1080, 1560]` on **Friday** (the
start day). It is shown as "18:00 – 2:00", in schema.org as Friday opens
"18:00" closes "02:00". Status at Sat 01:00 is open ("noch bis 2:00 Uhr"),
Sat 02:00 is closed. The original ignored such rows (end before start); the
default `overnightUntil` 06:00 keeps treating "18:00–08:00" as a typo.

### 3.2 `Noren\Holidays` (src/Holidays.php — written, owner `holidays`)

```php
final class Holidays {
    const STATES  = ['BW','BY','BE','BB','HB','HH','HE','MV','NI','NW','RP','SL','SN','ST','SH','TH'];
    const ALIASES = ['bayern' => 'BY', 'berlin' => 'BE', …];     // lower-case names, German + English
    const FLAGS   = ['assumption' => true, 'augsburg' => false, 'corpusChristi' => false, 'sundays' => true];

    static easterSunday(int $year, string $timezone = 'Europe/Berlin'): DateTimeImmutable
    static ids(int $year, string $state, array $flags = []): array      // ['2026-01-01' => 'newYear', …] sorted
    static repentanceDay(int $year): string                             // '2026-11-18'
    static forYear(int $year, array $config): array                     // ['2026-01-01' => 'Neujahr', …] incl. extra
    static name(string $id): string                                     // t('holiday.' . $id)
    static config(mixed $option): ?array                                // normalise noren.holidays, throws
    static checker(array $config): Closure                              // fn (DateTimeImmutable): ?string
    static state(string $state): string                                 // 'de-by'/'bayern' → 'BY', throws
}
```

`config()`:

```php
Holidays::config(false) === null;  Holidays::config(null) === null;
Holidays::config('BY') === ['state' => 'BY', 'assumption' => true, 'augsburg' => false,
                            'corpusChristi' => false, 'sundays' => true, 'extra' => []];
Holidays::config(['state' => 'BY', 'augsburg' => true, 'extra' => ['06-24' => 'Stadtfest', '2027-07-02' => 'Dorffest']]);
Holidays::config(['land' => 'bayern', 'mariaeHimmelfahrt' => false, 'friedensfest' => true]); // legacy betrieb.feiertage
// throws InvalidArgumentException: true, 42, ['assumption' => true] (no state), 'XX'
// invalid extra entries are skipped with error_log('Noren: noren.holidays extra entry … skipped …')
```

Rules (`ids()`; `E` = Easter Sunday):

| id | date | states |
|---|---|---|
| newYear | 01-01 | all |
| epiphany | 01-06 | BW, BY, ST |
| womensDay | 03-08 | BE since 2019, MV since 2023 |
| goodFriday | E−2 | all |
| easterSunday | E | BB (flag `sundays`, default on) |
| easterMonday | E+1 | all |
| labourDay | 05-01 | all |
| liberationDay | 05-08 | BE in 2020 and 2025 only (one-off) |
| ascension | E+39 | all |
| whitSunday | E+49 | BB (flag `sundays`) |
| whitMonday | E+50 | all |
| corpusChristi | E+60 | BW, BY, HE, NW, RP, SL; SN, TH with flag `corpusChristi` (default off) |
| augsburgPeace | 08-08 | BY with flag `augsburg` (default off) |
| assumption | 08-15 | SL always; BY with flag `assumption` (default ON) |
| childrensDay | 09-20 | TH since 2019 |
| germanUnity | 10-03 | all |
| reformation | 10-31 | BB, MV, SN, ST, TH always; HB, HH, NI, SH since 2018; every state in 2017 |
| allSaints | 11-01 | BW, BY, NW, RP, SL |
| repentance | Wednesday 16–22 Nov | SN |
| christmas1 | 12-25 | all |
| christmas2 | 12-26 | all |

Count per state in 2026 with default flags: BW 12, BY 13, BE 10, BB 12, HB 10,
HH 10, HE 10, MV 11, NI 10, NW 11, RP 11, SL 12, SN 11, ST 11, SH 10, TH 11.

**Decision on Easter/Whit Sunday**: included only for Brandenburg, the one
state where they are statutory holidays (Feiertagsgesetz BB). Elsewhere a
Sunday holiday changes nothing (the original's reasoning). Flag `sundays`
(default on) lets a BB site drop them. Valid for years from 1995 on (earlier
rules, e.g. Buß- und Bettag nationwide until 1994, are not modelled).

Names are translated on lookup (`checker()` caches untranslated entries per
year), so a language switch is respected. Extra days: a statutory holiday
keeps its name on the same date; `MM-DD` recurs every year, `Y-m-d` once.

### 3.3 `Noren\I18n`, `Noren\Options` (written, architect)

- `I18n::setLanguage()`, `language()`, `table()`, `t($key, $values)`,
  `day(int $weekday)`, `dayShort(int $weekday)`,
  `date(DateTimeInterface|string $date, bool $withYear = false)`.
- `Options::get()`, `active()`, `fields()`, `templates()`, `settings()`,
  `timezone()`, `overnightUntil()`, `closureLabel()`, `language()`,
  `applyLanguage()`. `settings()` returns:

```php
['timezone' => 'Europe/Berlin', 'holidayEve' => false, 'lookahead' => 90, 'overnightUntil' => 360,
 'leadDays' => 14, 'holidayRow' => true, 'holidayNote' => true, 'holidayNoteDays' => 14,
 'closureLabel' => null, 'compact' => 'grouped', 'status' => true]
```

Language order: `noren.language` → current language of a multi-language
site → its default language → first two letters of `locale` → `en`. Every
code path that produces text in a Kirby context calls
`Options::applyLanguage($kirby)` first (Locations wrappers, snippets).

### 3.4 `Noren\Grouping` (owner `presentation`)

Kirby-free. Signatures and examples in the stub `src/Grouping.php`. Group:

```php
['days' => [1, 2], 'label' => 'Di – Mi', 'blocks' => [[660, 960]], 'hours' => '11:00 – 16:00', 'note' => null]
```

- `consecutive(Schedule $s, bool $withNote = false)` — gasthaus rule: only
  adjacent days with identical `blocks()` (and identical `note()` when
  `$withNote`); closed days split groups and form none; week order;
  `label = rangeLabel(days)`, `hours = blocksLabel(blocks)`; `note` = the
  shared note when `$withNote`, else null.
- `byHours(Schedule $s, bool $withNote = true)` — radladen rule: days
  with identical blocks (and note) anywhere in the week; sorted by the start
  of the first block, ties keep week order; `label = listLabel(days)`,
  `hours = shortHoursLabel(blocks)`.
- `rangeLabel([1, 2])` "Di – Mi", `rangeLabel([3])` "Do" (de); en "Tue – Wed".
- `listLabel()` runs: 3+ days "Mo–Mi" (en dash, no spaces), 2 adjacent
  "Mo, Di", single "Fr", joined ", ": `[0,1,2,4]` → "Mo–Mi, Fr", `[2,4]` →
  "Mi, Fr".
- `blocksLabel([[660,960],[1020,1260]])` → "11:00 – 16:00 · 17:00 – 21:00"
  (`Schedule::formatTime`, " – " inside, " · " between); overnight
  `[[1080,1560]]` → "18:00 – 2:00".
- `shortHoursLabel([[600,1080]])` → de "10–18 Uhr", en "10–18";
  `[[600,750],[840,1080]]` → de "10–12:30 und 14–18 Uhr", en
  "10–12:30 and 14–18" (`compact.and`, `compact.hours`).
- `shortTime(600)` "10", `630` "10:30", `1440` "24", `1560` "2".

### 3.5 `Noren\Presenter` (owner `presentation`)

Kirby-free view models; the snippets render exactly these, and
`$site->norenTable()` / `norenCompact()` return them for own markup.
`$now` default: now in `$schedule->timezone()`; `$today` = `$now` at 0:00.

`table(Schedule $s, ?DateTimeImmutable $now = null, array $options = [])`,
options `holidayRow` (true), `holidayNote` (true), `holidayNoteDays` (14),
`leadDays` (14):

```php
[
  'days' => [   // always 7 entries, Monday first, from the WEEKLY plan (blocks())
    ['index' => 0, 'name' => 'Montag', 'short' => 'Mo', 'blocks' => [[780, 1080]],
     'hours' => ['13:00 – 18:00'],          // t('table.range') per block, formatTime()
     'closed' => false, 'note' => null,
     'today' => true],                      // weekday of $now, and today is not a closing holiday
    …,
    ['index' => 6, 'name' => 'Sonntag', 'short' => 'So', 'blocks' => [], 'hours' => [],
     'closed' => true, 'note' => null, 'today' => false],
  ],
  'holidays' => ['label' => 'Feiertage', 'text' => 'geschlossen', 'today' => false],
               // null unless $s->hasHolidays() && holidayRow; today = holidayOn(now) && !specialOn(now)
  'notes' => [  // first the next holiday (if holidays on, holidayNote, within holidayNoteDays,
                // from upcomingHolidays($today, holidayNoteDays) — at most ONE), then closureNotes()
    ['type' => 'holiday', 'from' => '2026-10-03', 'to' => '2026-10-03',
     'text' => '3.10. Tag der Deutschen Einheit: geschlossen', 'note' => null],
  ],
]
```

"Today" in the table: a closing holiday today (`holidayOn` and no special
hours) moves "today" from the weekday row to the holidays row (the weekday
row would show hours of a day that is closed). Without the holidays row, no
row is "today" on such a day.

`closureNotes(Schedule $s, DateTimeImmutable $today, int $leadDays)` — one
note per `upcomingClosures($today, $leadDays)` entry:

| entry | type | text (de) | text (en) |
|---|---|---|---|
| with `hours` | `special` | `note.special`: "24.12. Heiligabend: 8:30–12:00 Uhr" (occasion ?? `special.label`) | "24 Dec Heiligabend: 8:30–12:00" |
| `from === to`, occasion | `closedDay` | `note.closedDayOccasion`: "31.12. Silvester: geschlossen" | "31 Dec Silvester: closed" |
| `from === to`, no occasion | `closedDay` | `note.closedDay`: "16.2.: geschlossen" | "16 Feb: closed" |
| range | `closure` | `note.closure`: "Betriebsferien 2.8. bis 15.8.2027" (`label()`, from `format.date`, to `format.dateYear`) | "Closed for holidays 2 Aug to 15 Aug 2027" |

`note` = the entry's note (or null); `from`/`to` = the entry's dates. Date
of special/closedDay = `from`.

`compact(Schedule $s, ?DateTimeImmutable $now = null, array $options = [])`,
options `mode` ('grouped' | 'consecutive', default 'grouped'), `leadDays`
(14), `closures` (max notes, default 1):

```php
['mode' => 'grouped',
 'groups' => Grouping::byHours($s),            // 'consecutive' → Grouping::consecutive($s)
 'notes'  => array_slice(Presenter::closureNotes($s, $today, $leadDays), 0, $closures)]
```

### 3.6 `Noren\SchemaOrg` (owner `kirby`)

Kirby-free, plain arrays, no JSON. Stub has the signatures.

```php
SchemaOrg::openingHours($s) === [
  ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '13:00', 'closes' => '18:00'],
  …   // one entry per block, Monday → Sunday, blocks in order; closed days none
];
SchemaOrg::specialOpeningHours($s, at('2026-10-15')) === [
  // 1. ALL upcomingClosures($from) (no lead time), in that order
  ['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-12-24', 'validThrough' => '2026-12-24', 'opens' => '08:30', 'closes' => '12:00'],
  ['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-12-31', 'validThrough' => '2026-12-31', 'opens' => '00:00', 'closes' => '00:00'],
  …,
  // 2. upcomingHolidays($from, $holidayDays = 365): closed
  ['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-12-25', 'validThrough' => '2026-12-25', 'opens' => '00:00', 'closes' => '00:00'],
];
SchemaOrg::forSchedule($s, $from) === ['openingHoursSpecification' => […], 'specialOpeningHoursSpecification' => […]];
SchemaOrg::time(510) === '08:30'; time(1440) === '00:00'; time(1560) === '02:00';
```

`$from` default: today in the schedule's timezone. Overnight Fri 18:00–02:00
→ Friday opens "18:00" closes "02:00".

### 3.7 `Noren\Locations` (owner `kirby`)

Kirby glue, static methods exactly as in the stub (index.php calls them).
All of them return `null` / `[]` when `Options::active()` is false.

- **pages(App)**: `noren.locations` set → a Closure is called (may return
  `Pages`, a `Page`, an array of `Page`s or of page ids); a list of page ids
  is resolved with `$kirby->page($id)`; unresolvable entries are skipped.
  Otherwise every page of `$kirby->site()->index()` (listed + unlisted, index
  order) whose `intendedTemplate()` name is in `Options::templates()` (default
  `betrieb`). Returns `list<Page>`, duplicates removed.
- **primary(App)**: `pages()[0] ?? null`.
- **isLocation(Page)**: the page's id is among `pages()`.
- **areas(Page)**: rows of the `fields.areas` structure (default `bereiche`)
  with a non-empty `schluessel`:
  `['key' => 'laden', 'name' => name ?: key, 'short' => kurzname ?: name ?: key]`.
- **schedule(Page, ?area)**: `Schedule::fromFields($page->content()->get(fields.hours),
  $page->content()->get(fields.closures), $area, Options::closureLabel(),
  holidayChecker(), settings subset)` where settings = `timezone`,
  `holidayEve`, `lookahead`, `overnightUntil` from `Options::settings()`.
  Use `$page->content()->get($name)` (not `$page->$name()`: a field name may
  clash with a page method). **No caching.**
- **scheduleFor(Page, ?area)**: location page → `schedule($page, $area)`;
  else `schedule(primary, $area ?? (areaRef field value ?: null))`; null
  without primary.
- **units(App)**: for each location page in order: no areas →
  `schedule($page, null)`, kept if `maintained()`; with areas → one per area
  whose `schedule($page, key)` is maintained (areas without hours are left
  out — "geschlossen" because a field is empty sends people to a door that
  may be open). Unit:

```php
['id' => 'kontakt#laden',            // page id, plus '#' . area key when an area
 'location' => $page, 'area' => 'laden',
 'name' => 'Hofladen',               // area name, or the page title
 'short' => 'Laden',                 // area short, or the page's `fields.short` field (kurzname), or the title
 'label' => 'Laden',                 // ONE location: short. SEVERAL locations: location short
                                     //   (kurzname ?: title) . ' · ' . area short, or just the location short
 'schedule' => Schedule]
```

- **holidayConfig(App)**: `Holidays::config(Options::get('holidays'))`;
  `InvalidArgumentException` → `error_log('Noren: ' . message . ' — public holidays are off.')`, null.
- **holidayChecker(App)**: `Holidays::checker(config)` or null.
- **status/table/compact/schema(?Schedule …)**: null in → null out;
  otherwise `Options::applyLanguage(App::instance())` and delegate to
  `Schedule::status()`, `Presenter::table()` (options from settings:
  holidayRow, holidayNote, holidayNoteDays, leadDays), `Presenter::compact()`
  (mode = `$mode ?? settings.compact`, leadDays), `SchemaOrg::forSchedule()`.
- **holidays(App, ?year)**: `Holidays::forYear(year ?? current year in
  noren.timezone, config)` with language applied; [] when off or no config.

### 3.8 Snippets (owner `presentation`)

Unstyled, only `noren-*` classes, all output escaped with `esc()`. Each
snippet first returns silently when `!Options::active(kirby())`, then calls
`Options::applyLanguage(kirby())`. A site overrides one by creating
`site/snippets/noren/<name>.php`. Data passed in wins over defaults; `$now`
(DateTimeImmutable) exists for tests and previews.

**noren/status** — variables `$units` (default `site()->norenUnits()`),
`$only` (string: keep units whose `id`, `area` or `location->id()` equals
it), `$link` (URL of a page with all hours), `$now`. Prints nothing when
`settings.status` is false or no unit is left. Labels only when more than one
unit is shown.

```html
<div class="noren-status">
  <p class="noren-status-line" data-open="true" data-state="open"><span class="noren-status-label">Laden</span> <b class="noren-status-text">Jetzt geöffnet</b> <span class="noren-status-detail">noch bis 18:00 Uhr</span></p>
  <p class="noren-status-more"><a href="/kontakt">Alle Öffnungszeiten</a></p>
</div>
```

(`data-open` "true"/"false"; label span only with 2+ units; detail span only
when detail is not ''; "more" paragraph only with `$link`.)

**noren/table** — variables `$schedule` (default
`site()->norenSchedule($area ?? null)`), `$area`, `$now`. Prints nothing
for null or `!maintained()`. Renders `Presenter::table()` with the options
from `Options::settings()`:

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

(Closed days ARE printed — a missing row does not answer "open on
Mondays?". `type` holiday → `data-holiday`; special and closedDay →
`data-special`, note appended with " · "; closure → `noren-closure`, note
after `<br>`.)

**noren/compact** — variables `$schedule` (default as table), `$area`,
`$mode` (default `settings.compact`), `$title` (optional), `$now`. Prints
nothing for null or `!maintained()`.

```html
<div class="noren-compact">
  <p class="noren-compact-title">Werkstatt-Zeiten</p>
  <ul class="noren-compact-list">
    <li><span class="noren-compact-days">Mi, Fr</span> <span class="noren-compact-hours">10–18 Uhr</span> <span class="noren-compact-note">nach Vereinbarung</span></li>
  </ul>
  <p class="noren-compact-closure">Betriebsferien 2.8. bis 15.8.2027 — Hinweis</p>
</div>
```

(title paragraph only with `$title`; note span only with a note; closure
paragraphs from `notes`, " — " + note when set.)

### 3.9 Site and page methods (index.php — written)

| Site method | Returns |
|---|---|
| `norenLocations(): array` | `Locations::pages()` |
| `norenLocation(): ?Page` | first location |
| `norenAreas(): array` | areas of the first location |
| `norenSchedule(?string $area = null): ?Schedule` | first location's schedule; null = off / no location |
| `norenUnits(): array` | §3.7 units |
| `norenStatus(?string $area = null): ?array` | §3.1.3 |
| `norenTable(?string $area = null): ?array` | §3.5 table |
| `norenCompact(?string $area = null, ?string $mode = null): ?array` | §3.5 compact |
| `norenSchema(?string $area = null): ?array` | §3.6 forSchedule |
| `norenHolidays(?int $year = null): array` | date => name |

| Page method | Returns |
|---|---|
| `norenSchedule(?string $area = null): ?Schedule` | `Locations::scheduleFor()` |
| `norenAreas(): array` | own areas (location) or the first location's |
| `norenHasHours(?string $area = null): bool` | schedule maintained |
| `norenStatus/norenTable/norenCompact/norenSchema` | as the site methods, page-relative |

Helper: `noren_option(string $key, mixed $default = null)`.

---

## 4. Options

All read as `noren.<name>`, flat or nested; `null` = not set.

| Option | Default | Meaning |
|---|---|---|
| `active` | `false` | Master switch; only `true` switches on. Off: methods return null/[], snippets print nothing. |
| `template` | `'betrieb'` | Template name(s) of location pages (string or list). |
| `locations` | `null` | Closure or list of page ids; overrides `template` (e.g. praxisbeispiel: `fn () => site()->children()->listed()->filterBy('intendedTemplate', 'standort')`). |
| `fields` | see §6 | Map overriding page field names: `areas`, `hours`, `closures`, `areaRef`, `short`. |
| `timezone` | `'Europe/Berlin'` | Zone of all date logic; invalid → logged, default. |
| `holidays` | `false` | `false` / `'BY'` / `['state' => 'BY', 'assumption' => true, 'augsburg' => false, 'corpusChristi' => false, 'sundays' => true, 'extra' => ['MM-DD' or 'Y-m-d' => 'Name']]`. Legacy `['land' => 'bayern', 'mariaeHimmelfahrt' => …, 'friedensfest' => …]` accepted. |
| `holidayEve` | `false` | Evening-before-holiday notice in the status detail. |
| `holidayRow` | `true` | "Feiertage: geschlossen" row in the table (only when holidays are on). |
| `holidayNote` | `true` | Note of the next holiday under the table (only when holidays are on). |
| `holidayNoteDays` | `14` | How far ahead that note looks. |
| `leadDays` | `14` | Closures/special days appear on the site this many days before they start (schema.org always gets all). |
| `lookahead` | `90` | Days searched for the next opening. |
| `overnightUntil` | `'06:00'` | Latest next-day end of an overnight block; `false` = overnight off. |
| `closureLabel` | translated (`Betriebsferien` / `Closed for holidays`) | Name of a multi-day closure ("Praxisurlaub", "Winterpause"). |
| `compact` | `'grouped'` | Default mode of noren/compact: `grouped` (any days with equal hours) or `consecutive`. |
| `status` | `true` | `false` hides the noren/status snippet. |
| `language` | site language | `en` or `de`; else current/default language, `locale`, English. |

---

## 5. Translation keys

The complete tables are `translations/en.php` and `translations/de.php`
(written; `tests/I18nTest.php` keeps them in sync). Registered in Kirby as
`noren.<key>`. The generated list of every key with both texts follows in
§5.1. Groups:

- `day.1`–`day.7`, `dayShort.1`–`dayShort.7`, `format.date`, `format.dateYear`
- `status.*`, `detail.*` — Schedule::status()
- `closure.label`, `closure.singleDay`, `special.label`
- `table.*`, `note.*` — Presenter
- `compact.hours`, `compact.and` — Grouping::shortHoursLabel()
- `holiday.<id>` — Holidays
- `panel.*` — blueprints (§7)

---

## 6. Content field contract

German field names are a **compatibility contract**: existing content of
kirby-starter, Gasthaus Muster and praxisbeispiel must work unchanged. The page-level
names can be changed with `noren.fields`; the row-level names cannot.

| Level | Field | Option key | Default name | Content |
|---|---|---|---|---|
| page | areas | `fields.areas` | `bereiche` | structure: `schluessel` (key, required), `name`, `kurzname` |
| page | hours | `fields.hours` | `zeiten` | structure, rows below |
| page | closures | `fields.closures` | `schliessungen` | structure, rows below (praxisbeispiel: `urlaub`) |
| page | area reference | `fields.areaRef` | `zeitenschluessel` | text on a NON-location page naming an area |
| page | short name | `fields.short` | `kurzname` | text on a location page (status label) |
| hours row | `bereich` | — | | area key, empty = no areas |
| hours row | `tag` | — | | `Montag` … `Sonntag` (also English names, `1`–`7`) |
| hours row | `von1`, `bis1`, `von2`, `bis2` | — | | `HH:MM` or `HH:MM:SS` |
| hours row | `vm_von`, `vm_bis`, `nm_von`, `nm_bis` | — | | legacy, read when the matching pair is empty |
| hours row | `hinweis` (legacy `nm_hinweis`) | — | | note for the weekday |
| closure row | `von`, `bis` | — | | `Y-m-d`, both required, inclusive |
| closure row | `bereich` | — | | only this area; empty = all |
| closure row | `anlass` | — | | occasion ("Silvester") |
| closure row | `hinweis` | — | | replaces the automatic "wieder ab …" |
| closure row | `oeffnet`, `schliesst` | — | | short hours instead of closed (both required) |

---

## 7. Blueprints (owner `panel`)

YAML files registered in index.php under the keys `noren/fields/*`,
`noren/tabs/*`, `noren/pages/location`; sites use them with `extends:`.
They port `kirby-starter/site/blueprints/pages/betrieb.yml` tabs "Bereiche"
and "Zeiten" exactly (same field names, types, widths, `display: HH:mm`,
`required`, columns, select queries), with every label/help/placeholder/info
text replaced by a translation key.

- Labels, help, placeholders, info `text`, tab labels, page title: plain key
  strings, e.g. `label: noren.panel.hours.label` (Kirby translates them;
  verified).
- **Option texts need the wildcard form** (a plain key string is NOT
  translated there, verified):
  ```yaml
  options:
    - value: Montag
      text:
        '*': noren.day.1
  ```
- Area selects keep `query: page.bereiche.toStructure`, `text: "{{ structureItem.name }}"`,
  `value: "{{ structureItem.schluessel }}"` (a site that renames the field
  overrides the query in its own blueprint; document in README).

| Key | File | Content |
|---|---|---|
| `noren/fields/areas` | fields/areas.yml | structure `bereiche` definition (label `noren.panel.areas.label`, columns schluessel/name, fields schluessel/name/kurzname) |
| `noren/fields/areas-info` | fields/areas-info.yml | `type: info`, `theme: info`, text `noren.panel.areas.info` |
| `noren/fields/hours` | fields/hours.yml | structure `zeiten` (columns bereich/tag/von1/bis1; fields bereich, tag, von1, bis1, von2, bis2, hinweis) |
| `noren/fields/hours-info` | fields/hours-info.yml | info, theme notice, `noren.panel.hours.info` |
| `noren/fields/closures` | fields/closures.yml | structure `schliessungen` (columns anlass/von/bis/hinweis; fields anlass, von, bis, bereich, hinweis, oeffnet, schliesst) |
| `noren/fields/closures-info` | fields/closures-info.yml | info, theme negative, `noren.panel.closures.info` |
| `noren/tabs/areas` | tabs/areas.yml | label `noren.panel.tab.areas`, icon grid; fields `bereicheHinweis` (extends areas-info), `bereiche` (extends areas) |
| `noren/tabs/hours` | tabs/hours.yml | label `noren.panel.tab.hours`, icon clock; fields `zeitenHinweis`, `zeiten`, `trennerSchliessungen` (type line), `schliessungenHinweis`, `schliessungen` |
| `noren/pages/location` | pages/location.yml | title `noren.panel.page.title`, icon clock, tabs `bereiche` (extends noren/tabs/areas), `zeiten` (extends noren/tabs/hours) |

Example use in a site: `site/blueprints/pages/betrieb.yml` →
`extends: noren/pages/location`, or a tab `zeiten: { extends: noren/tabs/hours }`
next to the site's own address tab.

---

## 8. Compatibility decisions

- **No deprecated aliases.** Noren registers none of the old German method
  names (`oeffnungszeiten`, `bereiche`, `bereicheMitZeiten`, `betrieb`,
  `hatZeiten`). Reason: while `site/plugins/betrieb` is still installed,
  identical names would collide (Kirby keeps one), and the objects they
  return have different method names anyway, so old snippets would break
  regardless. Instead both plugins load side by side without conflict;
  a site switches its snippets at its own pace and then deletes the copy.
- Content stays unchanged (§6). The old option value `betrieb.feiertage`
  moves verbatim to `noren.holidays` (legacy keys accepted);
  `betrieb.schliessungLabel` → `noren.closureLabel`.
- Behaviour changes, each documented in CHANGELOG:
  1. overnight blocks (§3.1.4) — before: rows with end < start ignored;
  2. a business without areas now gets a status line (the starter's
     `bereicheMitZeiten()` returned [] when no areas were defined, so the band
     stayed empty);
  3. the status label uses the area's short name (`kurzname`, as its Panel
     help always promised);
  4. a holiday with special hours entered is no longer listed as closed in
     the table note and schema.org;
  5. holidays for all states, not only Bavaria; an unknown state is logged
     instead of silently meaning "no holidays".

Rename table (for README "Upgrading from site/plugins/betrieb"):

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
| radladen `zeiten-kurz` (`$plan`) | `noren/compact` (`$schedule`, `$mode`, `$title`) |
| gasthaus `Karte::zeitgruppen($plan)` → `tage`, `zeiten` | `Grouping::consecutive($schedule)` → `label`, `hours` |
| `strukturdaten.php` week/special closures | `$site->norenSchema()` |
| CSS `zeiten-raster`, `zeile`, `zeile-tag`, `zeile-zeit`, `zeile-zu`, `zeile-hinweis`, `zeile--feiertage`, `zeiten-zusatz`, `schliessung`, `data-heute="ja"` | `noren-table`, `noren-row`, `noren-day`, `noren-hours`, `noren-closed`, `noren-note`, `noren-row--holidays`, `noren-extra`, `noren-closure`, `data-today="true"` |
| CSS `band-status`, `band-bereich`, `data-offen="ja"` | `noren-status-line`, `noren-status-label`, `data-open="true"` |

---

## 9. Test plan

Every test file starts with a docblock (what it covers, how to run), uses
`tests/helpers.php`, ends with `finish('<Suite>')`. German expectations run
with `I18n::setLanguage('de')`; reset to `'en'` where English is checked.

### 9.1 tests/ScheduleTest.php (task `schedule`)

Port **every** check of `kirby-starter/tests/OeffnungszeitenTest.php` (same
sample business, same dates, same German expected strings) plus the
mustergasthaus extra check, renamed per §8:
`Oeffnungszeiten::ausFeld($feld, null, 'laden')` →
`Schedule::fromFields(field($rows), null, 'laden')`; constructor arrays with
`blocks`/`note` and closures `from`/`to`/`note`; holiday checker
`Holidays::checker(Holidays::config('BY'))` (require src/Holidays.php);
full status arrays compared with `only($status, ['open', 'text', 'detail'])`.
Keep the legacy row (`vm_/nm_`) and every regression guard (no memoisation:
two areas from one field differ; appended rows; DST 29 March 2026; running
closure kept; lead 14 days boundary 10.12. vs 9.12.).

New checks (expected values exact):

- Full status shape once: Mon 2026-07-06 14:00 shop →
  `['open' => true, 'text' => 'Jetzt geöffnet', 'detail' => 'noch bis 18:00 Uhr', 'state' => 'open', 'until' => '18:00', 'next' => null, 'occasion' => null, 'holiday' => null]`;
  Mon 10:00 → `state` closed, `next` '2026-07-06T13:00'; Sat 2026-10-03 10:00
  with BY → `state` holiday, `holiday` 'Tag der Deutschen Einheit', `next`
  '2026-10-05T13:00'; summer closure 2027-08-05 → `state` closure, `next`
  '2027-08-16T13:00' (Monday 16.8.2027, Muster plan Mo 13:00).
- Overnight (`field([['tag' => 'Freitag', 'von1' => '18:00', 'bis1' => '02:00']])`):
  `blocks(4) === [[1080, 1560]]`; Fri 2026-10-09 17:00 'öffnet heute um 18:00 Uhr';
  Fri 23:00 open 'noch bis 2:00 Uhr', until '2:00'; Sat 01:00 open; Sat 01:59 open;
  Sat 02:00 `open` false, text 'Heute geschlossen', detail 'öffnet am Freitag um 18:00 Uhr';
  Thu 23:00 'öffnet morgen um 18:00 Uhr'.
  '18:00'–'00:00' → `[[1080, 1440]]`, Fri 23:59 'noch bis 24:00 Uhr'.
  '18:00'–'08:00' → no block; with settings `overnightUntil` 600 → `[[1080, 1920]]`;
  with `overnightUntil` null '18:00'–'02:00' → no block.
  Friday full closure 2026-10-09 → Sat 2026-10-10 01:00 not open.
  Closure starting Saturday 2026-10-10 → Sat 01:00 still open (belongs to Friday).
  Good Friday 2026-04-03 with BY → Sat 2026-04-04 01:00 not open.
  Special day Thu 2026-12-31 occasion 'Silvesterparty' oeffnet 20:00 schliesst 03:00
  (weekly plan closed on Thursdays) → 2027-01-01 01:30 (Neujahr, BY) open,
  detail 'noch bis 3:00 Uhr (Silvesterparty)'.
- Eve notice (settings `holidayEve` true, Muster plan, BY): Fri 2026-10-02 19:00 →
  detail 'morgen Feiertag (Tag der Deutschen Einheit) · öffnet am Montag um 13:00 Uhr',
  `holiday` 'Tag der Deutschen Einheit'; Fri 2026-10-02 07:00 → 'öffnet heute um 8:30 Uhr',
  `holiday` null; Sat 2026-10-31 19:00 (1.11. is a Sunday) → no notice;
  tomorrow with special hours → no notice; tomorrow with closure → no notice;
  without the setting the ported check stays 'öffnet am Montag um 13:00 Uhr'.
- English (`I18n::setLanguage('en')`): Mon 14:00 'Open now' / 'open until 18:00';
  Sunday 11:00 café 'Closed today' / 'opens on Tuesday at 9:00';
  closure range 'Closed for holidays' / 'back on 11 Jul'; holiday 'Public holiday today';
  Silvester 'Silvester: closed'; a date ≥ 7 days ahead 'opens on 16 Aug at 13:00'.
- `closureLabel` 'Praxisurlaub' → range closure text 'Praxisurlaub'.
- Timezone: `at('2026-07-06 12:00', 'UTC')` → shop open (14:00 Berlin);
  settings timezone 'America/New_York' → `timezone()->getName()` and a status
  computed in New York time for a Berlin `$now`.
- `dayIndex`: 'Montag' 0, 'monday' 0, 'SONNTAG' 6, '7' 6, '0' null, 'Mon' null, '' null;
  a row with `'tag' => 'Tuesday'` is read.
- `parseTime`: '08:00:00' 480, '8:05' 485, '24:00' null, '8:5' null, '' null, 'abc' null.
- `lookahead` 5 with a 10-day closure → range detail ''.
- Closure per area: row `bereich` 'cafe' closes the café, not the shop;
  empty `bereich` closes both; `closures()[0]['area']` 'cafe'.
- Hoffest (special hours on 3.10.2026, BY): `upcomingHolidays(at('2026-09-27'), 60)` === [].
- `week()` has 7 entries; `hasHolidays()` true/false; constructor sorts blocks.

### 9.2 tests/HolidaysTest.php (task `holidays`)

- Easter Sunday: the 12 years of kirby-starter's FeiertageTest plus
  1818-03-22, 2285-03-22 (earliest), 1943-04-25, 2038-04-25 (latest);
  `easterSunday(2026)->getTimezone()->getName()` 'Europe/Berlin'.
- Bavaria 2026 complete list (ported verbatim incl. names); without
  assumption 12 entries; augsburg → '2026-08-08' 'Augsburger Friedensfest';
  Fronleichnam 2027-05-27.
- **All 16 states, 2026, complete `forYear()` lists (date => German name)
  written out by hand from §3.2's table and an official source** (state
  interior ministry / Wikipedia "Gesetzliche Feiertage in Deutschland") —
  never by printing the implementation's output. Counts must equal §3.2.
- Introduction years: reformation HB 2016 no / 2017 yes / 2018 yes, NI 2016
  no; BY 2017 yes / 2018 no; childrensDay TH 2018 no / 2019 yes; womensDay
  BE 2018 no / 2019 yes, MV 2022 no / 2023 yes; liberationDay BE 2020 yes,
  2021 no, 2025 yes, never in BB.
- Buß- und Bettag: 2026-11-18, 2027-11-17, 2028-11-22, 2029-11-21,
  2030-11-20; only SN.
- Flags: BB 2026 has '2026-04-05' Ostersonntag and '2026-05-24'
  Pfingstsonntag, not with `sundays` false; SN/TH Fronleichnam only with
  `corpusChristi`; `corpusChristi` false in BY changes nothing;
  `assumption` false in SL changes nothing; `augsburg` in BW changes nothing.
- `config()`: every example of §3.2 incl. exact normalised arrays; case and
  'DE-' variants; legacy keys; exceptions (`true`, `42`, no state, 'XX');
  invalid extras skipped AND logged (redirect `ini_set('error_log', $tmpFile)`,
  check the message, restore).
- `forYear()` with extras: '06-24' in 2026 and 2027; '2027-07-02' only 2027;
  extra on '10-03' keeps 'Tag der Deutschen Einheit'.
- `checker()`: '2026-10-03 23:30' → name; '2026-10-02' → null; the same
  checker after `I18n::setLanguage('en')` → 'German Unity Day'.
- `state()`: 'de-nw' 'NW', 'Thüringen' 'TH', 'XX' throws.

### 9.3 tests/GroupingTest.php (task `presentation`)

- gasthaus cases ported: Di–Mi 11–16, Do–Sa 11–22 → consecutive
  `[['days' => [1, 2], 'label' => 'Di – Mi', 'blocks' => [[660, 960]], 'hours' => '11:00 – 16:00', 'note' => null], ['days' => [3, 4, 5], 'label' => 'Do – Sa', 'blocks' => [[660, 1320]], 'hours' => '11:00 – 22:00', 'note' => null]]`;
  gap case (Di, Do, So two blocks) → labels 'Di', 'Do', 'So', hours
  '10:00 – 14:00 · 17:00 – 21:00'; empty plan → [].
- consecutive with `$withNote`: equal hours, different notes split.
- byHours: Mi+Fr 10–18, Sa 9–13 → `[['days' => [5], 'label' => 'Sa', 'hours' => '9–13 Uhr', …], ['days' => [2, 4], 'label' => 'Mi, Fr', 'hours' => '10–18 Uhr', …]]`;
  Mo–Mi + Fr → 'Mo–Mi, Fr'; Mo+Di → 'Mo, Di'; two blocks '10–12:30 und 14–18 Uhr';
  same hours, different notes → two groups (one group with `$withNote` false).
- English: 'Wed, Fri', '10–18', 'Tue – Wed', '10–12:30 and 14–18'.
- shortTime / blocksLabel cases of §3.4 incl. overnight.

### 9.4 tests/PresenterTest.php (task `presentation`)

Muster plan (Mo 13–18, Di–Fr 8:30–18, Sa 8–18) with BY:

- `table($s, at('2026-09-28 10:00'))`: days[0] `today` true, `hours`
  ['13:00 – 18:00'], days[6] `closed` true; `holidays`
  `['label' => 'Feiertage', 'text' => 'geschlossen', 'today' => false]`;
  notes `[['type' => 'holiday', 'from' => '2026-10-03', 'to' => '2026-10-03', 'text' => '3.10. Tag der Deutschen Einheit: geschlossen', 'note' => null]]`.
- at '2026-10-03 10:00': days[5] `today` false, holidays `today` true.
- Without checker: `holidays` null, no holiday note; `holidayRow` false →
  null; `holidayNote` false → no holiday note; `holidayNoteDays` 3 → none.
- Muster special days (Heiligabend 8:30–12 occasion, Silvester, 16.2.2027,
  2.–15.8.2027, 1.3.2027 half-filled) at '2026-12-17': notes =
  holiday '25.12. 1. Weihnachtsfeiertag: geschlossen', special
  '24.12. Heiligabend: 8:30–12:00 Uhr', closedDay '31.12. Silvester: geschlossen'.
- at '2027-02-10': closedDay '16.2.: geschlossen'; at '2027-07-25': closure
  'Betriebsferien 2.8. bis 15.8.2027', with note 'Sommerpause' → `note`.
- special without occasion → 'Sonderöffnung'; English texts of §3.5.
- Overnight row → hours ['18:00 – 2:00']; two blocks → two entries; note.
- `compact()`: default mode grouped; 'consecutive'; notes limited to 1 and
  to leadDays; `closures` 0 → []; unknown mode → 'grouped'.

### 9.5 tests/SnippetsTest.php (task `presentation`, Kirby)

Boot with `noren_site()` / `noren_kirby($tmp, ['noren.active' => true, 'locale' => 'de_DE'])`
and render with `$kirby->snippet('noren/<name>', $data, true)`, always
passing data explicitly (`schedule` built with `Schedule::fromFields(field(…))`,
`units` built by hand in the §3.7 shape, `now`), so this test does not depend
on Locations. Check the exact markup of §3.8: one unit (no label), two units
(labels), `only` filter, `link`; `noren.status` false → ''; `noren.active`
missing → '' for all three; table rows/`data-today`/closed span/note
span/holidays row/notes; compact title/groups/note/closure; schedule not
maintained → ''; HTML in a note is escaped; `noren.language` 'en' → English.
A site's own `site/snippets/noren/table.php` wins over the package snippet.

### 9.6 tests/SchemaOrgTest.php (task `kirby`)

- `time()` cases; `openingHours()` for the Muster plan (6 entries, first
  Monday 13:00–18:00), a two-block day (2 entries), overnight Fri
  (closes '02:00'), Sunday none.
- `specialOpeningHours($s, at('2026-10-15'))` with the Muster special days
  and BY: closures first, all five (no lead time), in start order, Heiligabend
  opens '08:30' closes '12:00', others '00:00'/'00:00'; then holidays on
  open days only, e.g. '2026-12-25' and '2026-12-26' present, '2026-11-01'
  (Sunday) absent. Write the expected holiday list by hand from a calendar.
- Hoffest special on 3.10. → that date appears once (special), not as closed.
- `forSchedule()` keys.

### 9.7 tests/KirbyTest.php (task `kirby`)

Real Kirby via helpers, content written with `noren_page()`:

1. Plugin registered; off by default (`norenSchedule()` null, `norenUnits()`
   [], `norenLocations()` [], `norenHolidays()` []); `'noren.active' => 'yes'`
   stays off; translations `noren.status.openNow` in en and de;
   `noren_option()` exists.
2. Starter shape `1_kontakt/betrieb.txt`: areas laden (name Hofladen,
   kurzname Laden), cafe (Hofcafé), werkstatt (no hours); hours rows incl.
   one legacy `nm_von/nm_bis` row; closures. Check `norenLocation()->id()`,
   `norenAreas()` exact, `norenSchedule('laden')->blocks(0)` vs
   `norenSchedule('cafe')->blocks(0)` (no memo, both call orders), `norenUnits()`
   ids `['kontakt#laden', 'kontakt#cafe']`, labels `['Laden', 'Hofcafé']`.
3. No areas → one unit, id 'kontakt', area null.
4. praxisbeispiel shape: `'noren.template' => 'standort'`, two pages `1_au`,
   `2_beispiel-nord` with `kurzname`, `vm_/nm_` rows and closures in
   `urlaub` (`'noren.fields' => ['closures' => 'urlaub']`) → two locations in
   order, unit labels 'Au', 'Beispiel Nord'; the urlaub closure applies; list
   form `['betrieb', 'standort']`.
5. `noren.locations` as closure (listed only) and as list of ids (unknown id
   skipped).
6. Page methods: a page with `zeitenschluessel: cafe` → café plan,
   `norenHasHours()` true; a page without reference → all rows; a location
   page → own plan.
7. Holidays: `'BY'` → `norenSchedule()->status(at('2026-10-03 10:00'))['text']`
   'Heute Feiertag'; legacy array works; `'XX'` → logged, no holidays;
   `norenHolidays(2026)` 13 entries.
8. Options: timezone, holidayEve, overnightUntil false, closureLabel,
   language (`locale`, `noren.language`).
9. `norenSchema()` shape; `norenStatus()` keys; `norenTable()` /
   `norenCompact()` shapes — these two depend on task `presentation`: wrap in
   try/catch `LogicException` and print `SKIP` (not a failure) while
   Presenter is still a stub.
10. Default data path: `snippet('noren/status')` with real content contains
    'Jetzt' (skip with a printed SKIP while the snippet is still the
    placeholder, i.e. renders ''); a site's own `site/snippets/noren/status.php`
    wins.

### 9.8 tests/BlueprintsTest.php (task `panel`, Kirby)

Site blueprint `site/blueprints/pages/betrieb.yml` with
`extends: noren/pages/location`, a page `kontakt` with areas. Load with
`$kirby->page('kontakt')->blueprint()` and `Kirby\Form\Form::for($page)`;
set the Panel language with `$kirby->setCurrentTranslation('de'); Kirby\Toolkit\I18n::$locale = 'de';`
(then 'en'). Check: tabs `bereiche`, `zeiten` with labels 'Bereiche'/'Zeiten'
(en 'Areas'/'Hours'); page title 'Öffnungszeiten'; fields `bereicheHinweis`,
`bereiche`, `zeitenHinweis`, `zeiten`, `trennerSchliessungen`,
`schliessungenHinweis`, `schliessungen`; structure subfield names exactly as
§6; types (time fields `display` HH:mm, date fields, selects); day options
values Montag…Sonntag with texts Montag…Sonntag / Monday…Sunday; the area
select resolves options laden/cafe from content; **no string starting with
`noren.` anywhere** in the recursive `toArray()` of the form fields in both
languages; every registered key loads on its own in a site blueprint via
`extends:` and a site can override a label after `extends`.

---

## 10. Documentation (owner `docs`)

Modelled on kirby-koe (read its README, CHANGELOG, PRIVACY, DATENSCHUTZ).

- **README.md** (English): what it is (Noren 暖簾), feature list, requirements
  (PHP 8.2–8.4, Kirby 5), install (composer + VCS repository note, manual),
  quick start (config + blueprint `extends` + snippets), **every option of
  §4 with default**, content field contract §6, locations and areas (Beim
  Muster 1×N, praxisbeispiel N×1, `noren.locations` example), snippets with
  variables and markup classes (§3.8), own markup (site/page methods §3.9 with
  return shapes as PHP array examples), blueprints (§7, incl. the query note),
  public holidays (table per state from §3.2, flags, extra days, the
  Easter/Whit Sunday decision, 1995+), edge cases (overnight, special hours
  beat holidays, half-filled rows, empty plan = nothing shown, lead time vs
  schema.org, closure "back on" skipping rest days, eve notice, timezone,
  DST), "Upgrading from site/plugins/betrieb" with §8 (decisions + rename
  table + behaviour changes), privacy pointer, development (composer install,
  `composer test`), license.
- **CHANGELOG.md**: `## [1.0.0] — unreleased`, Keep-a-Changelog sections like
  koe (project rules at the top: a change of the content field contract or
  of default output is major; privacy text changes marked **[privacy text]**),
  "Changed — renamed to English", "Added", "Changed behaviour" (§8 list),
  "Unchanged (data continuity)".
- **PRIVACY.md** (English summary) and **DATENSCHUTZ.md** (German text block,
  "beschreibt die Implementierung, keine Rechtsberatung"): no cookies, no
  storage, no external requests, no visitor data; status computed on the
  server per request; content is business data only; holiday calculation
  local; schema.org output is public business data.

### 5.1 All keys (generated from translations/*.php on 2026-10-10)

| Key | en | de |
|---|---|---|
| `day.1` | Monday | Montag |
| `day.2` | Tuesday | Dienstag |
| `day.3` | Wednesday | Mittwoch |
| `day.4` | Thursday | Donnerstag |
| `day.5` | Friday | Freitag |
| `day.6` | Saturday | Samstag |
| `day.7` | Sunday | Sonntag |
| `dayShort.1` | Mon | Mo |
| `dayShort.2` | Tue | Di |
| `dayShort.3` | Wed | Mi |
| `dayShort.4` | Thu | Do |
| `dayShort.5` | Fri | Fr |
| `dayShort.6` | Sat | Sa |
| `dayShort.7` | Sun | So |
| `format.date` | j M | j.n. |
| `format.dateYear` | j M Y | j.n.Y |
| `status.openNow` | Open now | Jetzt geöffnet |
| `status.closedNow` | Closed now | Jetzt geschlossen |
| `status.closedToday` | Closed today | Heute geschlossen |
| `status.holidayToday` | Public holiday today | Heute Feiertag |
| `status.occasionClosed` | {occasion}: closed | {occasion}: geschlossen |
| `status.allHours` | All opening hours | Alle Öffnungszeiten |
| `detail.until` | open until {time} | noch bis {time} Uhr |
| `detail.untilOccasion` | open until {time} ({occasion}) | noch bis {time} Uhr ({occasion}) |
| `detail.opensToday` | opens today at {time} | öffnet heute um {time} Uhr |
| `detail.opensTomorrow` | opens tomorrow at {time} | öffnet morgen um {time} Uhr |
| `detail.opensWeekday` | opens on {weekday} at {time} | öffnet am {weekday} um {time} Uhr |
| `detail.opensDate` | opens on {date} at {time} | öffnet am {date} um {time} Uhr |
| `detail.backOn` | back on {date} | wieder ab {date} |
| `detail.holidayTomorrow` | tomorrow is a public holiday ({name}) | morgen Feiertag ({name}) |
| `detail.separator` |  ·  |  ·  |
| `closure.label` | Closed for holidays | Betriebsferien |
| `closure.singleDay` | Closed | Geschlossen |
| `special.label` | Special opening hours | Sonderöffnung |
| `table.closed` | closed | geschlossen |
| `table.holidays` | Public holidays | Feiertage |
| `table.range` | {from} – {to} | {from} – {to} |
| `note.holiday` | {date} {name}: closed | {date} {name}: geschlossen |
| `note.special` | {date} {occasion}: {from}–{to} | {date} {occasion}: {from}–{to} Uhr |
| `note.closedDay` | {date}: closed | {date}: geschlossen |
| `note.closedDayOccasion` | {date} {occasion}: closed | {date} {occasion}: geschlossen |
| `note.closure` | {label} {from} to {to} | {label} {from} bis {to} |
| `compact.hours` | {hours} | {hours} Uhr |
| `compact.and` |  and  |  und  |
| `holiday.newYear` | New Year’s Day | Neujahr |
| `holiday.epiphany` | Epiphany | Heilige Drei Könige |
| `holiday.womensDay` | International Women’s Day | Internationaler Frauentag |
| `holiday.goodFriday` | Good Friday | Karfreitag |
| `holiday.easterSunday` | Easter Sunday | Ostersonntag |
| `holiday.easterMonday` | Easter Monday | Ostermontag |
| `holiday.labourDay` | Labour Day | Tag der Arbeit |
| `holiday.liberationDay` | Liberation Day | Tag der Befreiung |
| `holiday.ascension` | Ascension Day | Christi Himmelfahrt |
| `holiday.whitSunday` | Whit Sunday | Pfingstsonntag |
| `holiday.whitMonday` | Whit Monday | Pfingstmontag |
| `holiday.corpusChristi` | Corpus Christi | Fronleichnam |
| `holiday.augsburgPeace` | Augsburg Peace Festival | Augsburger Friedensfest |
| `holiday.assumption` | Assumption Day | Mariä Himmelfahrt |
| `holiday.childrensDay` | World Children’s Day | Weltkindertag |
| `holiday.germanUnity` | German Unity Day | Tag der Deutschen Einheit |
| `holiday.reformation` | Reformation Day | Reformationstag |
| `holiday.allSaints` | All Saints’ Day | Allerheiligen |
| `holiday.repentance` | Day of Repentance and Prayer | Buß- und Bettag |
| `holiday.christmas1` | Christmas Day | 1. Weihnachtsfeiertag |
| `holiday.christmas2` | Second Day of Christmas | 2. Weihnachtsfeiertag |
| `panel.page.title` | Opening hours | Öffnungszeiten |
| `panel.tab.areas` | Areas | Bereiche |
| `panel.tab.hours` | Hours | Zeiten |
| `panel.areas.label` | Areas | Bereiche |
| `panel.areas.info` | Areas are the parts of the business with **their own opening hours** — a shop and a café, for example. With only one set of hours, leave this list empty.<br><br>The **key** is the internal name (lower case, no umlauts, no spaces). It links an area to its hours. If it is changed later, the hours lose their area — then update them in the “Hours” tab as well. | Bereiche sind die Teile des Betriebs mit **eigenen Öffnungszeiten** — etwa Laden und Café. Wer nur eine Öffnungszeit hat, lässt diese Liste leer.<br><br>Der **Schlüssel** ist der interne Name (klein, ohne Umlaute, ohne Leerzeichen). Er verbindet Bereich und Zeiten. Wird er nachträglich geändert, verlieren die Zeiten ihren Bezug — dann bitte auch im Reiter „Zeiten“ nachziehen. |
| `panel.area.key.label` | Key | Schlüssel |
| `panel.area.key.help` | Lower case, no umlauts, no spaces. Example — shop, cafe, workshop | Klein, ohne Umlaute, ohne Leerzeichen. Beispiel — laden, cafe, werkstatt |
| `panel.area.name.label` | Name | Name |
| `panel.area.name.help` | As it should appear on the site. Example — Farm shop | So, wie es auf der Seite stehen soll. Beispiel — Hofladen |
| `panel.area.short.label` | Short name | Kurzname |
| `panel.area.short.help` | For tight spots such as the status band. Empty = the name is used. | Für enge Stellen wie das Statusband. Leer = Name wird verwendet. |
| `panel.hours.label` | Opening hours | Öffnungszeiten |
| `panel.hours.info` | **One row per area and weekday.** A day on which the business is closed gets **no row** — not a row with empty fields.<br><br>Each row has two blocks, for businesses with a lunch break. Open all day? Fill in only the first.<br><br>A block counts only when **both** times are set and the end is after the start. Half-filled rows are ignored — no information is better than wrong information.<br><br>**Past midnight** (Friday 18:00 to 2:00, for example): enter it as Friday’s row with the end in the early morning. The end may reach 6:00 at the latest. | **Eine Zeile je Bereich und Wochentag.** Ein Tag, an dem geschlossen ist, bekommt **keine Zeile** — nicht eine Zeile mit leeren Feldern.<br><br>Es gibt zwei Blöcke je Zeile, für Betriebe mit Mittagspause. Wer durchgehend offen hat, füllt nur den ersten.<br><br>Ein Block zählt nur, wenn **beide** Zeiten gesetzt sind und das Ende nach dem Anfang liegt. Halb ausgefüllte Zeilen werden ignoriert — lieber keine Auskunft als eine falsche.<br><br>**Über Mitternacht** (etwa Freitag 18:00 bis 2:00): als Zeile des Freitags eintragen, mit dem Ende am frühen Morgen. Das Ende darf höchstens bis 6:00 Uhr reichen. |
| `panel.hours.area.label` | Area | Bereich |
| `panel.hours.area.help` | Leave empty if the business has no separate areas. | Leer lassen, wenn der Betrieb keine getrennten Bereiche hat. |
| `panel.hours.day.label` | Weekday | Wochentag |
| `panel.hours.from1.label` | from | von |
| `panel.hours.to1.label` | to | bis |
| `panel.hours.from2.label` | from (2nd block) | von (2. Block) |
| `panel.hours.to2.label` | to (2nd block) | bis (2. Block) |
| `panel.hours.note.label` | Note | Hinweis |
| `panel.hours.note.help` | A short addition, such as “hot food until 2 pm”. | Kurzer Zusatz, etwa „warme Küche bis 14 Uhr“. |
| `panel.closures.label` | Closures and special days | Schließungen und Sondertage |
| `panel.closures.info` | **Public holidays can be calculated automatically** (all German states) — set `noren.holidays` in your `config.php`. Without that option, public holidays are NOT calculated. Always enter here what the calculation does not cover or what is closed in addition: company holidays, bridge days, Christmas Eve, New Year’s Eve, carnival and the like. Feel free to enter them early: the site shows the notice only 14 days before. | **Gesetzliche Feiertage lassen sich automatisch berechnen** (alle Bundesländer) — dafür `noren.holidays` in der eigenen `config.php` setzen. Ohne diese Option werden Feiertage NICHT automatisch berechnet. Hier immer eintragen, was die Berechnung nicht abdeckt oder was zusätzlich zu ist: Betriebsferien, Brückentage, Heiligabend, Silvester, Faschingsdienstag o. Ä. Ruhig früh eintragen: Auf der Website erscheint der Hinweis erst 14 Tage vorher. |
| `panel.closure.occasion.label` | Occasion | Anlass |
| `panel.closure.occasion.placeholder` | e.g. New Year’s Eve | z. B. Silvester |
| `panel.closure.occasion.help` | Shown on the site (“New Year’s Eve: closed”). Leave empty for company holidays. | Erscheint auf der Website („Silvester geschlossen“). Leer bei Betriebsferien. |
| `panel.closure.from.label` | from | von |
| `panel.closure.to.label` | to | bis |
| `panel.closure.to.help` | The last closed day. The site works out “back on” itself. | Der letzte geschlossene Tag. „Wieder ab“ rechnet die Seite selbst. |
| `panel.closure.area.label` | Only this area | Nur dieser Bereich |
| `panel.closure.area.help` | Empty = applies to all areas. That is the normal case. | Leer = betrifft alle Bereiche. Das ist der Normalfall. |
| `panel.closure.note.label` | Note | Hinweis |
| `panel.closure.note.help` | Replaces the automatic “back on …”. | Ersetzt das automatische „wieder ab …“. |
| `panel.closure.opens.label` | Only short hours, from | Nur verkürzt geöffnet von |
| `panel.closure.opens.help` | Empty = closed all day. Fill in both times, e.g. Christmas Eve 8:30–12:00. | Leer = ganz geschlossen. Beide Zeiten ausfüllen, etwa Heiligabend 8:30–12:00. |
| `panel.closure.closes.label` | to | bis |
