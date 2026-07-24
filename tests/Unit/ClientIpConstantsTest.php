<?php

/**
 * Constant-based configuration (ORIEL_TRUSTED_IP_HEADER /
 * ORIEL_TRUSTED_IP_ENVIRONMENT) can't be exercised in-process — constants
 * can't be undefined between tests — so each scenario shells out to
 * tests/bin/resolve-client-ip.php in a fresh PHP process and asserts on
 * its stdout.
 */

function resolveClientIpViaSubprocess(array $config): string
{
    $script = dirname(__DIR__) . '/bin/resolve-client-ip.php';

    $command = sprintf(
        '%s %s %s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($script),
        escapeshellarg(json_encode($config))
    );

    exec($command, $output, $exitCode);
    $stdout = implode("\n", $output);

    expect($exitCode)->toBe(0, "Subprocess failed: {$stdout}");

    return $stdout;
}

describe('constant configuration (subprocess)', function () {
    it('uses the ORIEL_TRUSTED_IP_HEADER constant', function () {
        $ip = resolveClientIpViaSubprocess([
            'constants' => ['ORIEL_TRUSTED_IP_HEADER' => 'X-Real-IP'],
            'server' => [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_X_REAL_IP' => '192.0.2.44',
            ],
        ]);

        expect($ip)->toBe('192.0.2.44');
    });

    it('uses the ORIEL_TRUSTED_IP_ENVIRONMENT constant', function () {
        $ip = resolveClientIpViaSubprocess([
            'constants' => ['ORIEL_TRUSTED_IP_ENVIRONMENT' => 'cloudflare'],
            'server' => [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
            ],
        ]);

        expect($ip)->toBe('198.51.100.7');
    });

    it('prefers the header constant over the environment constant', function () {
        $ip = resolveClientIpViaSubprocess([
            'constants' => [
                'ORIEL_TRUSTED_IP_HEADER' => 'X-Real-IP',
                'ORIEL_TRUSTED_IP_ENVIRONMENT' => 'cloudflare',
            ],
            'server' => [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
                'HTTP_X_REAL_IP' => '192.0.2.44',
            ],
        ]);

        expect($ip)->toBe('192.0.2.44');
    });

    it('lets the filter override the header constant', function () {
        $ip = resolveClientIpViaSubprocess([
            'constants' => ['ORIEL_TRUSTED_IP_HEADER' => 'X-Real-IP'],
            'filters' => ['oriel_trusted_ip_header' => 'CF-Connecting-IP'],
            'server' => [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
                'HTTP_X_REAL_IP' => '192.0.2.44',
            ],
        ]);

        expect($ip)->toBe('198.51.100.7');
    });

    it('lets the filter override the environment constant', function () {
        $ip = resolveClientIpViaSubprocess([
            'constants' => ['ORIEL_TRUSTED_IP_ENVIRONMENT' => 'wpengine'],
            'filters' => ['oriel_trusted_ip_environment' => 'cloudflare'],
            'server' => [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.60',
            ],
        ]);

        expect($ip)->toBe('198.51.100.7');
    });

    it('ignores an unrelated forwarding header when only the constant names another', function () {
        $ip = resolveClientIpViaSubprocess([
            'constants' => ['ORIEL_TRUSTED_IP_HEADER' => 'X-Real-IP'],
            'server' => [
                'REMOTE_ADDR' => '203.0.113.9',
                'HTTP_X_FORWARDED_FOR' => '6.6.6.6',
            ],
        ]);

        expect($ip)->toBe('203.0.113.9');
    });
});
