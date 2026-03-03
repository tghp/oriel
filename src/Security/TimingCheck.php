<?php

namespace Oriel\Security;

use Oriel\Processing\ProcessingContext;

class TimingCheck implements SecurityCheckInterface
{
    public const FIELD_NAME = '_oriel_tk';

    public function check(ProcessingContext $context): ?string
    {
        $minTime = (int) apply_filters('oriel_security_min_time', 3);

        $token = $_POST[self::FIELD_NAME] ?? '';

        if (empty($token)) {
            return 'Submission rejected.';
        }

        $decoded = base64_decode($token, true);

        if ($decoded === false) {
            return 'Submission rejected.';
        }

        $renderTime = (int) $decoded;

        if ($renderTime <= 0) {
            return 'Submission rejected.';
        }

        $elapsed = time() - $renderTime;
        $maxTime = (int) apply_filters('oriel_security_max_time', 86400);

        if ($elapsed < $minTime || $elapsed > $maxTime) {
            return 'Submission rejected.';
        }

        return null;
    }
}
