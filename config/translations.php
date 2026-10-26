<?php

use Sayamaapps\Noren\I18n;

// Every string, also usable as t('noren.<key>') and as blueprint labels. Noren
// reads its own tables (I18n), so a missing Kirby translation never changes a text.
$translations = [];
foreach (I18n::LANGUAGES as $language) {
	foreach (I18n::table($language) as $key => $text) {
		$translations[$language]['noren.' . $key] = $text;
	}
}

return $translations;
