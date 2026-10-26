<?php

use Kirby\Filesystem\Dir;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Noren\Options;

/**
 * Options are read as sayamaapps.noren.<key>, with the 1.x spelling noren.<key>
 * as fallback (OPT-1). The fallback is reported as a deprecation.
 */
#[CoversClass(Options::class)]
final class OptionsPrefixTest extends TestCase
{
	public function testLegacyFunctionReadsTheNewOptionAndIsDeprecated(): void
	{
		$deprecations = [];
		set_error_handler(function (int $level, string $message) use (&$deprecations): bool {
			$deprecations[] = $message;
			return true;
		}, E_USER_DEPRECATED);

		try {
			$this->withApp(['debug' => true, 'sayamaapps.noren.active' => true], function ($kirby): void {
				$kirby->plugins();
				$this->assertTrue(noren_option('active'), 'noren_option() reads sayamaapps.noren.active');
			});
		} finally {
			restore_error_handler();
		}

		$this->assertSame(['noren_option() is deprecated, use Sayamaapps\\Noren\\Options::get()'], $deprecations);
	}

	public function testNewSpellingSwitchesNorenOn(): void
	{
		$this->withApp(['sayamaapps.noren.active' => true], function ($kirby): void {
			$this->assertTrue(Options::active($kirby), 'sayamaapps.noren.active is read');
		});
	}

	public function testNewSpellingWinsOverOldAndSilencesTheFallback(): void
	{
		$this->withApp(['sayamaapps.noren.active' => false, 'noren.active' => true], function ($kirby): void {
			$this->assertFalse(Options::active($kirby), 'sayamaapps.noren.active wins when both are set');
		});
	}

	public function testNotSetFallsBackToTheDefault(): void
	{
		$this->withApp([], function ($kirby): void {
			$this->assertFalse(Options::active($kirby), 'unset: default false');
			$this->assertSame('Europe/Berlin', Options::timezone($kirby), 'unset: default timezone');
		});
	}

	public function testOldSpellingStillWorksAndIsDeprecated(): void
	{
		$deprecations = [];
		set_error_handler(function (int $level, string $message) use (&$deprecations): bool {
			$deprecations[] = $message;
			return true;
		}, E_USER_DEPRECATED);

		try {
			// Kirby reports deprecations in debug mode only.
			$this->withApp(['debug' => true, 'noren.active' => true], function ($kirby): void {
				$this->assertTrue(Options::active($kirby), 'noren.active is read as fallback');
			});
		} finally {
			restore_error_handler();
		}

		$this->assertSame(['Noren: noren.active is deprecated, use sayamaapps.noren.active'], $deprecations);
	}

	/** Runs $fn with a Kirby app on a throwaway site, removes the site afterwards. */
	private function withApp(array $options, callable $fn): void
	{
		$tmp = noren_site();
		try {
			$fn(noren_kirby($tmp, $options));
		} finally {
			Dir::remove($tmp);
		}
	}
}
