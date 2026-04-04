<?php

namespace Oriel\Processing;

use Oriel\Plugin;

class ValidateStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        $plugin = Plugin::instance();

        foreach ($context->formConfig['fields'] as $field) {
            $type = $field['type'] ?? 'text';
            $fieldInstance = $plugin->getFieldInstance($type);

            if (!$fieldInstance || $fieldInstance->isTransient()) {
                continue;
            }

            $id = $field['id'];
            $value = $context->submittedData[$id] ?? null;

            // Sanitize first, then validate the sanitized value.
            $sanitized = $fieldInstance->sanitize($field, $value);
            $context->submittedData[$id] = $sanitized;

            $error = $fieldInstance->validate($field, $sanitized);

            if ($error !== null) {
                $context->errors[$id] = $error;
            }
        }

        // Custom validation filters.
        $context->errors = apply_filters(
            'oriel_validate',
            $context->errors,
            $context->submittedData,
            $context->formConfig
        ) ?: [];

        $context->errors = apply_filters(
            'oriel_validate_' . $context->formId,
            $context->errors,
            $context->submittedData,
            $context->formConfig
        ) ?: [];

        if (!empty($context->errors)) {
            $context->shouldHalt = true;
        }

        return $context;
    }
}
