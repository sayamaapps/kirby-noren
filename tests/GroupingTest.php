<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Grouping (SPEC.md §3.4, §9.3): consecutive (restaurant rule), byHours
 * (bike-shop rule), labels, times. Kirby-free.
 */
require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__) . '/src/I18n.php';
use Sayamaapps\Noren\Grouping;
use Sayamaapps\Noren\I18n;
use Sayamaapps\Noren\Schedule;

/** A plan from content rows, the way the Panel stores them. */
function plan(array $rows): Schedule
{
	return Schedule::fromFields(field($rows), null, null, null, null, []);
}

/** A plan with a note per row, built with the constructor. */
function notedPlan(array $days): Schedule
{
	$week = [];
	foreach ($days as $day => $entry) {
		$week[$day] = ['blocks' => $entry[0], 'note' => $entry[1] ?? null];
	}
	return new Schedule($week);
}

#[CoversClass(Sayamaapps\Noren\Grouping::class)]
final class GroupingTest extends TestCase
{
	public function testSuite(): void
	{
		$de = static fn () => I18n::setLanguage('de');
		$en = static fn () => I18n::setLanguage('en');

		// --------------------------------------------------------------- consecutive
		$de();

		// restaurant: Di–Mi 11–16, Do–Sa 11–22
		$s = plan([
			['tag' => 'Dienstag', 'von1' => '11:00', 'bis1' => '16:00'],
			['tag' => 'Mittwoch', 'von1' => '11:00', 'bis1' => '16:00'],
			['tag' => 'Donnerstag', 'von1' => '11:00', 'bis1' => '22:00'],
			['tag' => 'Freitag', 'von1' => '11:00', 'bis1' => '22:00'],
			['tag' => 'Samstag', 'von1' => '11:00', 'bis1' => '22:00'],
		]);
		expect([
			['days' => [1, 2], 'label' => 'Di – Mi', 'blocks' => [[660, 960]], 'hours' => '11:00 – 16:00', 'note' => null],
			['days' => [3, 4, 5], 'label' => 'Do – Sa', 'blocks' => [[660, 1320]], 'hours' => '11:00 – 22:00', 'note' => null],
		], Grouping::consecutive($s), 'consecutive: Di–Mi 11–16 and Do–Sa 11–22');

		// Gap: Di, Do, So with two blocks each — three separate groups
		$s = plan([
			['tag' => 'Dienstag', 'von1' => '10:00', 'bis1' => '14:00', 'von2' => '17:00', 'bis2' => '21:00'],
			['tag' => 'Donnerstag', 'von1' => '10:00', 'bis1' => '14:00', 'von2' => '17:00', 'bis2' => '21:00'],
			['tag' => 'Sonntag', 'von1' => '10:00', 'bis1' => '14:00', 'von2' => '17:00', 'bis2' => '21:00'],
		]);
		$groups = Grouping::consecutive($s);
		expect(['Di', 'Do', 'So'], array_column($groups, 'label'), 'consecutive gap: three single-day groups');
		expect(['10:00 – 14:00 · 17:00 – 21:00', '10:00 – 14:00 · 17:00 – 21:00', '10:00 – 14:00 · 17:00 – 21:00'], array_column($groups, 'hours'), 'consecutive gap: two blocks joined with " · "');
		expect([[1], [3], [6]], array_column($groups, 'days'), 'consecutive gap: day indices');

		expect([], Grouping::consecutive(plan([])), 'consecutive: empty plan gives []');

		// $withNote: equal hours, different notes split; the group carries the shared note
		$s = notedPlan([
			0 => [[[600, 1080]], 'Hinweis A'],
			1 => [[[600, 1080]], 'Hinweis B'],
			2 => [[[600, 1080]], 'Hinweis B'],
		]);
		expect([
			['days' => [0, 1, 2], 'label' => 'Mo – Mi', 'blocks' => [[600, 1080]], 'hours' => '10:00 – 18:00', 'note' => null],
		], Grouping::consecutive($s), 'consecutive without withNote: one group, note null');
		expect([
			['days' => [0], 'label' => 'Mo', 'blocks' => [[600, 1080]], 'hours' => '10:00 – 18:00', 'note' => 'Hinweis A'],
			['days' => [1, 2], 'label' => 'Di – Mi', 'blocks' => [[600, 1080]], 'hours' => '10:00 – 18:00', 'note' => 'Hinweis B'],
		], Grouping::consecutive($s, true), 'consecutive withNote: different notes split, group carries its note');

		// ------------------------------------------------------------------- byHours
		// Mi+Fr 10–18, Sa 9–13 → Sa first (earlier start)
		$s = plan([
			['tag' => 'Mittwoch', 'von1' => '10:00', 'bis1' => '18:00'],
			['tag' => 'Freitag', 'von1' => '10:00', 'bis1' => '18:00'],
			['tag' => 'Samstag', 'von1' => '09:00', 'bis1' => '13:00'],
		]);
		expect([
			['days' => [5], 'label' => 'Sa', 'blocks' => [[540, 780]], 'hours' => '9–13 Uhr', 'note' => null],
			['days' => [2, 4], 'label' => 'Mi, Fr', 'blocks' => [[600, 1080]], 'hours' => '10–18 Uhr', 'note' => null],
		], Grouping::byHours($s), 'byHours: Sa 9–13 before Mi, Fr 10–18');

		// Mo–Mi + Fr with equal hours: one group
		$s = plan([
			['tag' => 'Montag', 'von1' => '10:00', 'bis1' => '18:00'],
			['tag' => 'Dienstag', 'von1' => '10:00', 'bis1' => '18:00'],
			['tag' => 'Mittwoch', 'von1' => '10:00', 'bis1' => '18:00'],
			['tag' => 'Freitag', 'von1' => '10:00', 'bis1' => '18:00'],
		]);
		$groups = Grouping::byHours($s);
		expect('Mo–Mi, Fr', $groups[0]['label'], 'byHours: Mo–Mi, Fr');
		expect([0, 1, 2, 4], $groups[0]['days'], 'byHours: days of Mo–Mi, Fr');

		// Mo + Di with equal hours
		$s = plan([
			['tag' => 'Montag', 'von1' => '10:00', 'bis1' => '18:00'],
			['tag' => 'Dienstag', 'von1' => '10:00', 'bis1' => '18:00'],
		]);
		expect('Mo, Di', Grouping::byHours($s)[0]['label'], 'byHours: Mo, Di');

		// Two blocks: "10–12:30 und 14–18 Uhr"
		$s = plan([
			['tag' => 'Montag', 'von1' => '10:00', 'bis1' => '12:30', 'von2' => '14:00', 'bis2' => '18:00'],
		]);
		$groups = Grouping::byHours($s);
		expect('10–12:30 und 14–18 Uhr', $groups[0]['hours'], 'byHours: two blocks in German');
		expect([[600, 750], [840, 1080]], $groups[0]['blocks'], 'byHours: blocks kept as minutes');

		// Same hours, different notes: two groups; without withNote one group
		$s = notedPlan([
			0 => [[[600, 1080]], 'A'],
			1 => [[[600, 1080]], 'B'],
		]);
		$groups = Grouping::byHours($s);
		expect(2, count($groups), 'byHours withNote (default): different notes give two groups');
		expect(['A', 'B'], array_column($groups, 'note'), 'byHours withNote: each group carries its note');
		$groups = Grouping::byHours($s, false);
		expect(1, count($groups), 'byHours without withNote: one group');
		expect(null, $groups[0]['note'], 'byHours without withNote: note null');
		expect('Mo, Di', $groups[0]['label'], 'byHours without withNote: label of both days');

		// Empty plan
		expect([], Grouping::byHours(plan([])), 'byHours: empty plan gives []');

		// ------------------------------------------------------------- English
		$en();

		$s = plan([
			['tag' => 'Wednesday', 'von1' => '10:00', 'bis1' => '18:00'],
			['tag' => 'Friday', 'von1' => '10:00', 'bis1' => '18:00'],
		]);
		$groups = Grouping::byHours($s);
		expect('Wed, Fri', $groups[0]['label'], 'en byHours: Wed, Fri');
		expect('10–18', $groups[0]['hours'], 'en byHours: 10–18 (no "Uhr")');

		$s = plan([
			['tag' => 'Tuesday', 'von1' => '11:00', 'bis1' => '16:00'],
			['tag' => 'Wednesday', 'von1' => '11:00', 'bis1' => '16:00'],
		]);
		expect('Tue – Wed', Grouping::consecutive($s)[0]['label'], 'en consecutive: Tue – Wed');

		$s = plan([
			['tag' => 'Monday', 'von1' => '10:00', 'bis1' => '12:30', 'von2' => '14:00', 'bis2' => '18:00'],
		]);
		expect('10–12:30 and 14–18', Grouping::byHours($s)[0]['hours'], 'en byHours: two blocks "and"');
		expect('11:00 – 16:00 · 17:00 – 21:00', Grouping::blocksLabel([[660, 960], [1020, 1260]]), 'en blocksLabel');

		$de();

		// ------------------------------------------------------------- labels
		expect('Di – Mi', Grouping::rangeLabel([1, 2]), 'rangeLabel: Di – Mi');
		expect('Do', Grouping::rangeLabel([3]), 'rangeLabel: single day Do');
		expect('Mo – Fr', Grouping::rangeLabel([0, 1, 2, 3, 4]), 'rangeLabel: first and last only');
		expect(I18n::dayShort(0), Grouping::rangeLabel([]), 'rangeLabel: no days gives the 1.x value, without a warning');

		expect('Mo–Mi, Fr', Grouping::listLabel([0, 1, 2, 4]), 'listLabel: Mo–Mi, Fr');
		expect('Mi, Fr', Grouping::listLabel([2, 4]), 'listLabel: Mi, Fr');
		expect('Mo, Di', Grouping::listLabel([0, 1]), 'listLabel: two adjacent days Mo, Di');
		expect('Fr', Grouping::listLabel([4]), 'listLabel: single Fr');

		expect('11:00 – 16:00 · 17:00 – 21:00', Grouping::blocksLabel([[660, 960], [1020, 1260]]), 'blocksLabel: two blocks');
		expect('18:00 – 2:00', Grouping::blocksLabel([[1080, 1560]]), 'blocksLabel: overnight shows 2:00');
		expect('10–18 Uhr', Grouping::shortHoursLabel([[600, 1080]]), 'shortHoursLabel: 10–18 Uhr');

		expect('10', Grouping::shortTime(600), 'shortTime 600 → 10');
		expect('10:30', Grouping::shortTime(630), 'shortTime 630 → 10:30');
		expect('24', Grouping::shortTime(1440), 'shortTime 1440 → 24');
		expect('2', Grouping::shortTime(1560), 'shortTime 1560 → 2');
		expect('24', Grouping::shortTime(2880), 'shortTime 2880 → 24');
		expect('18', Grouping::shortTime(2520), 'shortTime 2520 → 18');
		expect('0–24 Uhr', Grouping::shortHoursLabel([[0, 1440]]), 'shortHoursLabel: a full day is 0–24 Uhr');

		// Overnight block through the content field: Friday 18:00 to 2:00
		$s = plan([['tag' => 'Freitag', 'von1' => '18:00', 'bis1' => '02:00']]);
		$groups = Grouping::consecutive($s);
		expect([[1080, 1560]], $groups[0]['blocks'], 'overnight: block kept as [1080, 1560]');
		expect('18:00 – 2:00', $groups[0]['hours'], 'overnight: consecutive hours');
		expect('18–2 Uhr', Grouping::byHours($s)[0]['hours'], 'overnight: byHours short hours');
	}
}
