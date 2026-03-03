<?php

namespace Oriel\Field;

use Oriel\Util;

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
        $id = $this->getInputId($field, $formId);
        $extraClass = $field['class'] ?? '';

        $classes = 'oriel-field oriel-field--' . esc_attr($type) . ' oriel-field--' . esc_attr($id);

        if ($extraClass) {
            $classes .= ' ' . esc_attr($extraClass);
        }

        $classes = apply_filters('oriel_field_wrapper_class', $classes, $field, $formId);

        $inputWrapperClass = apply_filters('oriel_field_input_wrapper_class', 'oriel-field__input', $field, $formId);

        $html = '<div class="' . $classes . '">';
        $html .= $this->renderLabel($field, $formId);
        $html .= '<div class="' . $inputWrapperClass . '">';
        $html .= $this->renderInput($field, $value, $formId);
        $html .= '</div>';
        $html .= $this->renderDescription($field, $formId);
        $html .= $this->renderError($field, $formId);
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
            $name = $field['name'] ?? 'This field';

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

        $html = '';

        $inputId = $this->getInputId($field, $formId);
        $required = !empty($field['required']);

        $useLabelWrapper = apply_filters('oriel_field_use_label_wrapper', true, $field, $formId);
        
        if ($useLabelWrapper) {
            $wrapperClasses = apply_filters('oriel_field_label_wrapper_class', 'oriel-field__label-wrapper', $field, $formId);
            $wrapperAttrs = apply_filters('oriel_field_label_wrapper_attrs', [], $field, $formId);
            $html .= '<div class="' . $wrapperClasses . '"';
            $html .= $this->renderExtraAttributes($wrapperAttrs);
            $html .= '>';
        }

        $labelClasses = apply_filters('oriel_field_label_class', 'oriel-field__label', $field, $formId);

        $labelId = $inputId . '-label';

        $html .= '<label for="' . esc_attr($inputId) . '" id="' . $labelId . '" class="' . $labelClasses . '">';
        $html .= esc_html($field['name']);

        if ($required) {
            $requiredSymbol = apply_filters('oriel_field_required_symbol', '*', $field, $formId);
            $requiredClass = apply_filters('oriel_field_required_class', 'oriel-field__required', $field, $formId);
            $html .= ' <span class="' . $requiredClass . '">' . $requiredSymbol . '</span>';
        }

        $html .= '</label>';

        if ($useLabelWrapper) {
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Render the description text below the input.
     */
    protected function renderDescription(array $field, string $formId): string
    {
        if (empty($field['desc'])) {
            return '';
        }

        $descClass = apply_filters('oriel_field_description_class', 'oriel-field__desc', $field, $formId);
        return '<p class="' . $descClass . '">' . esc_html($field['desc']) . '</p>';
    }

    /**
     * Render the error placeholder.
     */
    protected function renderError(array $field, string $formId): string
    {
        $id = $this->getInputId($field, $formId);
        $errorClass = apply_filters('oriel_field_error_class', 'oriel-field__error', $field, $formId);

        return '<div class="' . $errorClass . '" data-error-for="' . esc_attr($id) . '"></div>';
    }

    /**
     * Build the input element's id attribute value.
     */
    protected function getInputId(array $field, string $formId): string
    {
        $fieldId = $field['id'];

        if (!$fieldId) {
            $fieldId = Util::slugify($field['name']);
        }

        if (!$fieldId) {
            $randomId = wp_rand(1000, 9999);
            $fieldId = 'oriel_' . $randomId;
        }

        return 'oriel_' . esc_attr($fieldId);
    }

    /**
     * Build the input element's name attribute value.
     */
    protected function getInputName(array $field, string $formId): string
    {
        return 'oriel[' . $this->getInputId($field, $formId) . ']';
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
            'name' => $this->getInputName($field, $formId),
        ], $extra);

        if (!empty($field['placeholder'])) {
            $attrs['placeholder'] = $field['placeholder'];
        }

        if (!empty($field['required'])) {
            $attrs['required'] = 'required';
        }

        $inputClass = apply_filters('oriel_field_input_class', '', $field, $formId);

        if ($inputClass) {
            $attrs['class'] = $inputClass;
        }

        // Merge any custom attributes from the field config.
        if (!empty($field['attributes']) && is_array($field['attributes'])) {
            $attrs = array_merge($attrs, $field['attributes']);
        }

        // Merge extra attributes from filter.
        $extraAttrs = apply_filters('oriel_field_input_attrs', [], $field, $formId);

        if (!empty($extraAttrs) && is_array($extraAttrs)) {
            $attrs = array_merge($attrs, $extraAttrs);
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

    /**
     * Render an associative array as HTML attributes string (with leading space).
     */
    protected function renderExtraAttributes(array $attrs): string
    {
        $parts = [];

        foreach ($attrs as $key => $value) {
            if ($value === true) {
                $parts[] = esc_attr($key);
            } elseif ($value !== false && $value !== null) {
                $parts[] = esc_attr($key) . '="' . esc_attr($value) . '"';
            }
        }

        if (empty($parts)) {
            return '';
        }

        return ' ' . implode(' ', $parts);
    }
}
