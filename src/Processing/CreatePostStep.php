<?php

namespace Oriel\Processing;

use Oriel\PostType;

class CreatePostStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        if ($context->shouldHalt) {
            return $context;
        }

        $title = ($context->formConfig['title'] ?? 'Submission')
            . ' - ' . current_time('Y-m-d H:i:s');

        $postId = wp_insert_post([
            'post_type' => PostType::POST_TYPE,
            'post_title' => $title,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($postId)) {
            $context->errors['post'] = 'Failed to save submission.';
            $context->shouldHalt = true;
            return $context;
        }

        $context->postId = $postId;

        // Save each field value as post meta with prefix.
        foreach ($context->formConfig['fields'] as $field) {
            $id = $field['id'];
            $value = $context->submittedData[$id] ?? '';
            update_post_meta($postId, ORIEL_META_PREFIX . $id, $value);
        }

        // Tag the submission with its form ID.
        wp_set_object_terms($postId, [$context->formId], PostType::TAXONOMY);

        return $context;
    }
}
