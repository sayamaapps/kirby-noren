<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Panel blueprints (SPEC.md §9.8): the location blueprint and its nine
 * registered keys load through Kirby, every label and option text is
 * translated in German and English, and no `noren.` key leaks into a form.
 * Kirby-booting, needs vendor/. Never requires src files.
 *   php tests/BlueprintsTest.php
 */
require_once __DIR__ . '/helpers.php';
use Kirby\Cms\Blueprint;
use Kirby\Form\Form;
use Kirby\Toolkit\I18n;
use Kirby\Filesystem\Dir;
/** The nine keys registered in index.php under 'blueprints'. */
const NOREN_BLUEPRINT_KEYS = [
    'noren/fields/areas',
    'noren/fields/areas-info',
    'noren/fields/hours',
    'noren/fields/hours-info',
    'noren/fields/closures',
    'noren/fields/closures-info',
    'noren/tabs/areas',
    'noren/tabs/hours',
    'noren/pages/location',
];

/**
 * Every string in a nested array (keys and values) that starts with
 * `noren.` — an untranslated key that reached the form. Returns the paths.
 */
function norenLeaks(mixed $value): array
{
    $found = [];
    $walk = function (mixed $node, string $path) use (&$walk, &$found): void {
        if (is_array($node)) {
            foreach ($node as $key => $child) {
                if (is_string($key) && str_starts_with($key, 'noren.')) {
                    $found[] = $path . '.' . $key;
                }
                $walk($child, $path . '.' . $key);
            }
            return;
        }
        if (is_string($node) && str_starts_with($node, 'noren.')) {
            $found[] = $path;
        }
    };
    $walk($value, '');
    return $found;
}

/** Resolves 'zeiten' or 'zeiten.tag' in a Form toArray() result. */
function norenField(array $fields, string $path): array
{
    [$top, $sub] = array_pad(explode('.', $path, 2), 2, null);
    $node = $fields[$top] ?? [];
    return $sub === null ? $node : ($node['fields'][$sub] ?? []);
}

/**
 * The tab labels of a blueprint, keyed by tab name. Blueprint::tabs() returns
 * a list of arrays in Kirby 5 and a Tabs collection of Tab objects in Kirby 6.
 */
function norenTabLabels(object $blueprint): array
{
    $labels = [];
    foreach ($blueprint->tabs() as $tab) {
        if (is_array($tab)) {
            $labels[$tab['name']] = $tab['label'];
        } else {
            $labels[$tab->name()] = $tab->label();
        }
    }
    return $labels;
}

#[CoversNothing]
final class BlueprintsTest extends TestCase
{
    public function testSuite(): void
    {
        // Sanity check of the leak detector itself.
        holds(norenLeaks(['a' => ['b' => 'noren.panel.x']]) === ['.a.b'], 'leak detector finds a nested noren. value');
        holds(norenLeaks(['a' => 'Text']) === [], 'leak detector ignores normal text');

        // The area-page fixture writes the site blueprint exactly as SPEC.md §9.8 shows.
        $tmp = noren_site();
        try {
            file_put_contents($tmp . '/site/blueprints/pages/betrieb.yml', "extends: noren/pages/location\n");

            // A site page that sets its own title and reuses every key on its own,
            // plus a field-level override after `extends` (the override must win).
            file_put_contents($tmp . '/site/blueprints/pages/eigen.yml', <<<'YAML'
        title: noren.panel.page.title
        tabs:
          bereiche:
            extends: noren/tabs/areas
          zeiten:
            extends: noren/tabs/hours
            label: Eigene Zeiten
          eigen:
            label: Eigener Reiter
            fields:
              eigenezeiten:
                extends: noren/fields/hours
                label: Eigene Zeiten
              eigeneschliessungen:
                extends: noren/fields/closures
                label: Eigene Schließungen

        YAML);

            noren_page($tmp, '1_kontakt', 'betrieb', [
                'title' => 'Kontakt',
                'bereiche' => [
                    ['schluessel' => 'laden', 'name' => 'Hofladen'],
                    ['schluessel' => 'cafe', 'name' => 'Hofcafé'],
                ],
            ]);
            noren_page($tmp, '2_eigen', 'eigen', ['title' => 'Eigen']);

            $days = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
            $dayTexts = [
                'de' => $days,
                'en' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            ];

            // [German, English] for every label that must resolve.
            $labels = [
                'bereiche'                => ['Bereiche', 'Areas'],
                'bereiche.schluessel'     => ['Schlüssel', 'Key'],
                'bereiche.name'           => ['Name', 'Name'],
                'bereiche.kurzname'       => ['Kurzname', 'Short name'],
                'zeiten'                  => ['Öffnungszeiten', 'Opening hours'],
                'zeiten.bereich'          => ['Bereich', 'Area'],
                'zeiten.tag'              => ['Wochentag', 'Weekday'],
                'zeiten.von1'             => ['von', 'from'],
                'zeiten.bis1'             => ['bis', 'to'],
                'zeiten.von2'             => ['von (2. Block)', 'from (2nd block)'],
                'zeiten.bis2'             => ['bis (2. Block)', 'to (2nd block)'],
                'zeiten.hinweis'          => ['Hinweis', 'Note'],
                'schliessungen'           => ['Schließungen und Sondertage', 'Closures and special days'],
                'schliessungen.anlass'    => ['Anlass', 'Occasion'],
                'schliessungen.von'       => ['von', 'from'],
                'schliessungen.bis'       => ['bis', 'to'],
                'schliessungen.bereich'   => ['Nur dieser Bereich', 'Only this area'],
                'schliessungen.hinweis'   => ['Hinweis', 'Note'],
                'schliessungen.oeffnet'   => ['Nur verkürzt geöffnet von', 'Only short hours, from'],
                'schliessungen.schliesst' => ['bis', 'to'],
            ];

            // Field name => [type, subfield type table] per SPEC.md §6 and §7.
            $types = [
                'bereiche'                => 'structure',
                'bereiche.schluessel'     => 'text',
                'bereiche.name'           => 'text',
                'bereiche.kurzname'       => 'text',
                'zeiten'                  => 'structure',
                'zeiten.bereich'          => 'select',
                'zeiten.tag'              => 'select',
                'zeiten.von1'             => 'time',
                'zeiten.bis1'             => 'time',
                'zeiten.von2'             => 'time',
                'zeiten.bis2'             => 'time',
                'zeiten.hinweis'          => 'text',
                'schliessungen'           => 'structure',
                'schliessungen.anlass'    => 'text',
                'schliessungen.von'       => 'date',
                'schliessungen.bis'       => 'date',
                'schliessungen.bereich'   => 'select',
                'schliessungen.hinweis'   => 'text',
                'schliessungen.oeffnet'   => 'time',
                'schliessungen.schliesst' => 'time',
            ];

            $languages = ['de' => 0, 'en' => 1];
            $titles = ['de' => 'Öffnungszeiten', 'en' => 'Opening hours'];
            $tabLabels = [
                'de' => ['bereiche' => 'Bereiche', 'zeiten' => 'Zeiten'],
                'en' => ['bereiche' => 'Areas', 'zeiten' => 'Hours'],
            ];
            $expectedInfo = [
                'bereichehinweis'      => ['info', 'info'],
                'zeitenhinweis'        => ['info', 'notice'],
                'schliessungenhinweis' => ['info', 'negative'],
            ];

            foreach ($languages as $language => $index) {
                // Fresh Kirby per language: page and blueprint objects are cached per app.
                $kirby = noren_kirby($tmp, ['noren.active' => true]);
                $kirby->setCurrentTranslation($language);
                I18n::$locale = $language;

                $page = $kirby->page('kontakt');
                $blueprint = $page->blueprint();
                $form = Form::for($page);
                $fields = $form->fields()->toArray();

                // Page title: the location blueprint is extended, but Kirby keeps its
                // own fallback title ("Pages /betrieb") over an extended title, so the
                // site sets the key itself. The key resolves here.
                $eigen = $kirby->page('eigen');
                $eigenForm = Form::for($eigen);
                $eigenFields = $eigenForm->fields()->toArray();
                expect($titles[$language], $eigen->blueprint()->title(), "$language: page title resolves");
                holds(!str_starts_with($blueprint->title(), 'noren.'), "$language: betrieb title is not an untranslated key");

                // Tabs and their labels.
                $tabs = norenTabLabels($blueprint);
                expect($tabLabels[$language], $tabs, "$language: tab labels bereiche/zeiten");

                // Fields of the form, in blueprint order (Kirby lower-cases the names).
                expect(
                    ['bereichehinweis', 'bereiche', 'zeitenhinweis', 'zeiten', 'trennerschliessungen', 'schliessungenhinweis', 'schliessungen'],
                    array_keys($fields),
                    "$language: field names"
                );

                foreach ($expectedInfo as $name => [$type, $theme]) {
                    expect($type, $fields[$name]['type'] ?? null, "$language: $name type");
                    expect($theme, $fields[$name]['theme'] ?? null, "$language: $name theme");
                }
                expect('line', $fields['trennerschliessungen']['type'] ?? null, "$language: separator is a line");

                // Labels.
                foreach ($labels as $path => $pair) {
                    expect($pair[$index], norenField($fields, $path)['label'] ?? null, "$language: label of $path");
                }

                // Types and attributes.
                foreach ($types as $path => $type) {
                    expect($type, norenField($fields, $path)['type'] ?? null, "$language: type of $path");
                }
                foreach (['zeiten.von1', 'zeiten.bis1', 'zeiten.von2', 'zeiten.bis2', 'schliessungen.oeffnet', 'schliessungen.schliesst'] as $path) {
                    expect('HH:mm', norenField($fields, $path)['display'] ?? null, "$language: display of $path");
                }
                foreach (['schliessungen.von', 'schliessungen.bis'] as $path) {
                    expect(true, norenField($fields, $path)['required'] ?? null, "$language: $path required");
                }
                foreach (['bereiche.schluessel', 'bereiche.name', 'zeiten.tag'] as $path) {
                    expect(true, norenField($fields, $path)['required'] ?? null, "$language: $path required");
                }

                // Help texts are translated, not keys.
                $helpFragments = [
                    'bereiche.schluessel' => ['de' => 'Klein, ohne Umlaute', 'en' => 'Lower case, no umlauts'],
                    'zeiten.bereich'      => ['de' => 'Leer lassen, wenn der Betrieb keine getrennten Bereiche hat.', 'en' => 'Leave empty if the business has no separate areas.'],
                    'schliessungen.bis'   => ['de' => 'Der letzte geschlossene Tag', 'en' => 'The last closed day'],
                ];
                foreach ($helpFragments as $path => $texts) {
                    holds(str_contains((string) (norenField($fields, $path)['help'] ?? ''), $texts[$language]), "$language: help of $path is translated");
                }

                // Weekday options: values stay German, texts follow the language.
                $tag = norenField($fields, 'zeiten.tag');
                expect($days, array_column($tag['options'] ?? [], 'value'), "$language: weekday values");
                expect($dayTexts[$language], array_column($tag['options'] ?? [], 'text'), "$language: weekday texts");

                // Area selects resolve the areas of this page.
                foreach (['zeiten.bereich', 'schliessungen.bereich'] as $path) {
                    $options = norenField($fields, $path)['options'] ?? [];
                    expect(['laden', 'cafe'], array_column($options, 'value'), "$language: $path area values");
                    expect(['Hofladen', 'Hofcafé'], array_column($options, 'text'), "$language: $path area texts");
                }

                // No untranslated key anywhere in the form.
                foreach ($fields as $name => $field) {
                    expect([], norenLeaks($field), "$language: no noren. key in field $name");
                }

                // Override page: title key, tab label override, field-level override.
                $eigenTabs = norenTabLabels($eigen->blueprint());
                expect('Eigene Zeiten', $eigenTabs['zeiten'] ?? null, "$language: tab label override wins over extends");
                expect('Eigener Reiter', $eigenTabs['eigen'] ?? null, "$language: own tab label");
                expect('Eigene Zeiten', norenField($eigenFields, 'eigenezeiten')['label'] ?? null, "$language: field label override wins over extends");
                expect('Eigene Schließungen', norenField($eigenFields, 'eigeneschliessungen')['label'] ?? null, "$language: closures override wins");
                expect($dayTexts[$language][0], norenField($eigenFields, 'eigenezeiten.tag')['options'][0]['text'] ?? null, "$language: override keeps translated options");
                expect('HH:mm', norenField($eigenFields, 'eigenezeiten.von1')['display'] ?? null, "$language: override keeps time display");
                expect($tabLabels[$language]['bereiche'], $eigenTabs['bereiche'] ?? null, "$language: area tab reused through extends");
                foreach ($eigenFields as $name => $field) {
                    expect([], norenLeaks($field), "$language: no noren. key in override field $name");
                }

                foreach (NOREN_BLUEPRINT_KEYS as $key) {
                    try {
                        $props = Blueprint::find($key);
                        holds(is_array($props) && $props !== [], "$language: $key loads on its own");
                    } catch (Throwable $e) {
                        holds(false, "$language: $key loads on its own (" . $e->getMessage() . ')');
                    }
                }
            }
        } finally {
            Dir::remove($tmp);
        }
    }
}
