<?php
/**
 * Shared test helpers — owned by the architect, builders only read it.
 *
 *   require_once __DIR__ . '/helpers.php';
 *
 * Loads NO src file: Kirby-free tests require the classes they need
 * themselves (src/I18n.php first), Kirby tests load the plugin through
 * Kirby (noren_site()). That way a class is never declared twice.
 *
 * Every test file: call expect()/holds() as often as needed, then
 * finish('Suite name') at the end — it prints all failures and exits 1, or
 * prints the count and exits 0. A failing check does not stop the file, so
 * one run shows every problem.
 */

$GLOBALS['norenChecks'] = 0;
$GLOBALS['norenFailures'] = [];

/** Strict comparison ($expected === $actual). */
function expect(mixed $expected, mixed $actual, string $what): void
{
    $GLOBALS['norenChecks']++;
    if ($expected !== $actual) {
        $GLOBALS['norenFailures'][] = sprintf(
            "  ✗ %s\n      expected: %s\n      actual:   %s",
            $what,
            var_export($expected, true),
            var_export($actual, true)
        );
    }
}

function holds(bool $ok, string $what): void
{
    $GLOBALS['norenChecks']++;
    if (!$ok) {
        $GLOBALS['norenFailures'][] = '  ✗ ' . $what;
    }
}

/** Only these keys of an array, in this order (for comparing parts of status()). */
function only(array $array, array $keys): array
{
    $result = [];
    foreach ($keys as $key) {
        $result[$key] = $array[$key] ?? null;
    }
    return $result;
}

/** A moment in Europe/Berlin (or another zone): at('2026-07-06 14:00'). */
function at(string $when, string $zone = 'Europe/Berlin'): DateTimeImmutable
{
    return new DateTimeImmutable($when, new DateTimeZone($zone));
}

/** Prints the result and exits: 0 when every check passed, 1 otherwise. */
function finish(string $suite): never
{
    $failures = $GLOBALS['norenFailures'];
    $checks = $GLOBALS['norenChecks'];
    if ($failures !== []) {
        fwrite(STDERR, "$suite: " . count($failures) . " of $checks checks failed\n" . implode("\n", $failures) . "\n");
        exit(1);
    }
    echo "$suite: all $checks checks passed\n";
    exit(0);
}

/* --------------------------------------------------------------------------
 * Minimal stand-ins for Kirby structure fields — just what
 * Schedule::fromFields() touches: toStructure(), field access by method
 * name, string conversion.
 * -------------------------------------------------------------------------- */

final class TestValue
{
    public function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function isNotEmpty(): bool
    {
        return $this->value !== '';
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }
}

final class TestRow
{
    public function __construct(private array $data)
    {
    }

    public function __call(string $name, array $args): TestValue
    {
        return new TestValue((string) ($this->data[$name] ?? ''));
    }
}

final class TestField
{
    public function __construct(private array $rows)
    {
    }

    public function toStructure(): array
    {
        return array_map(static fn (array $row) => new TestRow($row), $this->rows);
    }
}

/** field([['tag' => 'Montag', 'von1' => '08:00', 'bis1' => '12:00'], …]) */
function field(array $rows): TestField
{
    return new TestField($rows);
}

/* --------------------------------------------------------------------------
 * Kirby fixtures (need vendor/: composer install).
 * -------------------------------------------------------------------------- */

/**
 * A throwaway site directory with the package symlinked as
 * site/plugins/noren, a default template and a home page. Returns its path;
 * remove it with noren_remove_tree() in a finally block.
 */
function noren_site(): string
{
    $base = dirname(__DIR__);
    if (!is_file($base . '/vendor/autoload.php')) {
        fwrite(STDERR, "Composer dependencies missing; run composer install first\n");
        exit(1);
    }
    require_once $base . '/vendor/autoload.php';

    $tmp = sys_get_temp_dir() . '/noren-kirby-' . bin2hex(random_bytes(6));
    foreach (['public', 'content/home', 'site/plugins', 'site/snippets', 'site/templates', 'site/blueprints/pages', 'storage'] as $dir) {
        mkdir($tmp . '/' . $dir, 0700, true);
    }
    symlink($base, $tmp . '/site/plugins/noren');
    file_put_contents($tmp . '/site/templates/default.php', 'Fixture');
    file_put_contents($tmp . '/content/home/home.txt', 'Title: Home');
    return $tmp;
}

/**
 * A fresh Kirby instance on a noren_site() directory with these options.
 * Snippet paths are cached per process by Kirby, so the cache is reset.
 */
function noren_kirby(string $tmp, array $options = []): Kirby\Cms\App
{
    Kirby\Template\Snippet::$cache = [];
    return new Kirby\Cms\App([
        'roots' => [
            'base'    => $tmp,
            'index'   => $tmp . '/public',
            'content' => $tmp . '/content',
            'site'    => $tmp . '/site',
            'storage' => $tmp . '/storage',
        ],
        'options' => ['url' => 'http://example.invalid'] + $options,
    ]);
}

/**
 * Writes content/<dir>/<template>.txt. Array values (structure fields) are
 * YAML-encoded the way the Panel stores them.
 *
 *   noren_page($tmp, '1_kontakt', 'betrieb', ['title' => 'Kontakt', 'zeiten' => [[…], …]]);
 */
function noren_page(string $tmp, string $dir, string $template, array $fields): void
{
    $lines = [];
    foreach ($fields as $name => $value) {
        $text = is_array($value) ? Kirby\Data\Yaml::encode($value) : (string) $value;
        $lines[] = ucfirst($name) . ': ' . (is_array($value) ? "\n\n" . $text : $text);
    }
    @mkdir($tmp . '/content/' . $dir, 0700, true);
    file_put_contents($tmp . '/content/' . $dir . '/' . $template . '.txt', implode("\n\n----\n\n", $lines) . "\n");
}

function noren_remove_tree(string $path): void
{
    if (!is_dir($path) || is_link($path)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) {
        $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($path);
}
