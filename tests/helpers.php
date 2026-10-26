<?php
/**
 * Shared test helpers.
 *
 * Loaded by every test file with require_once. The checks use PHPUnit's
 * assertions, so a failing check ends the test method and is reported by
 * PHPUnit with its label.
 */

use PHPUnit\Framework\Assert;

/** Strict comparison ($expected === $actual). */
function expect(mixed $expected, mixed $actual, string $what): void
{
    Assert::assertSame($expected, $actual, $what);
}

function holds(bool $ok, string $what): void
{
    Assert::assertTrue($ok, $what);
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
 * remove it with Dir::remove() in a finally block.
 */
function noren_site(): string
{
    $base = dirname(__DIR__);
    if (!is_file($base . '/vendor/autoload.php')) {
        throw new RuntimeException('Composer dependencies missing; run composer install first');
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
