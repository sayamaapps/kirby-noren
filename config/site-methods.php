<?php

use Kirby\Cms\Page;
use Sayamaapps\Noren\Locations;
use Sayamaapps\Noren\Schedule;

// Site methods. Each closure only delegates; the logic lives in Locations.
return [
	// All location pages (noren.locations, else pages with a template from noren.template).
	'norenLocations' => function (): array {
		return Locations::pages($this->kirby());
	},

	// The first location page, or null.
	'norenLocation' => function (): Page|null {
		return Locations::primary($this->kirby());
	},

	// Areas of the first location: list<['key', 'name', 'short']>.
	'norenAreas' => function (): array {
		return Locations::siteAreas($this->kirby());
	},

	// Schedule of the first location for an area (null = all rows). Null when Noren
	// is off or there is no location page. Deliberately not cached: two areas share
	// one page.
	'norenSchedule' => function (string|null $area = null): Schedule|null {
		return Locations::siteSchedule($this->kirby(), $area);
	},

	// Every (location, area) with maintained hours: the status band's lines.
	// $now: the moment noren.mergeAreas compares the areas at (default now).
	// $merge false: one unit per area even with noren.mergeAreas on.
	'norenUnits' => function (DateTimeImmutable|null $now = null, bool $merge = true): array {
		return Locations::units($this->kirby(), $now, $merge);
	},

	// Status array of the first location (SPEC.md §3.1.3), or null.
	'norenStatus' => function (string|null $area = null): array|null {
		return Locations::status($this->norenSchedule($area));
	},

	// Table view model (SPEC.md §3.5) for own markup, or null.
	'norenTable' => function (string|null $area = null): array|null {
		return Locations::table($this->norenSchedule($area));
	},

	// Compact view model (SPEC.md §3.5) for own markup, or null.
	'norenCompact' => function (string|null $area = null, string|null $mode = null): array|null {
		return Locations::compact($this->norenSchedule($area), $mode);
	},

	// ['openingHoursSpecification' => [...], 'specialOpeningHoursSpecification' => [...]] or null.
	'norenSchema' => function (string|null $area = null): array|null {
		return Locations::schema($this->norenSchedule($area));
	},

	// Public holidays of a year (noren.holidays): date => name. [] when off.
	'norenHolidays' => function (int|null $year = null): array {
		return Locations::holidays($this->kirby(), $year);
	},
];
