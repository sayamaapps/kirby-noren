<?php

$config = Kirby\PhpCs\Config::create();

// Class heads keep `@package`; the Kirby rule set removes it.
return $config
	->setRules([...$config->getRules(), 'phpdoc_no_package' => false])
	->setFinder(
		PhpCsFixer\Finder::create()
			->exclude('vendor')
			->in(__DIR__)
	);
