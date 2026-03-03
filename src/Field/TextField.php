<?php

namespace Oriel\Field;

class TextField extends AbstractField
{
    protected function getType(): string
    {
        return 'text';
    }

    protected function renderInput(array $field, $value, string $formId): string
    {
        $attrs = $this->buildAttributes($field, $formId, [
            'type'  => 'text',
            'value' => esc_attr($value ?? ''),
        ]);

        return '<input ' . $attrs . ' />';
    }
}
