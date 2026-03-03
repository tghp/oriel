<?php

namespace Oriel\Field;

class CheckboxField extends AbstractField
{
    protected function getType(): string
    {
        return 'checkbox';
    }

    /**
     * Custom render — checkbox uses a label-wrapped layout with desc inline.
     *
     * {@inheritdoc}
     */
    public function render(array $field, $value, string $formId): string
    {
        $id = $field['id'] ?? '';
        $extraClass = $field['class'] ?? '';

        $classes = 'oriel-field oriel-field--checkbox oriel-field--' . esc_attr($id);

        if ($extraClass) {
            $classes .= ' ' . esc_attr($extraClass);
        }

        $html = '<div class="' . $classes . '">';
        $html .= $this->renderInput($field, $value, $formId);
        $html .= $this->renderError($field);
        $html .= '</div>';

        return $html;
    }

    protected function renderInput(array $field, $value, string $formId): string
    {
        $inputId = $this->getInputId($field, $formId);
        $inputName = $this->getInputName($field);
        $checked = !empty($value) ? ' checked' : '';
        $desc = $field['desc'] ?? $field['name'] ?? '';

        $html = '<input type="hidden" name="' . esc_attr($inputName) . '" value="0" />';
        $html .= '<label for="' . esc_attr($inputId) . '">';
        $html .= '<input type="checkbox" id="' . esc_attr($inputId) . '" name="' . esc_attr($inputName) . '" value="1"' . $checked;

        if (!empty($field['required'])) {
            $html .= ' required="required"';
        }

        $html .= ' />';
        $html .= ' ' . esc_html($desc);
        $html .= '</label>';

        return $html;
    }

    /**
     * {@inheritdoc}
     */
    public function sanitize(array $field, $value)
    {
        return $value ? 1 : 0;
    }
}
