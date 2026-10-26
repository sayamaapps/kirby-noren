<?php

/**
 * Class map for Kirby's F::loadClasses() (AUT-2). Keys are lowercase class names,
 * values are paths relative to the plugin root. tests/AutoloadTest.php checks the
 * map against src/.
 */

return [
	'sayamaapps\\noren\\grouping' => 'src/Grouping.php',
	'sayamaapps\\noren\\holidays' => 'src/Holidays.php',
	'sayamaapps\\noren\\i18n' => 'src/I18n.php',
	'sayamaapps\\noren\\locations' => 'src/Locations.php',
	'sayamaapps\\noren\\log' => 'src/Log.php',
	'sayamaapps\\noren\\options' => 'src/Options.php',
	'sayamaapps\\noren\\presenter' => 'src/Presenter.php',
	'sayamaapps\\noren\\schedule' => 'src/Schedule.php',
	'sayamaapps\\noren\\schemaorg' => 'src/SchemaOrg.php',
];
