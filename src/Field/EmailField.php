<?php

namespace Oriel\Field;

class EmailField extends AbstractField
{
    protected function getType(): string
    {
        return 'email';
    }

    protected function renderInput(array $field, $value, string $formId): string
    {
        $attrs = $this->buildAttributes($field, $formId, [
            'type'  => 'email',
            'value' => esc_attr($value ?? ''),
        ]);

        return '<input ' . $attrs . ' />';
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $field, $value): ?string
    {
        $parentError = parent::validate($field, $value);

        if ($parentError !== null) {
            return $parentError;
        }

        if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $name = $field['name'] ?? $field['id'] ?? 'This field';

            return $name . ' must be a valid email address.';
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function sanitize(array $field, $value)
    {
        return sanitize_email($value);
    }
}
