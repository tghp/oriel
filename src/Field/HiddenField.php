<?php

namespace Oriel\Field;

class HiddenField extends AbstractField
{
    protected function getType(): string
    {
        return 'hidden';
    }

    /**
     * Hidden fields render only the input — no wrapper, label, desc, or error.
     *
     * {@inheritdoc}
     */
    public function render(array $field, $value, string $formId): string
    {
        return $this->renderInput($field, $value, $formId);
    }

    protected function renderInput(array $field, $value, string $formId): string
    {
        $attrs = $this->buildAttributes($field, $formId, [
            'type'  => 'hidden',
            'value' => esc_attr($value ?? ''),
        ]);

        return '<input ' . $attrs . ' />';
    }
}
