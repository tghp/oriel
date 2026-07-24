<?php
/**
 * Subprocess fixture for constant-based ClientIp tests.
 *
 * Constants can't be undefined, so scenarios involving ORIEL_TRUSTED_IP_*
 * each run in a fresh PHP process (Pest can't use PHPUnit process isolation:
 * https://github.com/pestphp/pest/issues/910). Takes a JSON config as argv[1]:
 *
 *   {
 *     "constants": { "ORIEL_TRUSTED_IP_HEADER": "X-Real-IP" },
 *     "filters":   { "oriel_trusted_ip_header": "CF-Connecting-IP" },
 *     "server":    { "REMOTE_ADDR": "10.0.0.1" }
 *   }
 *
 * Filter values are returned as-is by the registered filter. Prints the
 * resolved IP to stdout.
 */

$config = json_decode($argv[1] ?? '{}', true);

if (! is_array($config)) {
    fwrite(STDERR, 'Invalid JSON config.');
    exit(1);
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__) . '/wp-stubs.php';

foreach ($config['constants'] ?? [] as $name => $value) {
    define($name, $value);
}

foreach ($config['filters'] ?? [] as $tag => $value) {
    add_filter($tag, fn () => $value);
}

$_SERVER = $config['server'] ?? [];

echo \Oriel\Security\ClientIp::resolve();
