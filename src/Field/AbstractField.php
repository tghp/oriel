<?php

namespace Oriel\Field;

abstract class AbstractField implements FieldInterface
{
    /**
     * Return the field type slug (e.g. "text", "email").
     */
    abstract protected function getType(): string;

    /**
     * Render the inner input element(s).
     *
     * @param array  $field  Field configuration array.
     * @param mixed  $value  Current value.
     * @param string $formId Form identifier.
     * @return string
     */
    abstract protected function renderInput(array $field, $value, string $formId): string;

    /**
     * {@inheritdoc}
     */
    public function render(array $field, $value, string $formId): string
    {
        $type = $this->getType();
        $id = $field['id'] ?? '';
        $extraClass = $field['class'] ?? '';

        $classes = 'oriel-field oriel-field--' . esc_attr($type) . ' oriel-field--' . esc_attr($id);

        if ($extraClass) {
            $classes .= ' ' . esc_attr($extraClass);
        }

        $html = '<div class="' . $classes . '">';
        $html .= $this->renderLabel($field, $formId);
        $html .= $this->renderInput($field, $value, $formId);
        $html .= $this->renderDescription($field);
        $html .= $this->renderError($field);
        $html .= '</div>';

        return $html;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $field, $value): ?string
    {
        $required = !empty($field['required']);

        if ($required && $this->isEmpty($value)) {
            $name = $field['name'] ?? $field['id'] ?? 'This field';

            return $name . ' is required.';
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function sanitize(array $field, $value)
    {
        return sanitize_text_field($value);
    }

    /**
     * Render the label element.
     */
    protected function renderLabel(array $field, string $formId): string
    {
        if (empty($field['name'])) {
            return '';
        }

        $inputId = $this->getInputId($field, $formId);
        $required = !empty($field['required']);

        $html = '<label for="' . esc_attr($inputId) . '">';
        $html .= esc_html($field['name']);

        if ($required) {
            $html .= ' <span class="oriel-field__required">*</span>';
        }

        $html .= '</label>';

        return $html;
    }

    /**
     * Render the description text below the input.
     */
    protected function renderDescription(array $field): string
    {
        if (empty($field['desc'])) {
            return '';
        }

        return '<p class="oriel-field__desc">' . esc_html($field['desc']) . '</p>';
    }

    /**
     * Render the error placeholder.
     */
    protected function renderError(array $field): string
    {
        $id = $field['id'] ?? '';

        return '<div class="oriel-field__error" data-error-for="' . esc_attr($id) . '"></div>';
    }

    /**
     * Build the input element's id attribute value.
     */
    protected function getInputId(array $field, string $formId): string
    {
        return 'oriel_' . $formId . '_' . ($field['id'] ?? '');
    }

    /**
     * Build the input element's name attribute value.
     */
    protected function getInputName(array $field): string
    {
        return 'oriel[' . ($field['id'] ?? '') . ']';
    }

    /**
     * Check whether a value is considered empty.
     *
     * @param mixed $value
     * @return bool
     */
    protected function isEmpty($value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if (is_array($value) && count($value) === 0) {
            return true;
        }

        return false;
    }

    /**
     * Build an HTML attributes string from a field configuration.
     *
     * Includes id, name, placeholder, required, and any extras from
     * the field's `attributes` array.
     */
    protected function buildAttributes(array $field, string $formId, array $extra = []): string
    {
        $attrs = array_merge([
            'id'   => $this->getInputId($field, $formId),
            'name' => $this->getInputName($field),
        ], $extra);

        if (!empty($field['placeholder'])) {
            $attrs['placeholder'] = $field['placeholder'];
        }

        if (!empty($field['required'])) {
            $attrs['required'] = 'required';
        }

        // Merge any custom attributes from the field config.
        if (!empty($field['attributes']) && is_array($field['attributes'])) {
            $attrs = array_merge($attrs, $field['attributes']);
        }

        $parts = [];

        foreach ($attrs as $key => $attrValue) {
            if ($attrValue === true) {
                $parts[] = esc_attr($key);
            } else {
                $parts[] = esc_attr($key) . '="' . esc_attr($attrValue) . '"';
            }
        }

        return implode(' ', $parts);
    }
}
