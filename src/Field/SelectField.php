<?php

namespace Oriel\Field;

class SelectField extends AbstractField
{
    protected function getType(): string
    {
        return 'select';
    }

    protected function renderInput(array $field, $value, string $formId): string
    {
        $attrs = $this->buildAttributes($field, $formId);
        $options = $field['options'] ?? [];

        $html = '<select ' . $attrs . '>';

        if (!empty($field['placeholder'])) {
            $html .= '<option value="">' . esc_html($field['placeholder']) . '</option>';
        }

        foreach ($options as $optionValue => $optionLabel) {
            $selected = ((string) $value === (string) $optionValue) ? ' selected' : '';
            $html .= '<option value="' . esc_attr($optionValue) . '"' . $selected . '>';
            $html .= esc_html($optionLabel);
            $html .= '</option>';
        }

        $html .= '</select>';

        return $html;
    }
}
