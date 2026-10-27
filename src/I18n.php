<?php

namespace Sayamaapps\Noren;

/**
 * Strings of the plugin, Kirby-free.
 *
 * The tables live in translations/<language>.php and are also registered as
 * Kirby plugin translations under `noren.<key>` (index.php), so site snippets
 * can use t('noren.status.openNow') and blueprints can use `noren.panel.*`
 * keys as labels. Noren itself reads its own tables: a missing Kirby
 * translation must never silently turn a German status English.
 *
 * The language is set once per request (Options::applyLanguage()) and
 * defaults to English. Missing keys fall back to English, then to the key.
 *
 * @package   Kirby Noren
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-noren
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
final class I18n
{
	/** Languages Noren ships. English is the default and the fallback. */
	public const LANGUAGES = ['en', 'de'];

	private static string $language = 'en';

	/** @var array<string, array<string,string>> */
	private static array $tables = [];

	/**
	 * A date in the current language: "3.10." / "3 Oct", with $withYear
	 * "3.10.2026" / "3 Oct 2026". Accepts a DateTimeInterface or "Y-m-d".
	 */
	public static function date(\DateTimeInterface|string $date, bool $withYear = false): string
	{
		if (is_string($date)) {
			$parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', substr(trim($date), 0, 10));
			if ($parsed === false) {
				return $date;
			}
			$date = $parsed;
		}
		return $date->format(self::t($withYear ? 'format.dateYear' : 'format.date'));
	}

	/** Full weekday name, $weekday 0 = Monday … 6 = Sunday. */
	public static function day(int $weekday): string
	{
		return self::t('day.' . ($weekday + 1));
	}

	/** Short weekday name ("Mo" / "Mon"), $weekday 0 = Monday … 6 = Sunday. */
	public static function dayShort(int $weekday): string
	{
		return self::t('dayShort.' . ($weekday + 1));
	}

	public static function language(): string
	{
		return self::$language;
	}

	/**
	 * Whether an occasion already says that the business is closed
	 * ("Geschlossene Gesellschaft", "Closed for a private party"). Such an
	 * occasion stands alone; with the closed template it would read
	 * "Geschlossene Gesellschaft: geschlossen".
	 */
	public static function namesClosed(string $occasion): bool
	{
		return mb_stripos($occasion, self::t('table.closed')) !== false;
	}

	public static function setLanguage(string $language): void
	{
		self::$language = in_array($language, self::LANGUAGES, true) ? $language : 'en';
	}

	/** `{name}` placeholders are replaced with the given values, unescaped. */
	public static function t(string $key, array $values = []): string
	{
		$text = self::table(self::$language)[$key] ?? self::table('en')[$key] ?? $key;
		if ($values === []) {
			return $text;
		}
		$pairs = [];
		foreach ($values as $name => $value) {
			$pairs['{' . $name . '}'] = (string)$value;
		}
		return strtr($text, $pairs);
	}

	/** @return array<string,string> */
	public static function table(string $language): array
	{
		if (!in_array($language, self::LANGUAGES, true)) {
			return [];
		}
		return self::$tables[$language] ??= require dirname(__DIR__) . '/translations/' . $language . '.php';
	}
}
