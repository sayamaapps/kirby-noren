<?php

namespace Sayamaapps\Noren;

use DateTimeImmutable;

/**
 * Kirby-free view models for the snippets and for own markup (SPEC.md §3.5).
 *
 * The snippets render exactly these arrays; `$site->norenTable()` and
 * `norenCompact()` return them for sites with their own markup.
 * `$now` defaults to now in the schedule's timezone; `$today` is `$now` at 0:00.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-noren
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
final class Presenter
{
	/**
	 * One note per upcoming closure or special day (SPEC.md §3.5); $today is 0:00.
	 *
	 * @return list<array{type: string, from: string, to: string, text: string, note: string|null}>
	 */
	public static function closureNotes(Schedule $schedule, DateTimeImmutable $today, int $leadDays): array
	{
		$notes = [];
		foreach ($schedule->upcomingClosures($today, $leadDays) as $closure) {
			$date = I18n::date($closure['from']);
			if ($closure['hours'] !== null) {
				$type = 'special';
				$text = I18n::t('note.special', [
					'date'     => $closure['from'] === $closure['to']
						? $date
						: I18n::t('note.dateRange', ['from' => $date, 'to' => I18n::date($closure['to'], true)]),
					'occasion' => $closure['occasion'] ?? I18n::t('special.label'),
					'from'     => Schedule::formatTime($closure['hours'][0]),
					'to'       => Schedule::formatTime($closure['hours'][1]),
				]);
			} elseif ($closure['from'] === $closure['to']) {
				$type = 'closedDay';
				$text = match (true) {
					$closure['occasion'] === null => I18n::t('note.closedDay', ['date' => $date]),
					I18n::namesClosed($closure['occasion']) => I18n::t('note.closedDayNamed', ['date' => $date, 'occasion' => $closure['occasion']]),
					default => I18n::t('note.closedDayOccasion', ['date' => $date, 'occasion' => $closure['occasion']]),
				};
			} else {
				$type = 'closure';
				$text = I18n::t('note.closure', [
					'label' => $schedule->label($closure),
					'from'  => $date,
					'to'    => I18n::date($closure['to'], true),
				]);
			}
			$notes[] = [
				'type' => $type,
				'from' => $closure['from'],
				'to'   => $closure['to'],
				'text' => $text,
				'note' => $closure['note'],
			];
		}
		return $notes;
	}

	/**
	 * Compact view for a short line of hours (SPEC.md §3.5).
	 *
	 * Options: mode ('grouped' | 'consecutive', default 'grouped'; anything
	 * else counts as 'grouped'), leadDays (14), closures (max notes, 1).
	 *
	 * @return array{mode: 'consecutive'|'grouped', groups: list<array{days: list<int>, label: string, blocks: list<array{0:int,1:int}>, hours: string, note: string|null}>, notes: list<array{type: string, from: string, to: string, text: string, note: string|null}>}
	 */
	public static function compact(Schedule $schedule, DateTimeImmutable|null $now = null, array $options = []): array
	{
		$now = self::moment($schedule, $now);
		$today = $now->setTime(0, 0);
		$mode = ($options['mode'] ?? 'grouped') === 'consecutive' ? 'consecutive' : 'grouped';
		$groups = $mode === 'consecutive' ? Grouping::consecutive($schedule) : Grouping::byHours($schedule);
		$notes = self::closureNotes($schedule, $today, self::number($options, 'leadDays', 14));

		return [
			'mode'   => $mode,
			'groups' => $groups,
			'notes'  => array_slice($notes, 0, max(0, self::number($options, 'closures', 1))),
		];
	}

	private static function flag(array $options, string $key, bool $default): bool
	{
		return isset($options[$key]) ? (bool)$options[$key] : $default;
	}

	private static function moment(Schedule $schedule, DateTimeImmutable|null $now): DateTimeImmutable
	{
		return $now !== null
			? $now->setTimezone($schedule->timezone())
			: new DateTimeImmutable('now', $schedule->timezone());
	}

	private static function number(array $options, string $key, int $default): int
	{
		return isset($options[$key]) ? (int)$options[$key] : $default;
	}

	/**
	 * Weekly table with holidays row, closures and notes (SPEC.md §3.5).
	 *
	 * Options: holidayRow (true), holidayNote (true), holidayNoteDays (14),
	 * leadDays (14).
	 *
	 * @return array{
	 *   days: list<array{index: int, name: string, short: string, blocks: list<array{0:int,1:int}>, hours: list<string>, closed: bool, note: ?string, today: bool}>,
	 *   holidays: ?array{label: string, text: string, today: bool},
	 *   notes: list<array{type: string, from: string, to: string, text: string, note: ?string}>
	 * }
	 */
	public static function table(Schedule $schedule, DateTimeImmutable|null $now = null, array $options = []): array
	{
		$now = self::moment($schedule, $now);
		$today = $now->setTime(0, 0);
		$weekday = (int)$now->format('N') - 1;

		// A closing holiday today (no special hours) moves "today" from the
		// weekday row to the holidays row: the weekday row would show hours
		// of a day that is closed.
		$holidayToday = $schedule->holidayOn($now) !== null && $schedule->specialOn($now) === null;

		$days = [];
		for ($index = 0; $index < 7; $index++) {
			$blocks = $schedule->blocks($index);
			$days[] = [
				'index'  => $index,
				'name'   => I18n::day($index),
				'short'  => I18n::dayShort($index),
				'blocks' => $blocks,
				'hours'  => array_map(
					static fn (array $block) => I18n::t('table.range', [
						'from' => Schedule::formatTime($block[0]),
						'to'   => Schedule::formatTime($block[1]),
					]),
					$blocks,
				),
				'closed' => $blocks === [],
				'note'   => $schedule->note($index),
				'today'  => $index === $weekday && !$holidayToday,
			];
		}

		$holidays = null;
		if ($schedule->hasHolidays() && self::flag($options, 'holidayRow', true)) {
			$holidays = [
				'label' => I18n::t('table.holidays'),
				'text'  => I18n::t('table.closed'),
				'today' => $holidayToday,
			];
		}

		$notes = [];
		if ($schedule->hasHolidays() && self::flag($options, 'holidayNote', true)) {
			// "N days ahead" counts today as day 0 and includes day N, as leadDays does for closures.
			$upcoming = $schedule->upcomingHolidays($today, self::number($options, 'holidayNoteDays', 14) + 1);
			if ($upcoming !== []) {
				$holiday = $upcoming[0];
				$notes[] = [
					'type' => 'holiday',
					'from' => $holiday['date'],
					'to'   => $holiday['date'],
					'text' => I18n::t('note.holiday', ['date' => I18n::date($holiday['date']), 'name' => $holiday['name']]),
					'note' => null,
				];
			}
		}
		$notes = array_merge($notes, self::closureNotes($schedule, $today, self::number($options, 'leadDays', 14)));

		return ['days' => $days, 'holidays' => $holidays, 'notes' => $notes];
	}
}
