# tghpmb Compat Mode Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add 12 new output filters + core structural DOM changes + a TghpmbCompat hook module that achieves CSS parity with Meta Box frontend submission output.

**Architecture:** Core rendering files (FormRenderer, AbstractField) gain new filter hooks and structural changes (input wrapper div, submit repositioning, form id/attrs). A pure-hooks `TghpmbCompat` class registers callbacks that swap classes/attrs for compat-enabled forms. Compat activated per-form via `'compat' => 'tghpmb'` option.

**Tech Stack:** PHP 7.4+, WordPress filters API, PSR-4 autoloading.

**Design doc:** `docs/plans/2026-03-03-tghpmb-compat-design.md`

---

### Task 1: Add id + attrs to form wrapper and form element (FormRenderer)

**Files:**
- Modify: `src/plugins/oriel/src/FormRenderer.php:47-57` (wrapper div)
- Modify: `src/plugins/oriel/src/FormRenderer.php:84-86` (form element)

**Step 1: Add id attribute + filter to outer wrapper div**

In `FormRenderer::render()`, after the wrapper class filter (line 54), add wrapper id filter and render it on the div. Replace lines 47-57:

```php
// Build wrapper classes.
$wrapperClass = 'oriel-form oriel-form--' . esc_attr($formId);

if (!empty($options['class'])) {
    $wrapperClass .= ' ' . esc_attr($options['class']);
}

$wrapperClass = apply_filters('oriel_form_wrapper_class', $wrapperClass, $formId, $this->config);

$wrapperId = apply_filters('oriel_form_wrapper_id', 'oriel-' . esc_attr($formId), $formId, $this->config);

// Start building HTML.
$html = '<div';

if ($wrapperId) {
    $html .= ' id="' . esc_attr($wrapperId) . '"';
}

$html .= ' class="' . $wrapperClass . '">';
```

**Step 2: Add id + attrs to form element**

Replace lines 84-86:

```php
// Opening form.
$formElementClass = apply_filters('oriel_form_element_class', 'oriel-form__form', $formId, $this->config);
$formElementId = apply_filters('oriel_form_element_id', 'oriel-form-' . esc_attr($formId), $formId, $this->config);

$formElementAttrs = apply_filters('oriel_form_element_attrs', [
    'novalidate'   => 'novalidate',
    'autocomplete' => 'off',
], $formId, $this->config);

$html .= '<form method="post"';

if ($formElementId) {
    $html .= ' id="' . esc_attr($formElementId) . '"';
}

$html .= ' class="' . $formElementClass . '" enctype="multipart/form-data"';
$html .= $this->renderAttributes($formElementAttrs);
$html .= '>';
```

**Step 3: Add `renderAttributes` helper to FormRenderer**

Add after `renderSecurityFields()` method (after line 222):

```php
/**
 * Render an associative array as HTML attributes string.
 */
private function renderAttributes(array $attrs): string
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
```

**Step 4: Commit**

```bash
git add src/plugins/oriel/src/FormRenderer.php
git commit -m "feat(oriel): add id and attrs filters to form wrapper and form element"
```

---

### Task 2: Add fields wrapper attrs filter (FormRenderer)

**Files:**
- Modify: `src/plugins/oriel/src/FormRenderer.php:92-96` (fields wrapper opening)

**Step 1: Add attrs filter to fields wrapper div**

Replace lines 92-96:

```php
if ($useFormFieldsWrapper) {
    // Opening wrapper for form fields.
    $formFieldsWrapperClass = apply_filters('oriel_form_fields_wrapper_class', 'oriel-form__fields', $formId, $this->config);
    $formFieldsWrapperAttrs = apply_filters('oriel_form_fields_wrapper_attrs', [], $formId, $this->config);
    $html .= '<div class="' . $formFieldsWrapperClass . '"';
    $html .= $this->renderAttributes($formFieldsWrapperAttrs);
    $html .= '>';
}
```

**Step 2: Commit**

```bash
git add src/plugins/oriel/src/FormRenderer.php
git commit -m "feat(oriel): add attrs filter to fields wrapper"
```

---

### Task 3: Move submit outside fields wrapper (FormRenderer)

**Files:**
- Modify: `src/plugins/oriel/src/FormRenderer.php:141-167` (submit + fields wrapper close)

**Step 1: Reorder submit and fields wrapper closing**

Currently (lines 141-167): fields_after → submit → fields wrapper close.
Target: fields_after → fields wrapper close → submit.

Replace lines 141-170:

```php
$html .= apply_filters('oriel_form_fields_after', '', $formId, $this->config);

if ($useFormFieldsWrapper) {
    // Closing wrapper for form fields.
    $html .= '</div>';
}

// Submit button.
$submitText = $options['submit_text'] ?? 'Submit';
$submitClass = $options['submit_class'] ?? '';

$submitWrapperClass = apply_filters('oriel_form_submit_class', 'oriel-form__submit', $formId, $this->config);
$submitInnerClass = apply_filters('oriel_form_submit_inner_class', 'oriel-form__submit-input', $formId, $this->config);

$submitButtonAttrs = apply_filters('oriel_form_submit_button_attrs', [], $formId, $this->config);

$submitHtml = '<div class="' . $submitWrapperClass . '">';
$submitHtml .= '<div class="' . $submitInnerClass . '">';
$submitHtml .= '<button type="submit"';

if ($submitClass) {
    $submitHtml .= ' class="' . esc_attr($submitClass) . '"';
}

$submitHtml .= $this->renderAttributes($submitButtonAttrs);
$submitHtml .= '>' . esc_html($submitText) . '</button>';
$submitHtml .= '</div>';
$submitHtml .= '</div>';

$submitHtml = apply_filters('oriel_submit_button', $submitHtml, $formId, $this->config);

$html .= apply_filters('oriel_form_submit_before', '', $formId, $this->config);
$html .= $submitHtml;
$html .= apply_filters('oriel_form_submit_after', '', $formId, $this->config);

// Closing form.
$html .= '</form>';
```

Also remove the now-redundant fields wrapper close and the old submit block that was between it (the old lines 164-170 which closed the wrapper after submit).

**Step 2: Commit**

```bash
git add src/plugins/oriel/src/FormRenderer.php
git commit -m "feat(oriel): move submit outside fields wrapper, add submit inner wrapper + button attrs filters"
```

---

### Task 4: Add input wrapper div to AbstractField

**Files:**
- Modify: `src/plugins/oriel/src/Field/AbstractField.php:27-49` (render method)

**Step 1: Wrap renderInput() output in a filterable div**

Replace lines 27-49:

```php
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
```

**Step 2: Commit**

```bash
git add src/plugins/oriel/src/Field/AbstractField.php
git commit -m "feat(oriel): add input wrapper div with oriel_field_input_wrapper_class filter"
```

---

### Task 5: Add input class, input attrs, and label wrapper attrs filters to AbstractField

**Files:**
- Modify: `src/plugins/oriel/src/Field/AbstractField.php:78-116` (renderLabel)
- Modify: `src/plugins/oriel/src/Field/AbstractField.php:198-229` (buildAttributes)

**Step 1: Add label wrapper attrs filter**

In `renderLabel()`, after the label wrapper class filter (line 92-93), add attrs. Replace lines 91-94:

```php
if ($useLabelWrapper) {
    $wrapperClasses = apply_filters('oriel_field_label_wrapper_class', 'oriel-field__label-wrapper', $field, $formId);
    $wrapperAttrs = apply_filters('oriel_field_label_wrapper_attrs', [], $field, $formId);
    $html .= '<div class="' . $wrapperClasses . '"';
    $html .= $this->renderExtraAttributes($wrapperAttrs);
    $html .= '>';
}
```

**Step 2: Add input class and input attrs filters to buildAttributes()**

In `buildAttributes()`, add the `oriel_field_input_class` and `oriel_field_input_attrs` filters. Replace lines 198-229:

```php
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
```

**Step 3: Add `renderExtraAttributes` helper to AbstractField**

Add after `buildAttributes()`:

```php
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
```

**Step 4: Commit**

```bash
git add src/plugins/oriel/src/Field/AbstractField.php
git commit -m "feat(oriel): add input class, input attrs, and label wrapper attrs filters"
```

---

### Task 6: Add input id and input name filters to AbstractField

**Files:**
- Modify: `src/plugins/oriel/src/Field/AbstractField.php:145-167` (getInputId, getInputName)

**Step 1: Add filters to getInputId and getInputName**

Replace lines 145-167:

```php
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

    $id = 'oriel_' . esc_attr($fieldId);

    return apply_filters('oriel_field_input_id', $id, $field, $formId);
}

protected function getInputName(array $field, string $formId): string
{
    $name = 'oriel[' . $this->getInputId($field, $formId) . ']';

    return apply_filters('oriel_field_input_name', $name, $field, $formId);
}
```

**Step 2: Fix CheckboxField and RadioField to pass $formId to getInputName**

In `src/plugins/oriel/src/Field/CheckboxField.php:41`, change:
```php
$inputName = $this->getInputName($field);
```
to:
```php
$inputName = $this->getInputName($field, $formId);
```

In `src/plugins/oriel/src/Field/RadioField.php:15`, change:
```php
$inputName = $this->getInputName($field);
```
to:
```php
$inputName = $this->getInputName($field, $formId);
```

**Step 3: Commit**

```bash
git add src/plugins/oriel/src/Field/AbstractField.php \
        src/plugins/oriel/src/Field/CheckboxField.php \
        src/plugins/oriel/src/Field/RadioField.php
git commit -m "feat(oriel): add input id and name filters, fix getInputName call sites"
```

---

### Task 7: Add `compat` to form option defaults (FormRegistry)

**Files:**
- Modify: `src/plugins/oriel/src/FormRegistry.php:15-24` (OPTION_DEFAULTS)

**Step 1: Add compat to defaults**

Replace lines 15-24:

```php
private const OPTION_DEFAULTS = [
    'redirect'                => '',
    'confirmation'            => '',
    'ajax'                    => false,
    'email'                   => null,
    'delete_after_processing' => false,
    'class'                   => '',
    'submit_class'            => '',
    'submit_text'             => 'Submit',
    'compat'                  => '',
];
```

**Step 2: Commit**

```bash
git add src/plugins/oriel/src/FormRegistry.php
git commit -m "feat(oriel): add compat option to form defaults"
```

---

### Task 8: Create TghpmbCompat class

**Files:**
- Create: `src/plugins/oriel/src/Compat/TghpmbCompat.php`

**Step 1: Create the compat class**

```php
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

    /**
     * Check if a form ID has tghpmb compat enabled.
     */
    private function isCompat(string $formId): bool
    {
        return isset($this->forms[$formId]);
    }

    /**
     * Register all output filter hooks.
     */
    private function registerHooks(): void
    {
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

    // ── Form-level filters ──────────────────────────────────────────

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

    // ── Field-level filters ─────────────────────────────────────────

    public function filterFieldWrapperClass(string $class, array $field, string $formId): string
    {
        if (!$this->isCompat($formId)) {
            return $class;
        }

        $type = $field['type'] ?? 'text';
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
        $attrs['id'] = '_tghpcontact_' . esc_attr($fieldId) . '-label';

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

        $type = $field['type'] ?? 'text';

        return 'rwmb-' . esc_attr($type);
    }

    public function filterInputAttrs(array $attrs, array $field, string $formId): array
    {
        if (!$this->isCompat($formId)) {
            return $attrs;
        }

        $fieldId = $field['id'] ?? '';
        $attrs['aria-labelledby'] = '_tghpcontact_' . esc_attr($fieldId) . '-label';

        return $attrs;
    }

    // ── Submit-level filters ────────────────────────────────────────

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
```

**Step 2: Commit**

```bash
git add src/plugins/oriel/src/Compat/TghpmbCompat.php
git commit -m "feat(oriel): add TghpmbCompat class with full hook registrations"
```

---

### Task 9: Wire TghpmbCompat into Plugin

**Files:**
- Modify: `src/plugins/oriel/src/Plugin.php:45-53` (onInit)

**Step 1: Initialize compat after registry**

Replace lines 45-53:

```php
public function onInit(): void
{
    PostType::register();

    $this->registry = new FormRegistry();

    $this->initCompat();

    add_shortcode('oriel_form', [$this, 'shortcode']);
    add_action('template_redirect', [$this, 'handleSubmission']);
}
```

**Step 2: Add initCompat method**

Add after `onInit()`:

```php
/**
 * Scan registry for compat-enabled forms and initialize compat modules.
 */
private function initCompat(): void
{
    $tghpmbForms = [];

    foreach ($this->registry->all() as $id => $config) {
        $compat = $config['options']['compat'] ?? '';

        if ($compat === 'tghpmb') {
            $tghpmbForms[$id] = $config;
        }
    }

    if (!empty($tghpmbForms)) {
        new Compat\TghpmbCompat($tghpmbForms);
    }
}
```

**Step 3: Commit**

```bash
git add src/plugins/oriel/src/Plugin.php
git commit -m "feat(oriel): wire TghpmbCompat initialization into Plugin"
```

---

### Task 10: Handle label wrapper id prefix from form config

The current TghpmbCompat hardcodes `_tghpcontact_` as the label id / aria-labelledby prefix. This should derive from form config to work for any tghpmb form, not just `contact`. The tghpmb prefix pattern is configurable per-form.

**Files:**
- Modify: `src/plugins/oriel/src/Compat/TghpmbCompat.php`

**Step 1: Add compat_prefix option support**

The compat module should read a `compat_prefix` option from form config. Add a helper:

```php
/**
 * Get the tghpmb field prefix for a form.
 *
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
```

**Step 2: Update filterLabelWrapperAttrs to use prefix**

```php
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
```

**Step 3: Update filterInputAttrs to use prefix**

```php
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
```

**Step 4: Add `compat_prefix` to FormRegistry OPTION_DEFAULTS**

```php
'compat_prefix' => '',
```

**Step 5: Commit**

```bash
git add src/plugins/oriel/src/Compat/TghpmbCompat.php \
        src/plugins/oriel/src/FormRegistry.php
git commit -m "feat(oriel): make tghpmb compat prefix configurable per form"
```

---

### Task 11: Verify output against reference HTML

**Step 1: Create a temporary test form registration**

Register a test form matching the reference HTML:

```php
add_filter('oriel_forms', function ($forms) {
    $forms['contact_block_generic'] = [
        'title'   => 'Contact',
        'options' => [
            'compat'        => 'tghpmb',
            'compat_prefix' => '_tghpcontact_',
            'submit_class'  => 'rwmb-button button button--blue-dark',
        ],
        'fields' => [
            [
                'id'          => 'sender_email',
                'name'        => 'Email',
                'type'        => 'email',
                'required'    => true,
                'placeholder' => 'Email',
            ],
            [
                'id'          => 'message',
                'name'        => 'Message',
                'type'        => 'text',
                'required'    => true,
                'placeholder' => 'Message',
            ],
        ],
    ];

    return $forms;
});
```

**Step 2: Compare rendered output against `docs/tghpmbform.html`**

Expected output structure (ignoring hidden/security fields):

```html
<div class="tghpform tghpform--contact_block_generic">
  <form id="contact_block_generic" method="post"
        class="rwmb-form mbfs-form" enctype="multipart/form-data"
        novalidate="novalidate" autocomplete="off">
    {security fields — will differ, that's fine}
    <div class="rwmb-form-fields form" id="form_contact_block_generic">
      <div class="rwmb-field rwmb-email-wrapper field-sender_email required">
        <div class="rwmb-label" id="_tghpcontact_sender_email-label">
          <label for="oriel_sender_email" id="oriel_sender_email-label">
            Email <span class="rwmb-required">*</span>
          </label>
        </div>
        <div class="rwmb-input">
          <input id="oriel_sender_email" name="oriel[oriel_sender_email]"
                 placeholder="Email" type="email" required="required"
                 class="rwmb-email"
                 aria-labelledby="_tghpcontact_sender_email-label" />
        </div>
        <div class="oriel-field__error" data-error-for="oriel_sender_email"></div>
      </div>
      <div class="rwmb-field rwmb-text-wrapper field-message required">
        <div class="rwmb-label" id="_tghpcontact_message-label">
          <label for="oriel_message" id="oriel_message-label">
            Message <span class="rwmb-required">*</span>
          </label>
        </div>
        <div class="rwmb-input">
          <input id="oriel_message" name="oriel[oriel_message]"
                 placeholder="Message" type="text" required="required"
                 class="rwmb-text"
                 aria-labelledby="_tghpcontact_message-label" />
        </div>
        <div class="oriel-field__error" data-error-for="oriel_message"></div>
      </div>
    </div>
    <div class="rwmb-field rwmb-button-wrapper rwmb-form-submit">
      <div class="rwmb-input">
        <button type="submit" class="rwmb-button button button--blue-dark"
                name="rwmb_submit" value="1">
          Submit
        </button>
      </div>
    </div>
  </form>
</div>
```

Verify class parity on every element against `docs/tghpmbform.html`. Acceptable differences:
- Hidden/security fields (different mechanism)
- Input id/name format (oriel_ prefix, nested name)
- `<label>` retains `id` attr (harmless)
- Error div present (invisible when empty)

**Step 3: Commit removal of test form (or leave in place if it's useful)**

---

### Task 12: Final commit — clean up docs

**Step 1: Remove plan docs if requested, or commit final state**

```bash
git add -A
git commit -m "feat(oriel): tghpmb compat mode complete"
```
