<?php

namespace Oriel\Processing;

class HooksStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        if ($context->shouldHalt) {
            return $context;
        }

        do_action('oriel_after_process', $context->formId, $context->postId);
        do_action('oriel_after_process_' . $context->formId, $context->postId);

        return $context;
    }
}
