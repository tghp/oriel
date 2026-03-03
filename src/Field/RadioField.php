<?php

namespace Oriel\Field;

class RadioField extends AbstractField
{
    protected function getType(): string
    {
        return 'radio';
    }

    protected function renderInput(array $field, $value, string $formId): string
    {
        $options = $field['options'] ?? [];
        $inputName = $this->getInputName($field);
        $inputIdBase = $this->getInputId($field, $formId);

        $radiosClass = apply_filters('oriel_field_radios_class', 'oriel-field__radios', $field, $formId);
        $html = '<div class="' . $radiosClass . '">';

        foreach ($options as $optionValue => $optionLabel) {
            $optionId = $inputIdBase . '_' . $optionValue;
            $checked = ((string) $value === (string) $optionValue) ? ' checked' : '';

            $html .= '<label for="' . esc_attr($optionId) . '">';
            $html .= '<input type="radio" id="' . esc_attr($optionId) . '" name="' . esc_attr($inputName) . '" value="' . esc_attr($optionValue) . '"' . $checked;

            if (!empty($field['required'])) {
                $html .= ' required="required"';
            }

            $html .= ' />';
            $html .= ' ' . esc_html($optionLabel);
            $html .= '</label>';
        }

        $html .= '</div>';

        return $html;
    }
}
