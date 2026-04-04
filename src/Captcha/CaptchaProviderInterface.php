<?php

namespace Oriel\Captcha;

interface CaptchaProviderInterface
{
    /**
     * Verify a captcha token against the provider's API.
     *
     * @param string $token  The token from the client-side widget.
     * @param string $secret The server-side secret key.
     * @return bool True if verification passed.
     */
    public function verify(string $token, string $secret): bool;
}
