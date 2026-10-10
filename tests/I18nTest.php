<?php
/**
 * Translations: every language has the same keys and placeholders as
 * English, no value is empty, and the helpers follow the language.
 *   php tests/I18nTest.php
 * Owned by the architect (shared file).
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../src/I18n.php';

use Noren\I18n;

$placeholders = function (string $text): array {
    preg_match_all('/\{(\w+)\}/', $text, $m);
    $names = array_unique($m[1]);
    sort($names);
    return $names;
};

$en = I18n::table('en');
holds(count($en) > 100, 'English table loaded');
foreach (I18n::LANGUAGES as $language) {
    $table = I18n::table($language);
    expect([], array_values(array_diff(array_keys($en), array_keys($table))), "$language: no missing keys");
    expect([], array_values(array_diff(array_keys($table), array_keys($en))), "$language: no extra keys");
    foreach ($en as $key => $text) {
        holds(isset($table[$key]) && is_string($table[$key]) && trim($table[$key]) !== '', "$language.$key not empty");
        expect($placeholders($text), $placeholders($table[$key] ?? ''), "$language.$key: same placeholders as English");
    }
}
expect([], I18n::table('fr'), 'unknown language: no table');

// Every key the code relies on exists (SPEC.md §5).
foreach (['status.openNow', 'detail.opensWeekday', 'closure.label', 'note.special', 'compact.and', 'holiday.germanUnity', 'panel.hours.info'] as $key) {
    holds(isset($en[$key]), "key $key exists");
}
for ($i = 1; $i <= 7; $i++) {
    holds(isset($en["day.$i"], $en["dayShort.$i"]), "weekday $i exists");
}

I18n::setLanguage('fr');
expect('en', I18n::language(), 'unknown language falls back to English');
expect('Open now', I18n::t('status.openNow'), 'English default');
expect('no.such.key', I18n::t('no.such.key'), 'missing key returns the key');
expect('opens on Monday at 13:00', I18n::t('detail.opensWeekday', ['weekday' => I18n::day(0), 'time' => '13:00']), 'placeholders replaced');
expect('Sunday', I18n::day(6), 'day(6) is Sunday');
expect('Sun', I18n::dayShort(6), 'English short day');
expect('3 Oct', I18n::date('2026-10-03'), 'English date');
expect('3 Oct 2026', I18n::date('2026-10-03', true), 'English date with year');

I18n::setLanguage('de');
expect('de', I18n::language(), 'German selected');
expect('Jetzt geöffnet', I18n::t('status.openNow'), 'German string');
expect('Montag', I18n::day(0), 'German weekday');
expect('Mi', I18n::dayShort(2), 'German short weekday');
expect('3.10.', I18n::date('2026-10-03'), 'German date');
expect('3.10.2026', I18n::date(new DateTimeImmutable('2026-10-03'), true), 'German date with year, from an object');
expect('nonsense', I18n::date('nonsense'), 'unparsable date string comes back unchanged');
I18n::setLanguage('en');


/* Panel help texts must not hard-code options a site can change (review finding 10). */
foreach (['en', 'de'] as $language) {
    $panel = I18n::table($language);
    holds(!str_contains($panel['panel.hours.info'], '6:00') && !str_contains($panel['panel.hours.info'], '6 Uhr'), "$language: hours help does not hard-code 6:00");
    holds(!str_contains($panel['panel.closures.info'], '14 days') && !str_contains($panel['panel.closures.info'], '14 Tage'), "$language: closures help does not hard-code 14 days");
}

finish('Noren I18n');
