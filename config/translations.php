<?php

use Sayamaapps\Noren\I18n;

// Every string, also usable as t('sayamaapps.noren.<key>') and as blueprint labels.
// The 1.x keys t('noren.<key>') stay registered until 2.0. Noren reads its own
// tables (I18n), so a missing Kirby translation never changes a text.
$translations = [];
foreach (I18n::LANGUAGES as $language) {
	foreach (I18n::table($language) as $key => $text) {
		$translations[$language]['sayamaapps.noren.' . $key] = $text;
		$translations[$language]['noren.' . $key] = $text;
	}
}

return $translations;
