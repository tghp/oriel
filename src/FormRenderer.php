<?php

namespace Oriel;

class FormRenderer
{
    /**
     * @var array Form configuration from the registry.
     */
    private $config;

    /**
     * @var array Display arguments (from shortcode or direct call).
     */
    private $args;

    /**
     * @var string The form identifier.
     */
    private $formId;

    /**
     * @param array $config Form configuration array (from FormRegistry::get()).
     * @param array $args   Optional display/shortcode arguments.
     */
    public function __construct(array $config, array $args = [])
    {
        $this->config = $config;
        $this->args = $args;
        $this->formId = $args['id'] ?? '';
    }

    /**
     * Render the full form HTML.
     */
    public function render(): string
    {
        $formId = $this->formId;
        $options = $this->config['options'] ?? [];
        $fields = $this->config['fields'] ?? [];

        // Load stored state (values + errors) from a previous failed submission.
        $state = $this->getStoredState();
        $storedValues = $state['values'] ?? [];
        $storedErrors = $state['errors'] ?? [];

        // Build wrapper classes.
        $wrapperClass = 'oriel-form oriel-form--' . esc_attr($formId);

        if (!empty($options['class'])) {
            $wrapperClass .= ' ' . esc_attr($options['class']);
        }

        // Start building HTML.
        $html = '<div class="' . $wrapperClass . '">';

        // Optional title from args.
        if (!empty($this->args['title'])) {
            $html .= '<h3 class="oriel-form__title">' . esc_html($this->args['title']) . '</h3>';
        }

        // Success message when ?oriel-submitted={formId} is present.
        if ($this->isSubmitted()) {
            $confirmation = $options['confirmation'] ?? 'Your submission has been received.';
            $html .= '<div class="oriel-form__message oriel-form__message--success">';
            $html .= esc_html($confirmation);
            $html .= '</div>';
        }

        // Error message when ?oriel-errors={formId} is present.
        if ($this->hasErrors()) {
            $html .= '<div class="oriel-form__message oriel-form__message--error">';
            $html .= 'There were errors with your submission. Please correct them and try again.';
            $html .= '</div>';
        }

        do_action('oriel_form_before', $formId, $this->config);

        $html .= '<form method="post" class="oriel-form__form" enctype="multipart/form-data">';
        $html .= '<input type="hidden" name="oriel_form_id" value="' . esc_attr($formId) . '">';
        $html .= wp_nonce_field('oriel_submit_' . $formId, '_oriel_nonce', true, false);

        // Render each field.
        foreach ($fields as $field) {
            $type = $field['type'] ?? 'text';
            $fieldId = $field['id'] ?? '';

            $instance = Plugin::instance()->getFieldInstance($type);

            if (!$instance) {
                continue;
            }

            // Resolve the field value: stored state takes priority, then `std` default.
            $value = null;

            if (isset($storedValues[$fieldId])) {
                $value = $storedValues[$fieldId];
            } elseif (isset($field['std'])) {
                $value = is_callable($field['std']) ? call_user_func($field['std']) : $field['std'];
            }

            // Attach stored error to the field config for rendering.
            if (isset($storedErrors[$fieldId])) {
                $field['error'] = $storedErrors[$fieldId];
            }

            $fieldHtml = $instance->render($field, $value, $formId);

            // If there's an error, inject it into the error placeholder div.
            if (!empty($field['error'])) {
                $errorFor = 'data-error-for="' . esc_attr($fieldId) . '"';
                $fieldHtml = str_replace(
                    '<div class="oriel-field__error" ' . $errorFor . '></div>',
                    '<div class="oriel-field__error" ' . $errorFor . '>' . esc_html($field['error']) . '</div>',
                    $fieldHtml
                );
            }

            $fieldHtml = apply_filters('oriel_field_html', $fieldHtml, $field, $formId);

            $html .= $fieldHtml;
        }

        // Submit button.
        $submitText = $options['submit_text'] ?? 'Submit';
        $submitClass = $options['submit_class'] ?? '';

        $submitHtml = '<div class="oriel-form__submit">';
        $submitHtml .= '<button type="submit"';

        if ($submitClass) {
            $submitHtml .= ' class="' . esc_attr($submitClass) . '"';
        }

        $submitHtml .= '>' . esc_html($submitText) . '</button>';
        $submitHtml .= '</div>';

        $submitHtml = apply_filters('oriel_submit_button', $submitHtml, $formId, $this->config);

        $html .= $submitHtml;
        $html .= '</form>';

        do_action('oriel_form_after', $formId, $this->config);

        $html .= '</div>';

        // Wrap in hide pattern if requested.
        if (!empty($this->args['hide'])) {
            $html = $this->wrapHidden($html);
        }

        // Clear stored state after rendering so it's only used once.
        if ($state) {
            $this->clearStoredState();
        }

        return apply_filters('oriel_form_html', $html, $formId, $this->config, $this->args);
    }

    /**
     * Check if the form was successfully submitted (via query param).
     */
    private function isSubmitted(): bool
    {
        return isset($_GET['oriel-submitted']) && $_GET['oriel-submitted'] === $this->formId;
    }

    /**
     * Check if the form has errors (via query param).
     */
    private function hasErrors(): bool
    {
        return isset($_GET['oriel-errors']) && $_GET['oriel-errors'] === $this->formId;
    }

    /**
     * Build the transient key for stored form state.
     */
    private function getTransientKey(): string
    {
        $formId = $this->formId;

        if (is_user_logged_in()) {
            $userId = get_current_user_id();

            return 'oriel_state_' . $userId . '_' . $formId;
        }

        // For guests, use the PHP session ID.
        if (!session_id()) {
            session_start();
        }

        return 'oriel_state_' . session_id() . '_' . $formId;
    }

    /**
     * Retrieve stored form state (values + errors) from a transient.
     *
     * @return array|null Array with 'values' and 'errors' keys, or null.
     */
    private function getStoredState(): ?array
    {
        $key = $this->getTransientKey();
        $state = get_transient($key);

        if (!is_array($state)) {
            return null;
        }

        return $state;
    }

    /**
     * Clear stored form state after rendering.
     */
    private function clearStoredState(): void
    {
        $key = $this->getTransientKey();
        delete_transient($key);
    }

    /**
     * Wrap the form HTML in a toggle button + hidden div pattern.
     */
    private function wrapHidden(string $formHtml): string
    {
        $buttonLabel = $this->args['hide_button_label'] ?? 'Show Form';
        $buttonClass = $this->args['hide_button_class'] ?? '';

        $id = 'oriel-toggle-' . esc_attr($this->formId);

        $html = '<button type="button"';
        $html .= ' class="oriel-form__toggle' . ($buttonClass ? ' ' . esc_attr($buttonClass) : '') . '"';
        $html .= ' aria-expanded="false"';
        $html .= ' aria-controls="' . $id . '"';
        $html .= '>' . esc_html($buttonLabel) . '</button>';

        $html .= '<div id="' . $id . '" class="oriel-form__hidden" hidden>';
        $html .= $formHtml;
        $html .= '</div>';

        return $html;
    }
}
