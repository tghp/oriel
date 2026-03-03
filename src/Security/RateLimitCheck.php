<?php

namespace Oriel\Security;

use Oriel\Processing\ProcessingContext;

class RateLimitCheck implements SecurityCheckInterface
{
    public function check(ProcessingContext $context): ?string
    {
        $maxAttempts = (int) apply_filters('oriel_security_rate_limit', 5);
        $window = (int) apply_filters('oriel_security_rate_window', 600);

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if (empty($ip)) {
            return null;
        }

        $key = 'oriel_rl_' . md5($ip);
        $count = (int) get_transient($key);

        if ($count >= $maxAttempts) {
            return 'Submission rejected.';
        }

        set_transient($key, $count + 1, $window);

        return null;
    }
}
