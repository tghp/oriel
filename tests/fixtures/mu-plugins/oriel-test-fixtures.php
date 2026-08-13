<?php
/**
 * Plugin Name: Oriel Test Fixtures
 * Description: Forms and security tuning for the Oriel E2E suite. Only active
 *              when ORIEL_TEST is defined (the test compose stack sets it).
 */

// Hard guard: never do anything outside the test environment.
if (! defined('ORIEL_TEST') || ! ORIEL_TEST) {
    return;
}

/*
 * Security defaults tuned for test speed. Applied at the default priority so
 * per-request header overrides (below, priority 20) win.
 */
add_filter('oriel_security_min_time', fn () => 0);
add_filter('oriel_security_rate_limit', fn () => 9999);

/*
 * Per-request override via the X-Oriel-Test header. Its value is JSON; the only
 * honored keys are the four integer security knobs. Each present key registers
 * the matching oriel_security_* filter at priority 20 so it beats the defaults.
 * Malformed JSON is ignored silently.
 */
$orielTestRaw = $_SERVER['HTTP_X_ORIEL_TEST'] ?? '';

if ($orielTestRaw !== '') {
    $orielTestOverrides = json_decode($orielTestRaw, true);

    if (is_array($orielTestOverrides)) {
        foreach (['min_time', 'max_time', 'rate_limit', 'rate_window'] as $knob) {
            if (! isset($orielTestOverrides[$knob])) {
                continue;
            }

            $value = (int) $orielTestOverrides[$knob];

            add_filter("oriel_security_{$knob}", fn () => $value, 20);
        }
    }
}

/*
 * Let rate-limit tests carry their own client identity. Each test sends a
 * distinct X-Oriel-Test-IP so buckets don't bleed across tests. ClientIp reads
 * the header named here from $_SERVER (HTTP_X_ORIEL_TEST_IP).
 */
add_filter('oriel_trusted_ip_header', fn () => 'X-Oriel-Test-IP');

// WordPress defaults the From to wordpress@{host} = wordpress@localhost,
// which PHPMailer rejects as an invalid address — silently failing every
// wp_mail(). Use a routable-looking sender.
add_filter('wp_mail_from', fn () => 'wordpress@example.test');

// Route all mail to Mailpit so tests can assert on delivered messages.
add_action('phpmailer_init', function ($phpmailer): void {
    $phpmailer->isSMTP();
    $phpmailer->Host = 'mailpit';
    $phpmailer->Port = 1025;
    $phpmailer->SMTPAuth = false;
    $phpmailer->SMTPSecure = '';
    $phpmailer->SMTPAutoTLS = false;
});

/*
 * Observation channel for the REST identity regression (issue #1): stamp the
 * pipeline-time user ID onto each security_min_ajax submission so specs can
 * assert who oriel_after_process_* handlers ran as.
 */
add_action('oriel_after_process_security_min_ajax', function ($postId): void {
    update_post_meta($postId, '_oriel_test_user_id', (string) get_current_user_id());
});

// Register the fixture forms.
add_filter('oriel_forms', function (array $forms): array {
    // Shared field set for the two kitchen-sink variants — one field per
    // non-captcha type. Rebuilt per form so the arrays stay independent.
    $kitchenSinkFields = fn (): array => [
        ['id' => 'name',           'name' => 'Name',    'type' => 'text',     'required' => true, 'email' => true],
        ['id' => 'email',          'name' => 'Email',   'type' => 'email',    'required' => true, 'email' => true],
        ['id' => 'message',        'name' => 'Message', 'type' => 'textarea', 'email' => true],
        ['id' => 'agree',          'name' => 'Agree',   'type' => 'checkbox', 'desc' => 'I agree to the terms', 'email' => true],
        [
            'id'      => 'topic',
            'name'    => 'Topic',
            'type'    => 'select',
            'options' => ['general' => 'General', 'support' => 'Support', 'sales' => 'Sales'],
            'email'   => true,
        ],
        [
            'id'      => 'contact_method',
            'name'    => 'Preferred contact method',
            'type'    => 'radio',
            'options' => ['email' => 'Email', 'phone' => 'Phone'],
            'email'   => true,
        ],
        ['id' => 'source', 'name' => 'Source', 'type' => 'hidden', 'std' => 'fixture-default'],
    ];

    $forms['kitchen_sink'] = [
        'title'   => 'Kitchen Sink',
        'options' => [
            'ajax'         => false,
            'confirmation' => 'Thanks — your kitchen_sink submission was received.',
            'email'        => ['email' => 'kitchen_sink@example.test', 'title' => 'Oriel Test: kitchen_sink'],
        ],
        'fields' => $kitchenSinkFields(),
    ];

    $forms['kitchen_sink_ajax'] = [
        'title'   => 'Kitchen Sink (AJAX)',
        'options' => [
            'ajax'         => true,
            'confirmation' => 'Thanks — your kitchen_sink_ajax submission was received.',
            'email'        => ['email' => 'kitchen_sink_ajax@example.test', 'title' => 'Oriel Test: kitchen_sink_ajax'],
        ],
        'fields' => $kitchenSinkFields(),
    ];

    $forms['security_min'] = [
        'title'   => 'Security Min',
        'options' => [
            'ajax'         => false,
            'confirmation' => 'Thanks — your security_min submission was received.',
            'email'        => ['email' => 'security_min@example.test', 'title' => 'Oriel Test: security_min'],
        ],
        'fields' => [
            ['id' => 'marker', 'name' => 'Marker', 'type' => 'text', 'required' => true, 'email' => true],
        ],
    ];

    $forms['security_min_ajax'] = [
        'title'   => 'Security Min (AJAX)',
        'options' => [
            'ajax'         => true,
            'confirmation' => 'Thanks — your security_min_ajax submission was received.',
            'email'        => ['email' => 'security_min_ajax@example.test', 'title' => 'Oriel Test: security_min_ajax'],
        ],
        'fields' => [
            ['id' => 'marker', 'name' => 'Marker', 'type' => 'text', 'required' => true, 'email' => true],
        ],
    ];

    $forms['compat_tghpmb'] = [
        'title'   => 'Compat tghpmb',
        'options' => [
            'ajax'          => false,
            'compat'        => 'tghpmb',
            'compat_prefix' => '_tghptest_',
            'submit_class'  => 'rwmb-button button button--blue-dark',
            'confirmation'  => 'Thanks — your compat_tghpmb submission was received.',
            'email'         => ['email' => 'compat_tghpmb@example.test', 'title' => 'Oriel Test: compat_tghpmb'],
        ],
        'fields' => [
            ['id' => 'email',   'name' => 'Email',   'type' => 'email', 'required' => true, 'email' => true, 'placeholder' => 'Email'],
            ['id' => 'message', 'name' => 'Message', 'type' => 'text',  'required' => true, 'email' => true, 'placeholder' => 'Message'],
        ],
    ];

    $forms['captcha_turnstile'] = [
        'title'   => 'Captcha Turnstile',
        'options' => [
            'ajax'         => true,
            'confirmation' => 'Thanks — your captcha_turnstile submission was received.',
            'email'        => ['email' => 'captcha_turnstile@example.test', 'title' => 'Oriel Test: captcha_turnstile'],
        ],
        'fields' => [
            ['id' => 'name',  'name' => 'Name',  'type' => 'text',  'required' => true, 'email' => true],
            ['id' => 'email', 'name' => 'Email', 'type' => 'email', 'required' => true, 'email' => true],
            [
                'id'       => 'captcha',
                'type'     => 'captcha',
                'provider' => 'turnstile',
                // Cloudflare test keys: sitekey and secret that always pass.
                'sitekey'  => '1x00000000000000000000AA',
                'secret'   => '1x0000000000000000000000000000000AA',
            ],
        ],
    ];

    $forms['captcha_turnstile_fail'] = [
        'title'   => 'Captcha Turnstile (Fail)',
        'options' => [
            'ajax'         => true,
            'confirmation' => 'Thanks — your captcha_turnstile_fail submission was received.',
            'email'        => ['email' => 'captcha_turnstile_fail@example.test', 'title' => 'Oriel Test: captcha_turnstile_fail'],
        ],
        'fields' => [
            ['id' => 'name',  'name' => 'Name',  'type' => 'text',  'required' => true, 'email' => true],
            ['id' => 'email', 'name' => 'Email', 'type' => 'email', 'required' => true, 'email' => true],
            [
                'id'       => 'captcha',
                'type'     => 'captcha',
                'provider' => 'turnstile',
                // Always-passes sitekey (so the widget yields a token) paired
                // with the always-fails secret (so server verification fails).
                'sitekey'  => '1x00000000000000000000AA',
                'secret'   => '2x0000000000000000000000000000000AA',
            ],
        ],
    ];

    $forms['captcha_recaptcha'] = [
        'title'   => 'Captcha reCAPTCHA',
        'options' => [
            'ajax'         => true,
            'confirmation' => 'Thanks — your captcha_recaptcha submission was received.',
            'email'        => ['email' => 'captcha_recaptcha@example.test', 'title' => 'Oriel Test: captcha_recaptcha'],
        ],
        'fields' => [
            ['id' => 'name',  'name' => 'Name',  'type' => 'text',  'required' => true, 'email' => true],
            ['id' => 'email', 'name' => 'Email', 'type' => 'email', 'required' => true, 'email' => true],
            [
                'id'       => 'captcha',
                'type'     => 'captcha',
                'provider' => 'recaptcha',
                // Google's official reCAPTCHA v2 test pair (always passes).
                'sitekey'  => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',
                'secret'   => '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe',
            ],
        ],
    ];

    $forms['redirect_form'] = [
        'title'   => 'Redirect Form',
        'options' => [
            'ajax'     => false,
            'redirect' => '/redirect-target/',
            'email'    => ['email' => 'redirect_form@example.test', 'title' => 'Oriel Test: redirect_form'],
        ],
        'fields' => [
            ['id' => 'marker', 'name' => 'Marker', 'type' => 'text', 'required' => true, 'email' => true],
        ],
    ];

    $forms['delete_after'] = [
        'title'   => 'Delete After',
        'options' => [
            'ajax'                    => false,
            'delete_after_processing' => true,
            'confirmation'            => 'Thanks — your delete_after submission was received.',
            'email'                   => ['email' => 'delete_after@example.test', 'title' => 'Oriel Test: delete_after'],
        ],
        'fields' => [
            ['id' => 'marker', 'name' => 'Marker', 'type' => 'text', 'required' => true, 'email' => true],
        ],
    ];

    // Plain form — the hide/toggle behavior comes from shortcode args on the
    // page, not from form config.
    $forms['toggle'] = [
        'title'   => 'Toggle',
        'options' => [
            'ajax'         => false,
            'confirmation' => 'Thanks — your toggle submission was received.',
            'email'        => ['email' => 'toggle@example.test', 'title' => 'Oriel Test: toggle'],
        ],
        'fields' => [
            ['id' => 'marker', 'name' => 'Marker', 'type' => 'text', 'email' => true],
        ],
    ];

    return $forms;
});
