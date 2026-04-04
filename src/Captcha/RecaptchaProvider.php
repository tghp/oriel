<?php

namespace Oriel\Captcha;

class RecaptchaProvider implements CaptchaProviderInterface
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function verify(string $token, string $secret): bool
    {
        $response = wp_remote_post(self::VERIFY_URL, [
            'body' => [
                'secret'   => $secret,
                'response' => $token,
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return !empty($body['success']);
    }
}
