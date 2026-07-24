<?php
/**
 * Minimal WordPress function stubs for unit tests.
 *
 * Only what the units under test touch — extend as coverage grows.
 * Call wp_stubs_reset() between tests (wired up in Pest.php).
 */

$GLOBALS['__wp_filters'] = [];
$GLOBALS['__wp_transients'] = [];

function wp_stubs_reset(): void
{
    $GLOBALS['__wp_filters'] = [];
    $GLOBALS['__wp_transients'] = [];
}

if (! function_exists('add_filter')) {
    function add_filter(string $tag, callable $callback): void
    {
        $GLOBALS['__wp_filters'][$tag][] = $callback;
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $tag, $value, ...$args)
    {
        foreach ($GLOBALS['__wp_filters'][$tag] ?? [] as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }
}

if (! function_exists('get_transient')) {
    function get_transient(string $key)
    {
        return $GLOBALS['__wp_transients'][$key] ?? false;
    }
}

if (! function_exists('set_transient')) {
    function set_transient(string $key, $value, int $expiration = 0): bool
    {
        $GLOBALS['__wp_transients'][$key] = $value;

        return true;
    }
}
