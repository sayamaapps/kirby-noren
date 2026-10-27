<?php

namespace Sayamaapps\Noren;

/**
 * Day grouping for compact output (SPEC.md §3.4). Kirby-free.
 *
 * Group shape (both functions):
 *   ['days' => [1, 2], 'label' => 'Di – Mi', 'blocks' => [[660, 960]],
 *    'hours' => '11:00 – 16:00', 'note' => null]
 * Day indices: 0 = Monday … 6 = Sunday. Closed days never form a group.
 *
 * @package   Kirby Noren
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-noren
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Grouping
{
	/** [[660, 960], [1020, 1260]] → "11:00 – 16:00 · 17:00 – 21:00" (Schedule::formatTime). */
	public static function blocksLabel(array $blocks): string
	{
		return implode(' · ', array_map(
			static fn (array $block) => Schedule::formatTime($block[0]) . ' – ' . Schedule::formatTime($block[1]),
			$blocks,
		));
	}

	/**
	 * Days with identical blocks ANYWHERE in the week. With $withNote (default) the note must match too and the
	 * group carries it. Sorted by the start of the first block, ties in week
	 * order. label = listLabel(), hours = shortHoursLabel().
	 *
	 * @return list<array{days: list<int>, label: string, blocks: list<array{0:int,1:int}>, hours: string, note: ?string}>
	 */
	public static function byHours(Schedule $schedule, bool $withNote = true): array
	{
		$found = [];
		for ($day = 0; $day < 7; $day++) {
			$blocks = $schedule->blocks($day);
			if ($blocks === []) {
				continue;
			}
			$note = $withNote ? $schedule->note($day) : null;
			$key = json_encode([$blocks, $note]);
			$found[$key] ??= ['days' => [], 'blocks' => $blocks, 'note' => $note];
			$found[$key]['days'][] = $day;
		}

		// Earliest opening first; equal starts keep the week order of their first day.
		$found = array_values($found);
		usort($found, static fn (array $a, array $b) => [$a['blocks'][0][0], $a['days'][0]] <=> [$b['blocks'][0][0], $b['days'][0]]);

		$groups = [];
		foreach ($found as $entry) {
			$groups[] = self::group($entry['days'], $entry['blocks'], $entry['note'], self::listLabel($entry['days']), true);
		}
		return $groups;
	}

	/**
	 * CONSECUTIVE days with identical blocks.
	 * With $withNote also an identical note is required, and the group
	 * carries it; without, `note` is always null.
	 * label = rangeLabel(), hours = blocksLabel(). In week order.
	 *
	 * @return list<array{days: list<int>, label: string, blocks: list<array{0:int,1:int}>, hours: string, note: ?string}>
	 */
	public static function consecutive(Schedule $schedule, bool $withNote = false): array
	{
		/** @var list<array{days: list<int>, blocks: list<array{0:int,1:int}>, note: string|null}> $runs */
		$runs = [];
		$current = null;
		for ($day = 0; $day < 7; $day++) {
			$blocks = $schedule->blocks($day);
			if ($blocks === []) {
				// A closed day splits the groups and forms none.
				$current = null;
				continue;
			}
			$note = $schedule->note($day);
			if ($current !== null &&
				$blocks === $current['blocks'] &&
				(!$withNote || $note === $current['note'])
			) {
				$runs[$current['index']]['days'][] = $day;
				continue;
			}
			$runs[] = ['days' => [$day], 'blocks' => $blocks, 'note' => $note];
			$current = ['index' => array_key_last($runs), 'blocks' => $blocks, 'note' => $note];
		}

		$groups = [];
		foreach ($runs as $run) {
			$groups[] = self::group(
				$run['days'],
				$run['blocks'],
				$withNote ? $run['note'] : null,
				self::rangeLabel($run['days']),
			);
		}
		return $groups;
	}

	/**
	 * @param list<int> $days
	 * @param list<array{0:int,1:int}> $blocks
	 * @return array{days: list<int>, label: string, blocks: list<array{0:int,1:int}>, hours: string, note: ?string}
	 */
	private static function group(array $days, array $blocks, string|null $note, string $label, bool $shortHours = false): array
	{
		return [
			'days'   => $days,
			'label'  => $label,
			'blocks' => $blocks,
			'hours'  => $shortHours ? self::shortHoursLabel($blocks) : self::blocksLabel($blocks),
			'note'   => $note,
		];
	}

	/**
	 * Runs of 3+ days as "Mo–Mi" (en dash, no spaces), 2 adjacent days as
	 * "Mo, Di", single days by name, runs joined with ", ":
	 * [2, 4] → "Mi, Fr"; [0, 1, 2, 4] → "Mo–Mi, Fr"; [0, 1] → "Mo, Di".
	 */
	public static function listLabel(array $days): string
	{
		sort($days);
		/** @var list<array{int, int}> $runs */
		$runs = [];
		foreach ($days as $day) {
			$last = count($runs) - 1;
			if ($last >= 0 && $day === $runs[$last][1] + 1) {
				$runs[$last][1] = $day;
			} else {
				$runs[] = [$day, $day];
			}
		}

		$parts = [];
		foreach ($runs as [$start, $end]) {
			$parts[] = match (true) {
				$start === $end => I18n::dayShort($start),
				$end === $start + 1 => I18n::dayShort($start) . ', ' . I18n::dayShort($end),
				default => I18n::dayShort($start) . '–' . I18n::dayShort($end),
			};
		}
		return implode(', ', $parts);
	}

	/** [1, 2] → "Di – Mi", [3] → "Do" (first and last day only; short names, " – "). */
	public static function rangeLabel(array $days): string
	{
		sort($days);
		$first = (int)($days[0] ?? 0);
		$last = (int)end($days);
		if ($first === $last) {
			return I18n::dayShort($first);
		}
		return I18n::dayShort($first) . ' – ' . I18n::dayShort($last);
	}

	/**
	 * [[600, 1080]] → de "10–18 Uhr", en "10–18";
	 * [[600, 750], [840, 1080]] → de "10–12:30 und 14–18 Uhr", en "10–12:30 and 14–18".
	 * Blocks "shortTime(from)–shortTime(to)" joined with t('compact.and'),
	 * wrapped in t('compact.hours', ['hours' => …]).
	 */
	public static function shortHoursLabel(array $blocks): string
	{
		$ranges = array_map(
			static fn (array $block) => self::shortTime($block[0]) . '–' . self::shortTime($block[1]),
			$blocks,
		);
		return I18n::t('compact.hours', ['hours' => implode(I18n::t('compact.and'), $ranges)]);
	}

	/** Full hours without ":00": 600 → "10", 630 → "10:30", 1440 → "24", 1560 → "2", 2880 → "24". */
	public static function shortTime(int $minutes): string
	{
		// Ends after midnight are shown as the clock time of their day (1560 → 2:00),
		// exact midnight as 24 (2880 → 24).
		if ($minutes > Schedule::MINUTES_PER_DAY) {
			$minutes = (($minutes - 1) % Schedule::MINUTES_PER_DAY) + 1;
		}
		return $minutes % 60 === 0 ? (string)intdiv($minutes, 60) : Schedule::formatTime($minutes);
	}
}
