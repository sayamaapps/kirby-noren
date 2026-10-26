<?php

use Sayamaapps\Noren\Locations;
use Sayamaapps\Noren\Schedule;

// Page methods. $this is the page. Each closure only delegates; the logic lives in Locations.
return [
	// A location page: its own schedule. Any other page: the first location's schedule
	// for $area, else for the area named in this page's `zeitenschluessel` field.
	'norenSchedule' => function (string|null $area = null): Schedule|null {
		return Locations::scheduleFor($this, $area);
	},

	// Areas of this location page, or of the first location.
	'norenAreas' => function (): array {
		return Locations::pageAreas($this);
	},

	// Does this page have (or point to) maintained hours?
	'norenHasHours' => function (string|null $area = null): bool {
		return Locations::pageHasHours($this, $area);
	},

	'norenStatus' => function (string|null $area = null): array|null {
		return Locations::status(Locations::scheduleFor($this, $area));
	},

	'norenTable' => function (string|null $area = null): array|null {
		return Locations::table(Locations::scheduleFor($this, $area));
	},

	'norenCompact' => function (string|null $area = null, string|null $mode = null): array|null {
		return Locations::compact(Locations::scheduleFor($this, $area), $mode);
	},

	'norenSchema' => function (string|null $area = null): array|null {
		return Locations::schema(Locations::scheduleFor($this, $area));
	},
];
