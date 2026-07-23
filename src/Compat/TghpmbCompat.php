<?php

namespace Oriel\Compat;

class TghpmbCompat
{
    /**
     * @var array<string, array> Form configs keyed by form ID.
     */
    private $forms = [];

    /**
     * @param array<string, array> $forms Compat-enabled form configs keyed by ID.
     */
    public function __construct(array $forms)
    {
        $this->forms = $forms;
        $this->registerHooks();
    }

    private function isCompat(string $formId): bool
    {
        return isset($this->forms[$formId]);
    }

    /**
     * Map Oriel field types to their tghpmb/Meta Box equivalents for class names.
     */
    private function aliasType(string $type): string
    {
        if ($type === 'captcha') {
            return 'recaptcha';
        }

        return $type;
    }

    /**
     * Get the tghpmb field prefix for a form.
     * Falls back to '_tghp{formId}_' if not explicitly set.
     */
    private function getPrefix(string $formId): string
    {
        $config = $this->forms[$formId] ?? [];
        $prefix = $config['options']['compat_prefix'] ?? '';

        if ($prefix) {
            return $prefix;
        }

        return '_tghp' . esc_attr($formId) . '_';
    }

    private function registerHooks(): void
    {
        add_action('oriel_form_render', [$this, 'enqueueAssets']);

        // Form-level.
        add_filter('oriel_form_wrapper_class', [$this, 'filterWrapperClass'], 10, 3);
        add_filter('oriel_form_wrapper_id', [$this, 'filterWrapperId'], 10, 3);
        add_filter('oriel_form_element_class', [$this, 'filterFormElementClass'], 10, 3);
        add_filter('oriel_form_element_id', [$this, 'filterFormElementId'], 10, 3);
        add_filter('oriel_form_fields_wrapper_class', [$this, 'filterFieldsWrapperClass'], 10, 3);
        add_filter('oriel_form_fields_wrapper_attrs', [$this, 'filterFieldsWrapperAttrs'], 10, 3);

        // Field-level.
        add_filter('oriel_field_wrapper_class', [$this, 'filterFieldWrapperClass'], 10, 3);
        add_filter('oriel_field_label_wrapper_class', [$this, 'filterLabelWrapperClass'], 10, 3);
        add_filter('oriel_field_label_wrapper_attrs', [$this, 'filterLabelWrapperAttrs'], 10, 3);
        add_filter('oriel_field_label_class', [$this, 'filterLabelClass'], 10, 3);
        add_filter('oriel_field_required_class', [$this, 'filterRequiredClass'], 10, 3);
        add_filter('oriel_field_input_wrapper_class', [$this, 'filterInputWrapperClass'], 10, 3);
        add_filter('oriel_field_input_class', [$this, 'filterInputClass'], 10, 3);
        add_filter('oriel_field_input_attrs', [$this, 'filterInputAttrs'], 10, 3);

        // Submit-level.
        add_filter('oriel_form_submit_class', [$this, 'filterSubmitClass'], 10, 3);
        add_filter('oriel_form_submit_inner_class', [$this, 'filterSubmitInnerClass'], 10, 3);
        add_filter('oriel_form_submit_button_attrs', [$this, 'filterSubmitButtonAttrs'], 10, 3);
    }

    /**
     * Enqueued at render time (not wp_enqueue_scripts) so the stylesheet
     * prints in the footer after the theme CSS, matching where Meta Box's
     * own late-enqueued styles land in the cascade.
     */
    public function enqueueAssets(string $formId): void
    {
        if (!$this->isCompat($formId)) {
            return;
        }

        wp_enqueue_style(
            'oriel-compat-tghpmb',
            ORIEL_PLUGIN_URL . 'assets/compat/tghpmb.css',
            [],
            ORIEL_VERSION
        );
    }

    // ── Form-level ──────────────────────────────────────────────────

    public function filterWrapperClass(string $class, string $formId, array $config): string
    {
        if (!$this->isCompat($formId)) {
            return $class;
        }

        return 'tghpform tghpform--' . esc_attr($formId);
    }

    public function filterWrapperId(string $id, string $formId, array $config): string
    {
        if (!$this->isCompat($formId)) {
            return $id;
        }

        return '';
    }

    public function filterFormElementClass(string $class, string $formId, array $config): string
    {
        if (!$this->isCompat($formId)) {
            return $class;
        }

        return 'rwmb-form mbfs-form';
    }

    public function filterFormElementId(string $id, string $formId, array $config): string
    {
        if (!$this->isCompat($formId)) {
            return $id;
        }

        return $formId;
    }

    public function filterFieldsWrapperClass(string $class, string $formId, array $config): string
    {
        if (!$this->isCompat($formId)) {
            return $class;
        }

        return 'rwmb-form-fields form';
    }

    public function filterFieldsWrapperAttrs(array $attrs, string $formId, array $config): array
    {
        if (!$this->isCompat($formId)) {
            return $attrs;
        }

        $attrs['id'] = 'form_' . esc_attr($formId);

        return $attrs;
    }

    // ── Field-level ─────────────────────────────────────────────────

    public function filterFieldWrapperClass(string $class, array $field, string $formId): string
    {
        if (!$this->isCompat($formId)) {
            return $class;
        }

        $type = $this->aliasType($field['type'] ?? 'text');
        $fieldId = $field['id'] ?? '';

        $classes = 'rwmb-field rwmb-' . esc_attr($type) . '-wrapper field-' . esc_attr($fieldId);

        if (!empty($field['required'])) {
            $classes .= ' required';
        }

        return $classes;
    }

    public function filterLabelWrapperClass(string $class, array $field, string $formId): string
    {
        if (!$this->isCompat($formId)) {
            return $class;
        }

        return 'rwmb-label';
    }

    public function filterLabelWrapperAttrs(array $attrs, array $field, string $formId): array
    {
        if (!$this->isCompat($formId)) {
            return $attrs;
        }

        $fieldId = $field['id'] ?? '';
        $prefix = $this->getPrefix($formId);
        $attrs['id'] = $prefix . esc_attr($fieldId) . '-label';

        return $attrs;
    }

    public function filterLabelClass(string $class, array $field, string $formId): string
    {
        if (!$this->isCompat($formId)) {
            return $class;
        }

        return '';
    }

    public function filterRequiredClass(string $class, array $field, string $formId): string
    {
        if (!$this->isCompat($formId)) {
            return $class;
        }

        return 'rwmb-required';
    }

    public function filterInputWrapperClass(string $class, array $field, string $formId): string
    {
        if (!$this->isCompat($formId)) {
            return $class;
        }

        return 'rwmb-input';
    }

    public function filterInputClass(string $class, array $field, string $formId): string
    {
        if (!$this->isCompat($formId)) {
            return $class;
        }

        $type = $this->aliasType($field['type'] ?? 'text');

        return 'rwmb-' . esc_attr($type);
    }

    public function filterInputAttrs(array $attrs, array $field, string $formId): array
    {
        if (!$this->isCompat($formId)) {
            return $attrs;
        }

        $fieldId = $field['id'] ?? '';
        $prefix = $this->getPrefix($formId);
        $attrs['aria-labelledby'] = $prefix . esc_attr($fieldId) . '-label';

        return $attrs;
    }

    // ── Submit-level ────────────────────────────────────────────────

    public function filterSubmitClass(string $class, string $formId, array $config): string
    {
        if (!$this->isCompat($formId)) {
            return $class;
        }

        return 'rwmb-field rwmb-button-wrapper rwmb-form-submit';
    }

    public function filterSubmitInnerClass(string $class, string $formId, array $config): string
    {
        if (!$this->isCompat($formId)) {
            return $class;
        }

        return 'rwmb-input';
    }

    public function filterSubmitButtonAttrs(array $attrs, string $formId, array $config): array
    {
        if (!$this->isCompat($formId)) {
            return $attrs;
        }

        $attrs['name'] = 'rwmb_submit';
        $attrs['value'] = '1';

        return $attrs;
    }
}
