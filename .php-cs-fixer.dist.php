<?php

$config = Kirby\PhpCs\Config::create();

// Class heads keep `@package` (STY-9 of the plugin rules); the Kirby rule set removes it.
return $config
	->setRules([...$config->getRules(), 'phpdoc_no_package' => false])
	->setFinder(
		PhpCsFixer\Finder::create()
			->exclude('vendor')
			->in(__DIR__)
	);
