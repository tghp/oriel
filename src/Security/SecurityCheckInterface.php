<?php

namespace Oriel\Security;

use Oriel\Processing\ProcessingContext;

interface SecurityCheckInterface
{
    /**
     * Run the security check.
     *
     * @return string|null Null on pass, error message on fail.
     */
    public function check(ProcessingContext $context): ?string;
}
