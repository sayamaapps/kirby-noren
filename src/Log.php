<?php

namespace Sayamaapps\Noren;

/**
 * The one place where Noren writes to the PHP error log.
 *
 * Every message is written once per process: a status and a table render read
 * the configuration many times, and a broken option must not fill the log with
 * copies (SPEC.md §4).
 *
 * @package   Kirby Noren
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-noren
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Log
{
	/** @var array<string, true> */
	private static array $seen = [];

	/** Writes $message to the error log unless it was written before in this process. */
	public static function error(string $message): void
	{
		if (isset(self::$seen[$message])) {
			return;
		}

		self::$seen[$message] = true;
		error_log($message);
	}

	/**
	 * Forgets the messages already written. Kept for 1.x callers and tests;
	 * removed in 2.0 (README, Deprecations).
	 *
	 * @deprecated 1.4.0, no replacement; removal planned for 2.0
	 */
	public static function forget(): void
	{
		self::$seen = [];
	}
}
