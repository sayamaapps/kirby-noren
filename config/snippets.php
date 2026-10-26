<?php

// Plain defaults without CSS. A site with its own markup creates
// site/snippets/noren/<name>.php (Kirby looks there first) or uses the array
// methods (norenStatus, norenTable, norenCompact).
return [
	'noren/status'  => __DIR__ . '/../snippets/noren/status.php',
	'noren/table'   => __DIR__ . '/../snippets/noren/table.php',
	'noren/compact' => __DIR__ . '/../snippets/noren/compact.php',
];
