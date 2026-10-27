<?php

// Options Noren reads, relative to the plugin: `sayamaapps.noren.<key>`.
// Each value is null on purpose. Options::get() gives the default at every read,
// and a default here would hide the 1.x spelling `noren.<key>` (fallback).
return [
	'active'          => null,
	'template'        => null,
	'locations'       => null,
	'fields'          => null,
	'timezone'        => null,
	'language'        => null,
	'holidays'        => null,
	'holidayEve'      => null,
	'holidayRow'      => null,
	'holidayNote'     => null,
	'holidayNoteDays' => null,
	'leadDays'        => null,
	'lookahead'       => null,
	'overnightUntil'  => null,
	'closureLabel'    => null,
	'compact'         => null,
	'status'          => null,
	'mergeAreas'      => null,
];
