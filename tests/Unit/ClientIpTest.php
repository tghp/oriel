<?php

use Oriel\Security\ClientIp;

describe('default behaviour', function () {
    it('uses REMOTE_ADDR and ignores forwarding headers when nothing is configured', function () {
        $_SERVER = [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
        ];

        expect(ClientIp::resolve())->toBe('203.0.113.9');
    });

    it('returns an empty string when REMOTE_ADDR is missing', function () {
        expect(ClientIp::resolve())->toBe('');
    });
});

describe('environment shorthand', function () {
    it('resolves from the platform header', function (string $environment, string $serverKey) {
        add_filter('oriel_trusted_ip_environment', fn () => $environment);
        $_SERVER = [
            'REMOTE_ADDR' => '10.0.0.1',
            $serverKey => '198.51.100.7',
        ];

        expect(ClientIp::resolve())->toBe('198.51.100.7');
    })->with([
        'cloudflare' => ['cloudflare', 'HTTP_CF_CONNECTING_IP'],
        'kinsta' => ['kinsta', 'HTTP_CF_CONNECTING_IP'],
        'wpengine' => ['wpengine', 'HTTP_X_FORWARDED_FOR'],
    ]);

    it('normalises case and whitespace', function () {
        add_filter('oriel_trusted_ip_environment', fn () => ' Cloudflare ');
        $_SERVER = [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
        ];

        expect(ClientIp::resolve())->toBe('198.51.100.7');
    });

    it('falls back to REMOTE_ADDR for an unknown environment', function () {
        add_filter('oriel_trusted_ip_environment', fn () => 'nonsense');
        $_SERVER = [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
        ];

        expect(ClientIp::resolve())->toBe('10.0.0.1');
    });
});

describe('explicit header', function () {
    it('takes precedence over the environment shorthand', function () {
        add_filter('oriel_trusted_ip_environment', fn () => 'cloudflare');
        add_filter('oriel_trusted_ip_header', fn () => 'X-Real-IP');
        $_SERVER = [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
            'HTTP_X_REAL_IP' => '192.0.2.44',
        ];

        expect(ClientIp::resolve())->toBe('192.0.2.44');
    });

    it('falls back to REMOTE_ADDR when the header is missing', function () {
        add_filter('oriel_trusted_ip_header', fn () => 'X-Forwarded-For');
        $_SERVER = ['REMOTE_ADDR' => '203.0.113.9'];

        expect(ClientIp::resolve())->toBe('203.0.113.9');
    });

    it('falls back to REMOTE_ADDR when the header holds no valid IP', function () {
        add_filter('oriel_trusted_ip_header', fn () => 'X-Forwarded-For');
        $_SERVER = [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_X_FORWARDED_FOR' => 'not-an-ip, <script>',
        ];

        expect(ClientIp::resolve())->toBe('203.0.113.9');
    });
});

describe('chain parsing', function () {
    beforeEach(function () {
        add_filter('oriel_trusted_ip_header', fn () => 'X-Forwarded-For');
    });

    it('takes the rightmost public IP, ignoring client-seeded entries', function () {
        $_SERVER = [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.23, 10.0.0.5',
        ];

        expect(ClientIp::resolve())->toBe('198.51.100.23');
    });

    it('falls back to the rightmost private IP when no entry is public', function () {
        $_SERVER = [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '192.168.1.10, 10.0.0.5',
        ];

        expect(ClientIp::resolve())->toBe('10.0.0.5');
    });

    it('handles IPv6 addresses', function () {
        $_SERVER = [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '2001:db8::1, 2606:4700::1',
        ];

        expect(ClientIp::resolve())->toBe('2606:4700::1');
    });
});

describe('oriel_client_ip filter', function () {
    it('overrides the resolved value as a final escape hatch', function () {
        add_filter('oriel_trusted_ip_header', fn () => 'X-Forwarded-For');
        add_filter('oriel_client_ip', fn () => '1.2.3.4');
        $_SERVER = [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '2001:db8::1, 2606:4700::1',
        ];

        expect(ClientIp::resolve())->toBe('1.2.3.4');
    });
});
