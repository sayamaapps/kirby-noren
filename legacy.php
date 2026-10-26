<?php

/**
 * Names of the 1.x API, kept until 2.0 (README, Deprecations).
 *
 * Loaded once by index.php, after the class map is registered. Class aliases and
 * function wrappers only; no logic here.
 */

class_alias(\Sayamaapps\Noren\Grouping::class, 'Noren\\Grouping');
class_alias(\Sayamaapps\Noren\Holidays::class, 'Noren\\Holidays');
class_alias(\Sayamaapps\Noren\I18n::class, 'Noren\\I18n');
class_alias(\Sayamaapps\Noren\Locations::class, 'Noren\\Locations');
class_alias(\Sayamaapps\Noren\Log::class, 'Noren\\Log');
class_alias(\Sayamaapps\Noren\Options::class, 'Noren\\Options');
class_alias(\Sayamaapps\Noren\Presenter::class, 'Noren\\Presenter');
class_alias(\Sayamaapps\Noren\Schedule::class, 'Noren\\Schedule');
class_alias(\Sayamaapps\Noren\SchemaOrg::class, 'Noren\\SchemaOrg');

if (Kirby\Cms\Helpers::hasOverride('noren_option') === false) {
	/** Reads `noren.<key>`. Deprecated: use Sayamaapps\Noren\Options::get(). */
	function noren_option(string $key, mixed $default = null): mixed
	{
		Kirby\Cms\Helpers::deprecated(
			'noren_option() is deprecated, use Sayamaapps\Noren\Options::get()',
			'noren-function'
		);

		return Sayamaapps\Noren\Options::get(Kirby\Cms\App::instance(), $key, $default);
	}
}
