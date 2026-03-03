<?php

namespace Oriel\Processing;

use Oriel\Security\HoneypotCheck;
use Oriel\Security\RateLimitCheck;
use Oriel\Security\TimingCheck;
use Oriel\Security\NonceCheck;

class SecurityStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        $checks = [
            new HoneypotCheck(),
            new RateLimitCheck(),
            new TimingCheck(),
            new NonceCheck(),
        ];

        foreach ($checks as $check) {
            $error = $check->check($context);

            if ($error !== null) {
                $message = apply_filters('oriel_security_error_message', $error);
                $context->errors['security'] = $message;
                $context->shouldHalt = true;

                return $context;
            }
        }

        return $context;
    }
}
