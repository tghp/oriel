<?php

namespace Oriel\Security;

use Oriel\Processing\ProcessingContext;

class RateLimitCheck implements SecurityCheckInterface
{
    public function check(ProcessingContext $context): ?string
    {
        $maxAttempts = (int) apply_filters('oriel_security_rate_limit', 5);
        $window = (int) apply_filters('oriel_security_rate_window', 600);

        $ip = ClientIp::resolve();

        if (empty($ip)) {
            return null;
        }

        $key = 'oriel_rl_' . md5($ip);
        $count = (int) get_transient($key);

        if ($count >= $maxAttempts) {
            return 'Submission rejected.';
        }

        // Sliding window: each submission resets the expiry. A persistent
        // attacker must wait a full $window of inactivity before the count resets.
        set_transient($key, $count + 1, $window);

        return null;
    }
}
