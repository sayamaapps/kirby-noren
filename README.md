# Noren

Opening hours for [Kirby CMS](https://getkirby.com) 5, with an "open now" status and German public holidays.

**Versions:** Kirby 5 · PHP 8.2 to 8.5 · MIT · [Changes](CHANGELOG.md) · [Reference](docs/reference.md)

*Noren* (暖簾) is the curtain hung in a shop's doorway while it is open. On a website, the plugin answers the same question: are you open right now, and if not, when again? It prints unstyled markup and provides the data for your own markup and schema.org.

## Features

- Several blocks per day, including overnight blocks such as Friday 18:00 to 2:00.
- Areas and locations: one plan per shop, café or site.
- Closures and special hours, with an automatic "back on" date.
- German public holidays for all 16 states, calculated locally.
- "Open now" status in the business's time zone, computed on each request.
- Unstyled snippets, plus arrays for your own markup and schema.org JSON-LD.

## Requirements

- Kirby 5 (`getkirby/cms` 5.0 or newer)
- PHP 8.2, 8.3, 8.4 or 8.5

## Installation

Pick one of three ways. The plugin always ends up in `site/plugins/noren/`.

**Composer** (the plugin goes to `vendor/` and is linked into `site/plugins/`):

```sh
composer require sayamaapps/kirby-noren
```

Add `site/plugins/noren/` to `.gitignore` and deploy it like `vendor/`.

**ZIP:** download the release archive from the [releases page](https://github.com/sayamaapps/kirby-noren/releases), unpack it and copy the folder to `site/plugins/noren/`.

**Git submodule:**

```sh
git submodule add https://github.com/sayamaapps/kirby-noren.git site/plugins/noren
```

## Usage

### Setup

Switch Noren on in `site/config/config.php`:

```php
return [
    'sayamaapps.noren.active'   => true,
    'sayamaapps.noren.language' => 'de',   // German texts (optional)
    'sayamaapps.noren.holidays' => 'BY',   // Bavarian public holidays (optional)
];
```

Texts are English unless `sayamaapps.noren.language`, the site language or Kirby's `locale` says otherwise. A German single-language site without a `locale` must set `sayamaapps.noren.language`. Noren keeps its texts in its own tables (`translations/`), registered with Kirby as `noren.*` keys, so `Sayamaapps\Noren\I18n::t()` returns them without a Kirby app too. The class name `I18n` is also used by Kirby (`Kirby\Toolkit\I18n`). The two are unrelated; refer to Noren's class by its full name.

Give the location pages this blueprint, for example `site/blueprints/pages/betrieb.yml`:

```yaml
title: noren.panel.page.title
extends: noren/pages/location
```

Keep the `title` line, because `extends:` does not carry it over. Pages with the template `betrieb` are locations (see `sayamaapps.noren.template`).

### Snippets

Call `snippet('noren/status')` for the "open now" line, `snippet('noren/table')` for the full week and `snippet('noren/compact')` for short lines.

| Snippet | Variables (all optional) |
|---|---|
| `noren/status` | `$only` (a unit, area or location id), `$link` (page with all hours), `$units`, `$now` |
| `noren/table` | `$area` or `$schedule`, `$now` |
| `noren/compact` | `$area` or `$schedule`, `$mode` (`grouped` or `consecutive`), `$title`, `$now` |

To change the markup, create a file with the same name in `site/snippets/noren/`. The markup classes are listed in [docs/reference.md](docs/reference.md#markup-classes).

### Own markup and schema.org

Site and page methods return the data behind the snippets, for example `site()->norenStatus('laden')` or `page()->norenTable()`. When Noren is off or there is no plan, they return `null` or `[]`. All methods and return shapes: [docs/reference.md](docs/reference.md#methods).

```php
<script type="application/ld+json">
<?= json_encode(site()->norenSchema(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
</script>
```

## Content field contract

The German field names are the defaults; rename them with `sayamaapps.noren.fields`. One location with several areas (a shop and a café, say) lists them in `bereiche` and gives each hours row its `bereich`. Several sites are several location pages; `sayamaapps.noren.locations` selects them explicitly.

| Field (option key) | Content |
|---|---|
| `bereiche` (`areas`) | Areas: `schluessel` (required), `name`, `kurzname` |
| `zeiten` (`hours`) | Weekly rows: `bereich`, `tag`, `von1`, `bis1`, `von2`, `bis2`, `hinweis` |
| `schliessungen` (`closures`) | Closures and special days: `von`, `bis` (`Y-m-d`, inclusive), `bereich`, `anlass`, `hinweis`, `oeffnet`, `schliesst` (short hours instead of closed) |
| `zeitenschluessel` (`areaRef`) | On a non-location page: the area whose hours apply |
| `kurzname` (`short`) | On a location page: the short name shown as status label |

- `tag` is `Montag` to `Sonntag`, the English names, or `1` to `7` (Monday = 1). A day without rows is closed.
- Rows for the same weekday add up, and blocks that touch or overlap form one opening. A block needs both times; a half-filled pair is ignored.
- An end after midnight belongs to the start day: Friday 18:00 to 2:00 goes into Friday's row, up to `overnightUntil`. An end of `00:00` means midnight, and `00:00` to `00:00` is open around the clock.
- The legacy columns `vm_von`, `vm_bis`, `nm_von`, `nm_bis` and `nm_hinweis` are read when their new column is empty.

## Configuration

Every option is `sayamaapps.noren.<name>`, written flat: `'sayamaapps.noren.active' => true`. The nested form `'noren' => ['active' => true]` works for the 1.x name only (see below). `null` means "not set". Booleans must be real `true` or `false`. A broken value is logged once and the default is used; with Kirby's `debug` option on, it throws instead.

The 1.x name `noren.<name>` still works as a fallback until 2.0, flat or nested, and is read without a warning. If both names are set, the new one wins. See [Deprecations](#deprecations).

| Option | Default | Description |
|---|---|---|
| `active` | `false` | Master switch. Off: methods return `null` or `[]`, snippets print nothing. |
| `template` | `'betrieb'` | Template of the location pages: a string or a list. |
| `locations` | `null` | Closure or list of page ids for the locations; overrides `template`. |
| `fields` | see [Content field contract](#content-field-contract) | Field names as a map. Give only the keys you change. |
| `timezone` | `'Europe/Berlin'` | Time zone of all date logic. |
| `language` | site language | `en` or `de`. |
| `holidays` | `false` | Public holidays: a state code, or an array with `state` and flags. See [Public holidays](#public-holidays). |
| `holidayEve` | `false` | Adds a notice to the status when tomorrow is a public holiday. |
| `holidayRow` | `true` | "Public holidays" row in the table (needs `holidays`). |
| `holidayNote` | `true` | Note on the next public holiday under the table (needs `holidays`). |
| `holidayNoteDays` | `14` | How many days ahead that note looks. |
| `leadDays` | `14` | Closures and special days show on the site this many days ahead. schema.org always gets all of them. |
| `lookahead` | `90` | How many days the status searches for the next opening. |
| `overnightUntil` | `'06:00'` | Latest end of a block running past midnight, as `'HH:MM'`. `false` turns overnight blocks off. |
| `closureLabel` | `Betriebsferien` / `Closed for holidays` | Name of multi-day closures, for example `'Praxisurlaub'`. Translated by default. |
| `compact` | `'grouped'` | Default mode of `noren/compact`: `grouped` or `consecutive`. |
| `status` | `true` | `false` hides the `noren/status` snippet. |
| `mergeAreas` | `false` | Areas with identical hours become one unit, such as "Hofladen und Hofcafé". `site()->norenUnits(null, false)` still returns one unit per area. |

Example with several options:

```php
return [
    'sayamaapps.noren.active'          => true,
    'sayamaapps.noren.template'        => ['betrieb', 'praxis'],
    'sayamaapps.noren.timezone'        => 'Europe/Berlin',
    'sayamaapps.noren.language'        => 'de',
    'sayamaapps.noren.holidays'        => ['state' => 'BY', 'augsburg' => true],
    'sayamaapps.noren.holidayEve'      => true,
    'sayamaapps.noren.overnightUntil'  => '04:00',
    'sayamaapps.noren.closureLabel'    => 'Praxisurlaub',
    'sayamaapps.noren.mergeAreas'      => true,
];
```

## Deprecations

Deprecated in 1.4, removal planned for 2.0. Nothing changes in 1.x: the old names keep working, without a warning.

| 1.x | Now | Notes |
|---|---|---|
| `noren.<option>` in `config.php` | `sayamaapps.noren.<option>` | Still read as fallback. Rename the keys in your site config. |
| `noren.<name>` translation keys | `sayamaapps.noren.<name>` | Both are registered in 1.4. |
| `noren_option('name', $default)` | `Sayamaapps\Noren\Options::get(kirby(), 'name', $default)` | Global function, kept as a guarded wrapper. |
| `Noren\Schedule`, `Noren\I18n`, … (all classes) | `Sayamaapps\Noren\Schedule`, `Sayamaapps\Noren\I18n`, … | Class aliases. `instanceof` and type hints keep working. |
| `Noren\Log::forget()` | none | Test hook, kept for existing callers. |

In warnings and logs, a broken option is named as the site wrote it: `noren.timezone` for the 1.x name, `sayamaapps.noren.timezone` otherwise. Log searches for `noren.` keep matching.

The site and page methods (`norenStatus()`, `norenTable()` and so on) and the snippet names keep their names. Their return values do not change.

### Planned for 2.0

- Runtime deprecation notices for the 1.x option names and `noren_option()`. In debug mode, Kirby shows them as an error page.
- Invalid options throw `Kirby\Exception\InvalidArgumentException` in debug mode instead of `\InvalidArgumentException`. A `catch (\InvalidArgumentException)` does not catch it.
- Removal of the 1.x option names, `noren_option()`, the `Noren\` class aliases and `Log::forget()`.

## Public holidays

Off by default. With `holidays` set, the holidays of a state are calculated for any year, Easter included. Pass flags in an array, for example `['state' => 'BY', 'augsburg' => true, 'extra' => ['06-24' => 'Stadtfest']]`. The regional days of each state are listed in [docs/reference.md](docs/reference.md#public-holidays-by-state).

| Flag | Default | Applies to |
|---|---|---|
| `assumption` | `true` | Bavaria: Assumption Day, in Catholic municipalities only. Always in Saarland. |
| `augsburg` | `false` | Bavaria: Augsburg Peace Festival, in the city of Augsburg only. |
| `corpusChristi` | `false` | Saxony and Thuringia: Corpus Christi, in some Catholic municipalities. |
| `sundays` | `true` | Brandenburg: Easter Sunday and Whit Sunday, which are statutory holidays there. |

- Nine days apply in every state: New Year's Day, Good Friday, Easter Monday, Labour Day, Ascension Day, Whit Monday, German Unity Day, and both Christmas days.
- `extra` keys are `MM-DD` (every year) or `Y-m-d` (once). A day that is already a statutory holiday keeps its name.

## Privacy

Noren processes no visitor data. It sets no cookies, uses no browser storage and makes no request to a third party, so a site's privacy policy needs no entry for it.

## Maintenance

Tests: `composer install`, then `composer test`. Contributing and the checks: [.github/CONTRIBUTING.md](.github/CONTRIBUTING.md). Issues and pull requests are welcome, without a support promise. Report security problems privately, see [.github/SECURITY.md](.github/SECURITY.md). Changes: [CHANGELOG.md](CHANGELOG.md).

## License

MIT, see [LICENSE](LICENSE).
