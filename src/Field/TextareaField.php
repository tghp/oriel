<?php

namespace Oriel\Field;

class TextareaField extends AbstractField
{
    protected function getType(): string
    {
        return 'textarea';
    }

    protected function renderInput(array $field, $value, string $formId): string
    {
        $attrs = $this->buildAttributes($field, $formId, [
            'rows' => $field['rows'] ?? '5',
        ]);

        return '<textarea ' . $attrs . '>' . esc_textarea($value ?? '') . '</textarea>';
    }

    /**
     * {@inheritdoc}
     */
    public function sanitize(array $field, $value)
    {
        return sanitize_textarea_field($value);
    }
}
