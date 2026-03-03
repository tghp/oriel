<?php

namespace Oriel\Processing;

interface StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext;
}
