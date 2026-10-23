<?php

use Kirby\Cms\App;
use Kirby\Cms\Page;

require_once __DIR__ . '/src/I18n.php';
require_once __DIR__ . '/src/Log.php';
require_once __DIR__ . '/src/Options.php';
require_once __DIR__ . '/src/Schedule.php';
require_once __DIR__ . '/src/Holidays.php';
require_once __DIR__ . '/src/Grouping.php';
require_once __DIR__ . '/src/Presenter.php';
require_once __DIR__ . '/src/SchemaOrg.php';
require_once __DIR__ . '/src/Locations.php';

use Noren\I18n;
use Noren\Locations;
use Noren\Options;
use Noren\Schedule;

/**
 * Noren (暖簾 — the shop curtain hung out when a shop is open): opening hours
 * for Kirby 5. Off by default: without `'noren.active' => true` every site and
 * page method returns null or [] and the snippets print nothing.
 *
 * All logic lives in src/Locations.php and the Kirby-free classes.
 *
 * Defaults are passed as fallback values directly at each option call, NOT
 * in an 'options' block (Kirby would file them under sayamaapps.noren.*
 * otherwise).
 *
 * A separately installed plugin with a similar purpose may stay installed
 * next to it: different plugin id, namespace, method and snippet names, so
 * both load without conflict. Noren registers no aliases for other method
 * names (SPEC.md §8).
 */

/** Reads `noren.<key>`. Also meant for site snippets with their own markup. */
function noren_option(string $key, mixed $default = null): mixed
{
    return Options::get(App::instance(), $key, $default);
}

$norenTranslations = [];
foreach (I18n::LANGUAGES as $norenLanguage) {
    foreach (I18n::table($norenLanguage) as $norenKey => $norenText) {
        $norenTranslations[$norenLanguage]['noren.' . $norenKey] = $norenText;
    }
}
unset($norenLanguage, $norenKey, $norenText);

App::plugin('sayamaapps/noren', [
    // All strings, also usable as t('noren.<key>') and as blueprint labels.
    'translations' => $norenTranslations,

    /**
     * Plain defaults without CSS. A site with its own markup creates
     * site/snippets/noren/<name>.php — Kirby looks there first — or uses
     * the array methods below (norenStatus/norenTable/norenCompact).
     */
    'snippets' => [
        'noren/status'  => __DIR__ . '/snippets/noren/status.php',
        'noren/table'   => __DIR__ . '/snippets/noren/table.php',
        'noren/compact' => __DIR__ . '/snippets/noren/compact.php',
    ],

    /**
     * Blueprint pieces for `extends:` in a site's own blueprints
     * (SPEC.md §7), plus a complete example page blueprint.
     */
    'blueprints' => [
        'noren/fields/areas'          => __DIR__ . '/blueprints/fields/areas.yml',
        'noren/fields/areas-info'     => __DIR__ . '/blueprints/fields/areas-info.yml',
        'noren/fields/hours'          => __DIR__ . '/blueprints/fields/hours.yml',
        'noren/fields/hours-info'     => __DIR__ . '/blueprints/fields/hours-info.yml',
        'noren/fields/closures'       => __DIR__ . '/blueprints/fields/closures.yml',
        'noren/fields/closures-info'  => __DIR__ . '/blueprints/fields/closures-info.yml',
        'noren/tabs/areas'            => __DIR__ . '/blueprints/tabs/areas.yml',
        'noren/tabs/hours'            => __DIR__ . '/blueprints/tabs/hours.yml',
        'noren/pages/location'        => __DIR__ . '/blueprints/pages/location.yml',
    ],

    'siteMethods' => [
        /** All location pages (noren.locations, else pages with a template from noren.template). */
        'norenLocations' => function (): array {
            return Locations::pages($this->kirby());
        },

        /** The first location page, or null. */
        'norenLocation' => function (): ?Page {
            return Locations::primary($this->kirby());
        },

        /** Areas of the first location: list<['key', 'name', 'short']>. */
        'norenAreas' => function (): array {
            $location = Locations::primary($this->kirby());
            return $location !== null ? Locations::areas($location) : [];
        },

        /**
         * Schedule of the first location for an area (null = all rows).
         * null when Noren is off or there is no location page.
         * DELIBERATELY NOT CACHED — two areas live on the same page.
         */
        'norenSchedule' => function (?string $area = null): ?Schedule {
            $location = Locations::primary($this->kirby());
            return $location !== null ? Locations::schedule($location, $area) : null;
        },

        /** Every (location, area) with maintained hours — the status band's lines. */
        'norenUnits' => function (): array {
            return Locations::units($this->kirby());
        },

        /** Status array of the first location (SPEC.md §3.1.3), or null (no location, Noren off, or no weekly plan). */
        'norenStatus' => function (?string $area = null): ?array {
            return Locations::status($this->norenSchedule($area));
        },

        /** Table view model (SPEC.md §3.5) for own markup, or null (no plan, see norenStatus()). */
        'norenTable' => function (?string $area = null): ?array {
            return Locations::table($this->norenSchedule($area));
        },

        /** Compact view model (SPEC.md §3.5) for own markup, or null. */
        'norenCompact' => function (?string $area = null, ?string $mode = null): ?array {
            return Locations::compact($this->norenSchedule($area), $mode);
        },

        /** ['openingHoursSpecification' => […], 'specialOpeningHoursSpecification' => […]] or null. */
        'norenSchema' => function (?string $area = null): ?array {
            return Locations::schema($this->norenSchedule($area));
        },

        /** Public holidays of a year (noren.holidays): date => name. [] when off. */
        'norenHolidays' => function (?int $year = null): array {
            return Locations::holidays($this->kirby(), $year);
        },
    ],

    'pageMethods' => [
        /**
         * A location page: its own schedule. Any other page: the first
         * location's schedule for $area, else for the area named in this
         * page's `zeitenschluessel` field (noren.fields.areaRef).
         */
        'norenSchedule' => function (?string $area = null): ?Schedule {
            return Locations::scheduleFor($this, $area);
        },

        /** Areas of this location page, or of the first location. */
        'norenAreas' => function (): array {
            if (Locations::isLocation($this)) {
                return Locations::areas($this);
            }
            $location = Locations::primary($this->kirby());
            return $location !== null ? Locations::areas($location) : [];
        },

        /** Does this page have (or point to) maintained hours? */
        'norenHasHours' => function (?string $area = null): bool {
            return Locations::scheduleFor($this, $area)?->maintained() === true;
        },

        'norenStatus' => function (?string $area = null): ?array {
            return Locations::status(Locations::scheduleFor($this, $area));
        },

        'norenTable' => function (?string $area = null): ?array {
            return Locations::table(Locations::scheduleFor($this, $area));
        },

        'norenCompact' => function (?string $area = null, ?string $mode = null): ?array {
            return Locations::compact(Locations::scheduleFor($this, $area), $mode);
        },

        'norenSchema' => function (?string $area = null): ?array {
            return Locations::schema(Locations::scheduleFor($this, $area));
        },
    ],
]);
