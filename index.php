<?php

use Kirby\Filesystem\F;

/**
 * Plugin registration for Noren (暖簾, the shop curtain hung out while a shop is
 * open): opening hours for Kirby 5.
 *
 * Kirby loads this file. It registers the class map, the legacy aliases and the
 * definitions in config/. The logic lives in src/. Off by default: without
 * `'sayamaapps.noren.active' => true` (or the older `noren.active`) every site and
 * page method returns null or [] and the snippets print nothing.
 *
 * A separately installed plugin with a similar purpose may stay installed next to
 * it: different plugin id, namespace, method and snippet names.
 */
F::loadClasses(require __DIR__ . '/autoload.php', __DIR__);
require_once __DIR__ . '/legacy.php';

Kirby::plugin(
	name: 'sayamaapps/noren',
	extends: [
		'translations' => require __DIR__ . '/config/translations.php',
		'snippets'     => require __DIR__ . '/config/snippets.php',
		'blueprints'   => require __DIR__ . '/config/blueprints.php',
		'siteMethods'  => require __DIR__ . '/config/site-methods.php',
		'pageMethods'  => require __DIR__ . '/config/page-methods.php',
	],
	root: __DIR__
);
