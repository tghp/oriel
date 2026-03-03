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

        $wrapperClass = apply_filters('oriel_form_wrapper_class', $wrapperClass, $formId, $this->config);

        // Start building HTML.
        $html = '<div class="' . $wrapperClass . '">';

        // Optional title from args.
        if (!empty($this->args['title'])) {
            $titleClass = apply_filters('oriel_form_title_class', 'oriel-form__title', $formId, $this->config);
            $html .= '<h3 class="' . $titleClass . '">' . esc_html($this->args['title']) . '</h3>';
        }

        // Success message when ?oriel-submitted={formId} is present.
        if ($this->isSubmitted()) {
            $confirmation = $options['confirmation'] ?? 'Your submission has been received.';
            $successClass = apply_filters('oriel_form_message_class', 'oriel-form__message oriel-form__message--success', $formId, 'success');
            $html .= '<div class="' . $successClass . '">';
            $html .= esc_html($confirmation);
            $html .= '</div>';
        }

        // Error message when ?oriel-errors={formId} is present.
        if ($this->hasErrors()) {
            $errorClass = apply_filters('oriel_form_message_class', 'oriel-form__message oriel-form__message--error', $formId, 'error');
            $html .= '<div class="' . $errorClass . '">';
            $html .= 'There were errors with your submission. Please correct them and try again.';
            $html .= '</div>';
        }

        do_action('oriel_form_before', $formId, $this->config);

        // Opening form.
        $formElementClass = apply_filters('oriel_form_element_class', 'oriel-form__form', $formId, $this->config);
        $html .= '<form method="post" class="' . $formElementClass . '" enctype="multipart/form-data">';
        $html .= '<input type="hidden" name="oriel_form_id" value="' . esc_attr($formId) . '">';
        $html .= wp_nonce_field('oriel_submit_' . $formId, '_oriel_nonce', true, false);

        $useFormFieldsWrapper = apply_filters('oriel_form_use_fields_wrapper', true, $formId, $this->config);

        if ($useFormFieldsWrapper) {
            // Opening wrapper for form fields.
            $formFieldsWrapperClass = apply_filters('oriel_form_fields_wrapper_class', 'oriel-form__fields', $formId, $this->config);
            $html .= '<div class="' . $formFieldsWrapperClass . '">';
        }

        $html .= apply_filters('oriel_form_fields_before', '', $formId, $this->config);

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
                $fieldHtml = preg_replace(
                    '/(<div[^>]*data-error-for="' . preg_quote(esc_attr($fieldId), '/') . '"[^>]*>)<\/div>/',
                    '$1' . esc_html($field['error']) . '</div>',
                    $fieldHtml
                );
            }

            $fieldHtml = apply_filters('oriel_field_html', $fieldHtml, $field, $formId);

            $html .= $fieldHtml;
        }

        $html .= apply_filters('oriel_form_fields_after', '', $formId, $this->config);

        // Submit button.
        $submitText = $options['submit_text'] ?? 'Submit';
        $submitClass = $options['submit_class'] ?? '';

        $submitWrapperClass = apply_filters('oriel_form_submit_class', 'oriel-form__submit', $formId, $this->config);
        $submitHtml = '<div class="' . $submitWrapperClass . '">';
        $submitHtml .= '<button type="submit"';

        if ($submitClass) {
            $submitHtml .= ' class="' . esc_attr($submitClass) . '"';
        }

        $submitHtml .= '>' . esc_html($submitText) . '</button>';
        $submitHtml .= '</div>';

        $submitHtml = apply_filters('oriel_submit_button', $submitHtml, $formId, $this->config);

        $html .= apply_filters('oriel_form_submit_before', '', $formId, $this->config);
        $html .= $submitHtml;
        $html .= apply_filters('oriel_form_submit_after', '', $formId, $this->config);

        if ($useFormFieldsWrapper) {
            // Closing wrapper for form fields.
            $html .= '</div>';
        }

        // Closing form.
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

        $toggleClass = 'oriel-form__toggle' . ($buttonClass ? ' ' . esc_attr($buttonClass) : '');
        $toggleClass = apply_filters('oriel_form_toggle_class', $toggleClass, $this->formId);

        $html = '<button type="button"';
        $html .= ' class="' . $toggleClass . '"';
        $html .= ' aria-expanded="false"';
        $html .= ' aria-controls="' . $id . '"';
        $html .= '>' . esc_html($buttonLabel) . '</button>';

        $hiddenClass = apply_filters('oriel_form_hidden_class', 'oriel-form__hidden', $this->formId);
        $html .= '<div id="' . $id . '" class="' . $hiddenClass . '" hidden>';
        $html .= $formHtml;
        $html .= '</div>';

        return $html;
    }
}
