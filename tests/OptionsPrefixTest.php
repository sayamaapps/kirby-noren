<?php

use Kirby\Filesystem\Dir;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Noren\Locations;
use Sayamaapps\Noren\Options;

/**
 * Options are read as sayamaapps.noren.<key>, with the 1.x spelling noren.<key>
 * as fallback. The fallback is read silently (README, Deprecations).
 */
#[CoversClass(Options::class)]
final class OptionsPrefixTest extends TestCase
{
	public function testBrokenOptionIsNamedAsTheSiteSpelledIt(): void
	{
		foreach (['noren.timezone', 'sayamaapps.noren.timezone'] as $setting) {
			$message = null;
			set_error_handler(static fn (): bool => true, E_USER_DEPRECATED);
			try {
				$this->withApp(['debug' => true, $setting => 'Mars/Base'], function ($kirby) use (&$message): void {
					try {
						Options::timezone($kirby);
					} catch (\InvalidArgumentException $e) {
						$message = $e->getMessage();
					}
				});
			} finally {
				restore_error_handler();
			}
			$this->assertIsString($message, $setting . ': the broken value throws in debug mode');
			$this->assertStringContainsString('unknown ' . $setting . ' ', $message, $setting . ': message names the key as set');
		}
	}

	public function testHolidaysErrorNamesTheKeyAsSpelled(): void
	{
		foreach (['noren.holidays', 'sayamaapps.noren.holidays'] as $setting) {
			$message = null;
			$this->withApp(['debug' => true, $setting => ['foo' => 1]], function ($kirby) use (&$message): void {
				try {
					Locations::holidayConfig($kirby);
				} catch (\InvalidArgumentException $e) {
					$message = $e->getMessage();
				}
			});
			$this->assertIsString($message, $setting . ': the broken value throws in debug mode');
			$this->assertStringContainsString($setting . ' needs a', $message, $setting . ': message names the key as set');
		}
	}

	public function testLegacyFunctionReadsTheNewOptionWithoutDeprecation(): void
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

		$this->assertSame([], $deprecations, 'noren_option() raises no runtime deprecation');
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

	public function testOldSpellingStillWorksWithoutDeprecation(): void
	{
		$deprecations = [];
		set_error_handler(function (int $level, string $message) use (&$deprecations): bool {
			$deprecations[] = $message;
			return true;
		}, E_USER_DEPRECATED);

		try {
			// Kirby reports deprecations in debug mode only, so debug is on here.
			$this->withApp(['debug' => true, 'noren.active' => true], function ($kirby): void {
				$this->assertTrue(Options::active($kirby), 'noren.active is read as fallback');
			});
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $deprecations, 'noren.active is read without a runtime deprecation');
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
