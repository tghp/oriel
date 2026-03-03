<?php

namespace Oriel\Processing;

class CleanupStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        if ($context->shouldHalt || !$context->postId) {
            return $context;
        }

        $shouldDelete = $context->formConfig['options']['delete_after_processing'] ?? false;

        if (!$shouldDelete) {
            return $context;
        }

        $doNotDelete = get_post_meta($context->postId, ORIEL_META_PREFIX . 'do_not_delete', true);

        if ($doNotDelete === '1') {
            return $context;
        }

        wp_delete_post($context->postId, true);

        return $context;
    }
}
