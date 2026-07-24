# Oriel Plugin Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build Oriel, a developer-first WordPress form plugin independent of Meta Box, with code-defined forms, pipeline processing, REST API submission, and email notifications.

**Architecture:** PSR-4 namespaced plugin (`Oriel\`) with discrete pipeline steps for form processing. Forms registered via `oriel_forms` filter. Fields are extensible classes implementing `FieldInterface`. Submissions stored as `oriel_submission` CPT. AJAX via REST endpoint.

**Tech Stack:** PHP 7.4+, WordPress 6.8+, Composer (PSR-4 autoload only)

**Design doc:** `docs/plans/2026-03-03-oriel-plugin-design.md`

---

### Task 1: Plugin Bootstrap & Autoloading

**Files:**
- Create: `src/plugins/oriel/oriel.php`
- Create: `src/plugins/oriel/composer.json`
- Create: `src/plugins/oriel/src/Plugin.php`

**Step 1: Create composer.json with PSR-4 autoload**

```json
{
    "name": "tghp/oriel",
    "description": "Developer-first WordPress form plugin",
    "type": "wordpress-plugin",
    "version": "1.0.0",
    "require": {
        "php": ">=7.4"
    },
    "autoload": {
        "psr-4": {
            "Oriel\\": "src/"
        },
        "files": [
            "src/helpers.php"
        ]
    }
}
```

**Step 2: Create the main plugin entry file**

`src/plugins/oriel/oriel.php`:

```php
<?php
/**
 * Plugin Name: Oriel
 * Description: Developer-first WordPress form plugin
 * Version: 1.0.0
 * Author: TGHP
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    die();
}

define('ORIEL_VERSION', '1.0.0');
define('ORIEL_META_PREFIX', '_oriel_');
define('ORIEL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ORIEL_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/vendor/autoload.php';

\Oriel\Plugin::instance();
```

**Step 3: Create Plugin singleton**

`src/plugins/oriel/src/Plugin.php` — singleton that on `init`:
- Instantiates `PostType` (task 2)
- Instantiates `FormRegistry` (task 3)
- Registers shortcode (task 6)
- Hooks `template_redirect` for non-AJAX submission (task 8)
- Hooks `rest_api_init` for REST endpoint (task 9)

For now, just the skeleton:

```php
<?php

namespace Oriel;

class Plugin
{
    private static ?Plugin $instance = null;
    private FormRegistry $registry;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', [$this, 'init']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function init(): void
    {
        PostType::register();
        $this->registry = new FormRegistry();

        add_shortcode('oriel_form', [$this, 'shortcode']);
        add_action('template_redirect', [$this, 'handleSubmission']);
    }

    public function getRegistry(): FormRegistry
    {
        return $this->registry;
    }

    public function shortcode(array $atts = []): string
    {
        $atts = shortcode_atts([
            'id' => '',
            'title' => '',
            'hide' => '0',
            'hide_button_label' => 'Show Form',
            'hide_button_class' => '',
            'background' => '',
        ], $atts, 'oriel_form');

        if (empty($atts['id'])) {
            return '';
        }

        return oriel_form($atts['id'], $atts);
    }

    public function handleSubmission(): void
    {
        if (empty($_POST['oriel_form_id'])) {
            return;
        }

        $formId = sanitize_text_field($_POST['oriel_form_id']);

        if (!wp_verify_nonce($_POST['oriel_nonce'] ?? '', 'oriel_submit_' . $formId)) {
            return;
        }

        $formConfig = $this->registry->get($formId);
        if (!$formConfig) {
            return;
        }

        $submittedData = $this->extractSubmittedData($formConfig);
        $processor = new FormProcessor($this->registry);
        $processor->process($formId, $formConfig, $submittedData, false);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('oriel/v1', '/submit', [
            'methods' => 'POST',
            'callback' => [$this, 'handleRestSubmission'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handleRestSubmission(\WP_REST_Request $request): \WP_REST_Response
    {
        $formId = sanitize_text_field($request->get_param('form_id') ?? '');
        $nonce = $request->get_param('oriel_nonce') ?? '';

        if (!wp_verify_nonce($nonce, 'oriel_submit_' . $formId)) {
            return new \WP_REST_Response(['success' => false, 'errors' => ['nonce' => 'Invalid nonce.']], 403);
        }

        $formConfig = $this->registry->get($formId);
        if (!$formConfig) {
            return new \WP_REST_Response(['success' => false, 'errors' => ['form' => 'Form not found.']], 404);
        }

        $submittedData = [];
        $orielData = $request->get_param('oriel') ?? [];
        foreach ($formConfig['fields'] as $field) {
            $id = $field['id'];
            $submittedData[$id] = $orielData[$id] ?? null;
        }

        $processor = new FormProcessor($this->registry);
        $result = $processor->process($formId, $formConfig, $submittedData, true);

        if (!empty($result->errors)) {
            return new \WP_REST_Response(['success' => false, 'errors' => $result->errors], 422);
        }

        $response = ['success' => true];
        if (!empty($formConfig['options']['confirmation'])) {
            $response['message'] = $formConfig['options']['confirmation'];
        }
        if (!empty($formConfig['options']['redirect'])) {
            $response['redirect'] = $formConfig['options']['redirect'];
        }

        return new \WP_REST_Response($response, 200);
    }

    private function extractSubmittedData(array $formConfig): array
    {
        $submittedData = [];
        $orielData = $_POST['oriel'] ?? [];

        foreach ($formConfig['fields'] as $field) {
            $id = $field['id'];
            $submittedData[$id] = $orielData[$id] ?? null;
        }

        return $submittedData;
    }
}
```

**Step 4: Create helpers.php**

`src/plugins/oriel/src/helpers.php`:

```php
<?php

if (!function_exists('oriel_form')) {
    function oriel_form(string $id, array $args = []): string
    {
        $registry = \Oriel\Plugin::instance()->getRegistry();
        $formConfig = $registry->get($id);

        if (!$formConfig) {
            return '';
        }

        $renderer = new \Oriel\FormRenderer($registry);
        return $renderer->render($id, $formConfig, $args);
    }
}

if (!function_exists('oriel_get_submission_data')) {
    function oriel_get_submission_data(int $postId, string $key)
    {
        return get_post_meta($postId, ORIEL_META_PREFIX . $key, true);
    }
}
```

**Step 5: Run composer dump-autoload**

Run: `cd src/plugins/oriel && composer dump-autoload`
Expected: Autoload files generated in `vendor/`

**Step 6: Commit**

```bash
git add src/plugins/oriel/
git commit -m "feat(oriel): plugin bootstrap with singleton, autoload, helpers"
```

---

### Task 2: Custom Post Type & Taxonomy

**Files:**
- Create: `src/plugins/oriel/src/PostType.php`

**Step 1: Create PostType class**

Registers `oriel_submission` CPT (non-public, no search, dashicons-email) and `oriel_form` taxonomy (non-hierarchical, hidden from UI). Mirrors tghp-mb-contact's `contact_submission` CPT approach.

```php
<?php

namespace Oriel;

class PostType
{
    public const POST_TYPE = 'oriel_submission';
    public const TAXONOMY = 'oriel_form';

    public static function register(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Form Submissions'),
                'singular_name' => __('Form Submission'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-email',
            'supports' => ['title'],
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'capability_type' => 'page',
        ]);

        register_taxonomy(self::TAXONOMY, self::POST_TYPE, [
            'labels' => [
                'name' => __('Forms'),
                'singular_name' => __('Form'),
            ],
            'public' => false,
            'show_ui' => false,
            'show_admin_column' => true,
            'hierarchical' => false,
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add src/plugins/oriel/src/PostType.php
git commit -m "feat(oriel): register oriel_submission CPT and oriel_form taxonomy"
```

---

### Task 3: Form Registry

**Files:**
- Create: `src/plugins/oriel/src/FormRegistry.php`

**Step 1: Create FormRegistry**

Collects forms via `oriel_forms` filter on construction. Normalizes option defaults. Applies `oriel_fields` and `oriel_fields_{$formId}` filters to each form's fields.

```php
<?php

namespace Oriel;

class FormRegistry
{
    /** @var array<string, array> */
    private array $forms = [];

    public function __construct()
    {
        $forms = apply_filters('oriel_forms', []);

        foreach ($forms as $id => $form) {
            $this->forms[$id] = $this->normalize($id, $form);
        }
    }

    public function get(string $id): ?array
    {
        return $this->forms[$id] ?? null;
    }

    public function all(): array
    {
        return $this->forms;
    }

    private function normalize(string $id, array $form): array
    {
        $defaults = [
            'title' => '',
            'options' => [],
            'fields' => [],
        ];

        $form = array_merge($defaults, $form);

        $optionDefaults = [
            'redirect' => '',
            'confirmation' => '',
            'ajax' => false,
            'email' => null,
            'delete_after_processing' => false,
            'class' => '',
            'submit_class' => '',
            'submit_text' => 'Submit',
        ];

        $form['options'] = array_merge($optionDefaults, $form['options']);

        // Apply field filters
        $form['fields'] = apply_filters('oriel_fields', $form['fields'], $form);
        $form['fields'] = apply_filters("oriel_fields_{$id}", $form['fields'], $form);

        return $form;
    }
}
```

**Step 2: Commit**

```bash
git add src/plugins/oriel/src/FormRegistry.php
git commit -m "feat(oriel): form registry with filter collection and normalization"
```

---

### Task 4: Field System

**Files:**
- Create: `src/plugins/oriel/src/Field/FieldInterface.php`
- Create: `src/plugins/oriel/src/Field/AbstractField.php`
- Create: `src/plugins/oriel/src/Field/TextField.php`
- Create: `src/plugins/oriel/src/Field/EmailField.php`
- Create: `src/plugins/oriel/src/Field/TextareaField.php`
- Create: `src/plugins/oriel/src/Field/CheckboxField.php`
- Create: `src/plugins/oriel/src/Field/SelectField.php`
- Create: `src/plugins/oriel/src/Field/RadioField.php`
- Create: `src/plugins/oriel/src/Field/HiddenField.php`

**Step 1: Create FieldInterface**

```php
<?php

namespace Oriel\Field;

interface FieldInterface
{
    public function render(array $field, $value, string $formId): string;
    public function validate(array $field, $value): ?string;
    public function sanitize(array $field, $value);
}
```

**Step 2: Create AbstractField**

Provides shared rendering logic: wrapper div, label, description, error message. Subclasses implement `renderInput()` for the actual input element.

```php
<?php

namespace Oriel\Field;

abstract class AbstractField implements FieldInterface
{
    abstract protected function renderInput(array $field, $value, string $formId): string;
    abstract protected function getType(): string;

    public function render(array $field, $value, string $formId): string
    {
        $type = $this->getType();
        $id = $field['id'];
        $classes = "oriel-field oriel-field--{$type} oriel-field--{$id}";

        if (!empty($field['class'])) {
            $classes .= ' ' . $field['class'];
        }

        $html = '<div class="' . esc_attr($classes) . '">';

        if (!empty($field['name']) && $type !== 'hidden') {
            $html .= $this->renderLabel($field, $formId);
        }

        $html .= $this->renderInput($field, $value, $formId);

        if (!empty($field['desc']) && $type !== 'hidden') {
            $html .= '<p class="oriel-field__desc">' . esc_html($field['desc']) . '</p>';
        }

        if (!empty($field['error'])) {
            $html .= '<p class="oriel-field__error">' . esc_html($field['error']) . '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    public function validate(array $field, $value): ?string
    {
        if (!empty($field['required']) && $this->isEmpty($value)) {
            $label = $field['name'] ?? $field['id'];
            return sprintf('%s is required.', $label);
        }

        return null;
    }

    public function sanitize(array $field, $value)
    {
        return sanitize_text_field($value ?? '');
    }

    protected function renderLabel(array $field, string $formId): string
    {
        $inputId = $this->getInputId($field, $formId);
        $html = '<label for="' . esc_attr($inputId) . '">';
        $html .= esc_html($field['name']);

        if (!empty($field['required'])) {
            $html .= ' <span class="oriel-field__required">*</span>';
        }

        $html .= '</label>';

        return $html;
    }

    protected function getInputId(array $field, string $formId): string
    {
        return 'oriel_' . $formId . '_' . $field['id'];
    }

    protected function getInputName(array $field): string
    {
        return 'oriel[' . $field['id'] . ']';
    }

    protected function isEmpty($value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    protected function buildAttributes(array $field, string $formId): string
    {
        $attrs = [
            'id' => $this->getInputId($field, $formId),
            'name' => $this->getInputName($field),
        ];

        if (!empty($field['placeholder'])) {
            $attrs['placeholder'] = $field['placeholder'];
        }

        if (!empty($field['required'])) {
            $attrs['required'] = 'required';
        }

        if (!empty($field['attributes'])) {
            $attrs = array_merge($attrs, $field['attributes']);
        }

        $html = '';
        foreach ($attrs as $key => $val) {
            if ($val === 'required') {
                $html .= ' required';
            } else {
                $html .= ' ' . esc_attr($key) . '="' . esc_attr($val) . '"';
            }
        }

        return $html;
    }
}
```

**Step 3: Create concrete field types**

Each field type implements `renderInput()`, `getType()`, and optionally overrides `validate()`/`sanitize()`.

**TextField:**
```php
<?php

namespace Oriel\Field;

class TextField extends AbstractField
{
    protected function getType(): string
    {
        return 'text';
    }

    protected function renderInput(array $field, $value, string $formId): string
    {
        return '<input type="text"' . $this->buildAttributes($field, $formId)
            . ' value="' . esc_attr($value ?? '') . '">';
    }
}
```

**EmailField:**
```php
<?php

namespace Oriel\Field;

class EmailField extends AbstractField
{
    protected function getType(): string
    {
        return 'email';
    }

    protected function renderInput(array $field, $value, string $formId): string
    {
        return '<input type="email"' . $this->buildAttributes($field, $formId)
            . ' value="' . esc_attr($value ?? '') . '">';
    }

    public function validate(array $field, $value): ?string
    {
        $error = parent::validate($field, $value);
        if ($error) {
            return $error;
        }

        if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email address.';
        }

        return null;
    }

    public function sanitize(array $field, $value)
    {
        return sanitize_email($value ?? '');
    }
}
```

**TextareaField:**
```php
<?php

namespace Oriel\Field;

class TextareaField extends AbstractField
{
    protected function getType(): string
    {
        return 'textarea';
    }

    protected function renderInput(array $field, $value, string $formId): string
    {
        return '<textarea' . $this->buildAttributes($field, $formId) . '>'
            . esc_textarea($value ?? '') . '</textarea>';
    }

    public function sanitize(array $field, $value)
    {
        return sanitize_textarea_field($value ?? '');
    }
}
```

**CheckboxField:**
```php
<?php

namespace Oriel\Field;

class CheckboxField extends AbstractField
{
    protected function getType(): string
    {
        return 'checkbox';
    }

    public function render(array $field, $value, string $formId): string
    {
        $type = $this->getType();
        $id = $field['id'];
        $classes = "oriel-field oriel-field--{$type} oriel-field--{$id}";

        if (!empty($field['class'])) {
            $classes .= ' ' . $field['class'];
        }

        $html = '<div class="' . esc_attr($classes) . '">';
        $html .= $this->renderInput($field, $value, $formId);

        if (!empty($field['error'])) {
            $html .= '<p class="oriel-field__error">' . esc_html($field['error']) . '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    protected function renderInput(array $field, $value, string $formId): string
    {
        $inputId = $this->getInputId($field, $formId);
        $checked = $value ? ' checked' : '';

        $html = '<input type="hidden" name="' . esc_attr($this->getInputName($field)) . '" value="0">';
        $html .= '<label for="' . esc_attr($inputId) . '">';
        $html .= '<input type="checkbox" id="' . esc_attr($inputId) . '"'
            . ' name="' . esc_attr($this->getInputName($field)) . '"'
            . ' value="1"' . $checked;

        if (!empty($field['required'])) {
            $html .= ' required';
        }

        $html .= '>';

        if (!empty($field['desc'])) {
            $html .= ' ' . esc_html($field['desc']);
        } elseif (!empty($field['name'])) {
            $html .= ' ' . esc_html($field['name']);
        }

        if (!empty($field['required'])) {
            $html .= ' <span class="oriel-field__required">*</span>';
        }

        $html .= '</label>';

        return $html;
    }

    public function sanitize(array $field, $value)
    {
        return $value ? 1 : 0;
    }

    protected function isEmpty($value): bool
    {
        return empty($value);
    }
}
```

**SelectField:**
```php
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
        $html = '<select' . $this->buildAttributes($field, $formId) . '>';

        if (!empty($field['placeholder'])) {
            $html .= '<option value="">' . esc_html($field['placeholder']) . '</option>';
        }

        foreach (($field['options'] ?? []) as $optValue => $optLabel) {
            $selected = ($value !== null && (string) $value === (string) $optValue) ? ' selected' : '';
            $html .= '<option value="' . esc_attr($optValue) . '"' . $selected . '>'
                . esc_html($optLabel) . '</option>';
        }

        $html .= '</select>';

        return $html;
    }
}
```

**RadioField:**
```php
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
        $html = '<div class="oriel-field__radios">';
        $name = $this->getInputName($field);

        foreach (($field['options'] ?? []) as $optValue => $optLabel) {
            $optId = $this->getInputId($field, $formId) . '_' . $optValue;
            $checked = ($value !== null && (string) $value === (string) $optValue) ? ' checked' : '';

            $html .= '<label for="' . esc_attr($optId) . '">';
            $html .= '<input type="radio" id="' . esc_attr($optId) . '"'
                . ' name="' . esc_attr($name) . '"'
                . ' value="' . esc_attr($optValue) . '"' . $checked . '>';
            $html .= ' ' . esc_html($optLabel);
            $html .= '</label>';
        }

        $html .= '</div>';

        return $html;
    }
}
```

**HiddenField:**
```php
<?php

namespace Oriel\Field;

class HiddenField extends AbstractField
{
    protected function getType(): string
    {
        return 'hidden';
    }

    protected function renderInput(array $field, $value, string $formId): string
    {
        return '<input type="hidden"'
            . ' name="' . esc_attr($this->getInputName($field)) . '"'
            . ' value="' . esc_attr($value ?? '') . '">';
    }

    public function render(array $field, $value, string $formId): string
    {
        return $this->renderInput($field, $value, $formId);
    }
}
```

**Step 4: Commit**

```bash
git add src/plugins/oriel/src/Field/
git commit -m "feat(oriel): field system with interface, abstract base, and 7 field types"
```

---

### Task 5: Field Type Registry

Add a method to `Plugin` that maps type strings to field classes, extensible via `oriel_field_types` filter.

**Files:**
- Modify: `src/plugins/oriel/src/Plugin.php`

**Step 1: Add getFieldTypes() to Plugin**

Add this method to Plugin class:

```php
public function getFieldTypes(): array
{
    $types = [
        'text' => Field\TextField::class,
        'email' => Field\EmailField::class,
        'textarea' => Field\TextareaField::class,
        'checkbox' => Field\CheckboxField::class,
        'select' => Field\SelectField::class,
        'radio' => Field\RadioField::class,
        'hidden' => Field\HiddenField::class,
    ];

    return apply_filters('oriel_field_types', $types);
}

public function getFieldInstance(string $type): ?Field\FieldInterface
{
    $types = $this->getFieldTypes();

    if (!isset($types[$type])) {
        return null;
    }

    $class = $types[$type];
    return new $class();
}
```

**Step 2: Commit**

```bash
git add src/plugins/oriel/src/Plugin.php
git commit -m "feat(oriel): extensible field type registry via oriel_field_types filter"
```

---

### Task 6: Form Renderer

**Files:**
- Create: `src/plugins/oriel/src/FormRenderer.php`

**Step 1: Create FormRenderer**

Handles full form HTML output: wrapper div, messages (success/error on reload), form tag with nonce + hidden fields, field rendering via field type instances, submit button. Applies all rendering filter hooks.

```php
<?php

namespace Oriel;

class FormRenderer
{
    private FormRegistry $registry;

    public function __construct(FormRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function render(string $formId, array $formConfig, array $args = []): string
    {
        $options = $formConfig['options'];
        $wrapperClass = 'oriel-form oriel-form--' . $formId;

        if (!empty($options['class'])) {
            $wrapperClass .= ' ' . $options['class'];
        }

        // Retrieve stored values/errors for non-AJAX repopulation
        $storedState = $this->getStoredState($formId);
        $storedValues = $storedState['values'] ?? [];
        $storedErrors = $storedState['errors'] ?? [];

        $html = '<div class="' . esc_attr($wrapperClass) . '">';

        // Title (from shortcode args)
        if (!empty($args['title'])) {
            $html .= '<h3 class="oriel-form__title">' . esc_html($args['title']) . '</h3>';
        }

        // Success/error messages on reload
        $html .= $this->renderMessages($formId, $formConfig);

        do_action('oriel_form_before', $formId, $formConfig);

        $html .= '<form method="post" class="oriel-form__form" enctype="multipart/form-data">';
        $html .= '<input type="hidden" name="oriel_form_id" value="' . esc_attr($formId) . '">';
        $html .= wp_nonce_field('oriel_submit_' . $formId, 'oriel_nonce', true, false);

        // Render fields
        foreach ($formConfig['fields'] as $field) {
            $fieldType = $field['type'] ?? 'text';
            $fieldInstance = Plugin::instance()->getFieldInstance($fieldType);

            if (!$fieldInstance) {
                continue;
            }

            // Resolve default value
            $value = $storedValues[$field['id']] ?? null;
            if ($value === null) {
                $std = $field['std'] ?? '';
                $value = is_callable($std) ? $std() : $std;
            }

            // Attach error to field for rendering
            if (isset($storedErrors[$field['id']])) {
                $field['error'] = $storedErrors[$field['id']];
            }

            $fieldHtml = $fieldInstance->render($field, $value, $formId);
            $html .= apply_filters('oriel_field_html', $fieldHtml, $field, $value, $formId);
        }

        // Submit button
        $submitClass = $options['submit_class'] ?? '';
        $submitText = $options['submit_text'] ?? 'Submit';
        $submitHtml = '<div class="oriel-form__submit">';
        $submitHtml .= '<button type="submit" class="' . esc_attr($submitClass) . '">'
            . esc_html($submitText) . '</button>';
        $submitHtml .= '</div>';
        $html .= apply_filters('oriel_submit_button', $submitHtml, $formId, $formConfig);

        $html .= '</form>';

        do_action('oriel_form_after', $formId, $formConfig);

        $html .= '</div>';

        // Clear stored state after rendering
        if (!empty($storedState)) {
            $this->clearStoredState($formId);
        }

        $html = apply_filters('oriel_form_html', $html, $formId, $formConfig);

        // Handle hide option from shortcode
        if (!empty($args['hide']) && $args['hide'] !== '0') {
            $buttonLabel = $args['hide_button_label'] ?? 'Show Form';
            $buttonClass = $args['hide_button_class'] ?? '';
            $wrappedHtml = '<button class="' . esc_attr($buttonClass) . '" onclick="this.nextElementSibling.style.display=\'block\';this.style.display=\'none\';">'
                . esc_html($buttonLabel) . '</button>';
            $wrappedHtml .= '<div style="display:none;">' . $html . '</div>';
            $html = $wrappedHtml;
        }

        return $html;
    }

    private function renderMessages(string $formId, array $formConfig): string
    {
        $html = '';

        if (isset($_GET['oriel-submitted']) && $_GET['oriel-submitted'] === $formId) {
            $message = $formConfig['options']['confirmation'] ?? '';
            if ($message) {
                $html .= '<div class="oriel-form__message oriel-form__message--success">'
                    . esc_html($message) . '</div>';
            }
        }

        if (isset($_GET['oriel-errors']) && $_GET['oriel-errors'] === $formId) {
            $html .= '<div class="oriel-form__message oriel-form__message--error">'
                . esc_html('There were errors with your submission. Please correct them and try again.')
                . '</div>';
        }

        return $html;
    }

    private function getStoredState(string $formId): array
    {
        $transientKey = $this->getTransientKey($formId);
        $state = get_transient($transientKey);

        return is_array($state) ? $state : [];
    }

    private function clearStoredState(string $formId): void
    {
        delete_transient($this->getTransientKey($formId));
    }

    private function getTransientKey(string $formId): string
    {
        $userId = get_current_user_id();

        if ($userId) {
            return 'oriel_state_' . $userId . '_' . $formId;
        }

        if (!session_id()) {
            session_start();
        }

        return 'oriel_state_' . session_id() . '_' . $formId;
    }
}
```

**Step 2: Commit**

```bash
git add src/plugins/oriel/src/FormRenderer.php
git commit -m "feat(oriel): form renderer with field rendering, messages, state repopulation"
```

---

### Task 7: Processing Pipeline

**Files:**
- Create: `src/plugins/oriel/src/Processing/StepInterface.php`
- Create: `src/plugins/oriel/src/Processing/ProcessingContext.php`
- Create: `src/plugins/oriel/src/Processing/ValidateStep.php`
- Create: `src/plugins/oriel/src/Processing/CreatePostStep.php`
- Create: `src/plugins/oriel/src/Processing/HooksStep.php`
- Create: `src/plugins/oriel/src/Processing/EmailStep.php`
- Create: `src/plugins/oriel/src/Processing/CleanupStep.php`
- Create: `src/plugins/oriel/src/Processing/RedirectStep.php`
- Create: `src/plugins/oriel/src/Email/EmailNotifier.php`
- Create: `src/plugins/oriel/src/FormProcessor.php`

**Step 1: Create StepInterface and ProcessingContext**

```php
<?php
// src/Processing/StepInterface.php
namespace Oriel\Processing;

interface StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext;
}
```

```php
<?php
// src/Processing/ProcessingContext.php
namespace Oriel\Processing;

class ProcessingContext
{
    public string $formId;
    public array $formConfig;
    public array $submittedData;
    public ?int $postId = null;
    public array $errors = [];
    public bool $shouldHalt = false;
    public bool $isRest = false;

    public function __construct(
        string $formId,
        array $formConfig,
        array $submittedData,
        bool $isRest = false
    ) {
        $this->formId = $formId;
        $this->formConfig = $formConfig;
        $this->submittedData = $submittedData;
        $this->isRest = $isRest;
    }
}
```

**Step 2: Create ValidateStep**

Runs each field's `validate()`, then applies `oriel_validate` / `oriel_validate_{$formId}` filters. On any errors, sets `shouldHalt = true`.

```php
<?php

namespace Oriel\Processing;

use Oriel\Plugin;

class ValidateStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        $plugin = Plugin::instance();

        foreach ($context->formConfig['fields'] as $field) {
            $fieldType = $field['type'] ?? 'text';
            $fieldInstance = $plugin->getFieldInstance($fieldType);

            if (!$fieldInstance) {
                continue;
            }

            $value = $context->submittedData[$field['id']] ?? null;
            $sanitized = $fieldInstance->sanitize($field, $value);
            $context->submittedData[$field['id']] = $sanitized;

            $error = $fieldInstance->validate($field, $sanitized);

            if ($error !== null) {
                $context->errors[$field['id']] = $error;
            }
        }

        // Custom validation filters
        $context->errors = apply_filters(
            'oriel_validate',
            $context->errors,
            $context->submittedData,
            $context->formConfig
        ) ?: [];

        $context->errors = apply_filters(
            "oriel_validate_{$context->formId}",
            $context->errors,
            $context->submittedData,
            $context->formConfig
        ) ?: [];

        if (!empty($context->errors)) {
            $context->shouldHalt = true;
        }

        return $context;
    }
}
```

**Step 3: Create CreatePostStep**

```php
<?php

namespace Oriel\Processing;

use Oriel\PostType;

class CreatePostStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        if ($context->shouldHalt) {
            return $context;
        }

        $title = ($context->formConfig['title'] ?? 'Submission')
            . ' - ' . current_time('Y-m-d H:i:s');

        $postId = wp_insert_post([
            'post_type' => PostType::POST_TYPE,
            'post_title' => $title,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($postId)) {
            $context->errors['post'] = 'Failed to save submission.';
            $context->shouldHalt = true;
            return $context;
        }

        $context->postId = $postId;

        // Save field values as post meta
        foreach ($context->formConfig['fields'] as $field) {
            $id = $field['id'];
            $value = $context->submittedData[$id] ?? '';
            update_post_meta($postId, ORIEL_META_PREFIX . $id, $value);
        }

        // Set form taxonomy term
        wp_set_object_terms($postId, [$context->formId], PostType::TAXONOMY);

        return $context;
    }
}
```

**Step 4: Create HooksStep**

```php
<?php

namespace Oriel\Processing;

class HooksStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        if ($context->shouldHalt) {
            return $context;
        }

        do_action('oriel_after_process', $context->formId, $context->postId);
        do_action("oriel_after_process_{$context->formId}", $context->postId);

        return $context;
    }
}
```

**Step 5: Create EmailNotifier and EmailStep**

```php
<?php
// src/Email/EmailNotifier.php
namespace Oriel\Email;

class EmailNotifier
{
    public function send(string $to, string $subject, array $fields, array $submittedData): bool
    {
        $html = '<h1>' . esc_html($subject) . '</h1>';

        foreach ($fields as $field) {
            if (empty($field['email'])) {
                continue;
            }

            $id = $field['id'];
            $label = $field['name'] ?? $id;
            $value = $submittedData[$id] ?? '';
            $formattedValue = $this->formatValue($field, $value);

            $html .= '<p><strong>' . esc_html($label) . '</strong><br>'
                . $formattedValue . '</p>';
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return wp_mail($to, $subject, $html, $headers);
    }

    private function formatValue(array $field, $value): string
    {
        $type = $field['type'] ?? 'text';

        switch ($type) {
            case 'checkbox':
                return $value ? 'Yes' : 'No';

            case 'select':
            case 'radio':
                $options = $field['options'] ?? [];
                $label = $options[$value] ?? $value;
                return esc_html($value) . ' (' . esc_html($label) . ')';

            default:
                return esc_html($value);
        }
    }
}
```

```php
<?php
// src/Processing/EmailStep.php
namespace Oriel\Processing;

use Oriel\Email\EmailNotifier;

class EmailStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        if ($context->shouldHalt) {
            return $context;
        }

        $emailConfig = $context->formConfig['options']['email'] ?? null;

        if (!$emailConfig || empty($emailConfig['email'])) {
            return $context;
        }

        $to = apply_filters('oriel_email_to', $emailConfig['email'], $context->formId, $context->postId);
        $subject = apply_filters('oriel_email_subject', $emailConfig['title'] ?? 'Form Submission', $context->formId, $context->postId);

        $notifier = new EmailNotifier();
        $html = '';

        // Allow filtering the full content after generation
        $notifier->send($to, $subject, $context->formConfig['fields'], $context->submittedData);

        return $context;
    }
}
```

Actually, let me restructure EmailStep to support the `oriel_email_content` filter properly:

```php
<?php
// src/Processing/EmailStep.php
namespace Oriel\Processing;

use Oriel\Email\EmailNotifier;

class EmailStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        if ($context->shouldHalt) {
            return $context;
        }

        $emailConfig = $context->formConfig['options']['email'] ?? null;

        if (!$emailConfig || empty($emailConfig['email'])) {
            return $context;
        }

        $to = apply_filters(
            'oriel_email_to',
            $emailConfig['email'],
            $context->formId,
            $context->postId
        );

        $subject = apply_filters(
            'oriel_email_subject',
            $emailConfig['title'] ?? 'Form Submission',
            $context->formId,
            $context->postId
        );

        $notifier = new EmailNotifier();
        $content = $notifier->buildContent($subject, $context->formConfig['fields'], $context->submittedData);

        $content = apply_filters(
            'oriel_email_content',
            $content,
            $context->formId,
            $context->postId
        );

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($to, $subject, $content, $headers);

        return $context;
    }
}
```

Update EmailNotifier accordingly — split `send()` into `buildContent()` (public, returns HTML string) and let EmailStep handle `wp_mail`:

```php
<?php
// src/Email/EmailNotifier.php (revised)
namespace Oriel\Email;

class EmailNotifier
{
    public function buildContent(string $title, array $fields, array $submittedData): string
    {
        $html = '<h1>' . esc_html($title) . '</h1>';

        foreach ($fields as $field) {
            if (empty($field['email'])) {
                continue;
            }

            $id = $field['id'];
            $label = $field['name'] ?? $id;
            $value = $submittedData[$id] ?? '';
            $formattedValue = $this->formatValue($field, $value);

            $html .= '<p><strong>' . esc_html($label) . '</strong><br>'
                . $formattedValue . '</p>';
        }

        return $html;
    }

    private function formatValue(array $field, $value): string
    {
        $type = $field['type'] ?? 'text';

        switch ($type) {
            case 'checkbox':
                return $value ? 'Yes' : 'No';

            case 'select':
            case 'radio':
                $options = $field['options'] ?? [];
                $label = $options[$value] ?? $value;
                return esc_html($value) . ' (' . esc_html($label) . ')';

            default:
                return esc_html($value);
        }
    }
}
```

**Step 6: Create CleanupStep**

```php
<?php

namespace Oriel\Processing;

class CleanupStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        if ($context->shouldHalt || !$context->postId) {
            return $context;
        }

        $shouldDelete = $context->formConfig['options']['delete_after_processing'] ?? false;

        if (!$shouldDelete) {
            return $context;
        }

        $doNotDelete = get_post_meta($context->postId, ORIEL_META_PREFIX . 'do_not_delete', true);

        if ($doNotDelete === '1') {
            return $context;
        }

        wp_delete_post($context->postId, true);

        return $context;
    }
}
```

**Step 7: Create RedirectStep**

Handles response differently for REST vs page POST. For page POST: stores errors/values in transient, redirects. For REST: returns (no-op, response built in Plugin).

```php
<?php

namespace Oriel\Processing;

class RedirectStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        // REST responses handled in Plugin::handleRestSubmission()
        if ($context->isRest) {
            return $context;
        }

        $baseUrl = strtok(home_url($_SERVER['REQUEST_URI'] ?? ''), '?');

        if (!empty($context->errors)) {
            // Store errors + values in transient for form repopulation
            $this->storeState($context->formId, [
                'errors' => $context->errors,
                'values' => $context->submittedData,
            ]);

            $redirectUrl = add_query_arg('oriel-errors', $context->formId, $baseUrl);
            wp_redirect($redirectUrl);
            exit;
        }

        $redirect = $context->formConfig['options']['redirect'] ?? '';

        if ($redirect) {
            wp_redirect($redirect);
        } else {
            $redirectUrl = add_query_arg('oriel-submitted', $context->formId, $baseUrl);
            wp_redirect($redirectUrl);
        }

        exit;
    }

    private function storeState(string $formId, array $state): void
    {
        $transientKey = $this->getTransientKey($formId);
        set_transient($transientKey, $state, 300); // 5 min TTL
    }

    private function getTransientKey(string $formId): string
    {
        $userId = get_current_user_id();

        if ($userId) {
            return 'oriel_state_' . $userId . '_' . $formId;
        }

        if (!session_id()) {
            session_start();
        }

        return 'oriel_state_' . session_id() . '_' . $formId;
    }
}
```

**Step 8: Create FormProcessor**

Orchestrates the pipeline: instantiates all steps in order, runs each sequentially.

```php
<?php

namespace Oriel;

use Oriel\Processing\ProcessingContext;
use Oriel\Processing\ValidateStep;
use Oriel\Processing\CreatePostStep;
use Oriel\Processing\HooksStep;
use Oriel\Processing\EmailStep;
use Oriel\Processing\CleanupStep;
use Oriel\Processing\RedirectStep;

class FormProcessor
{
    private FormRegistry $registry;

    public function __construct(FormRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function process(
        string $formId,
        array $formConfig,
        array $submittedData,
        bool $isRest = false
    ): ProcessingContext {
        $context = new ProcessingContext($formId, $formConfig, $submittedData, $isRest);

        $steps = [
            new ValidateStep(),
            new CreatePostStep(),
            new HooksStep(),
            new EmailStep(),
            new CleanupStep(),
            new RedirectStep(),
        ];

        foreach ($steps as $step) {
            $context = $step->process($context);
        }

        return $context;
    }
}
```

**Step 9: Commit**

```bash
git add src/plugins/oriel/src/Processing/ src/plugins/oriel/src/Email/ src/plugins/oriel/src/FormProcessor.php
git commit -m "feat(oriel): processing pipeline with 6 steps, email notifier, form processor"
```

---

### Task 8: Integration Test — Full Roundtrip

This task verifies the full plugin works end-to-end by registering a test form, checking it renders, and reviewing the submission flow code paths.

**Files:**
- No new files — manual verification steps

**Step 1: Verify composer autoload is working**

Run: `cd src/plugins/oriel && composer dump-autoload`
Expected: autoload files generated, no errors

**Step 2: Verify plugin activates without errors**

Activate the plugin in WordPress admin (or via WP-CLI if available):
Run: `cd /Users/josh/Sites/colossus && wp plugin activate oriel --path=. 2>&1 || echo "Check WP-CLI availability"`

If no WP-CLI, verify manually that the plugin appears in WordPress admin and can be activated.

**Step 3: Register a test form for manual verification**

Add a temporary test form registration to verify everything connects. This can be done by adding a `mu-plugin` or filter in the theme temporarily:

```php
add_filter('oriel_forms', function ($forms) {
    $forms['test-form'] = [
        'title' => 'Test Form',
        'options' => [
            'confirmation' => 'Thanks for submitting!',
            'submit_text' => 'Send',
        ],
        'fields' => [
            ['id' => 'name', 'name' => 'Name', 'type' => 'text', 'required' => true],
            ['id' => 'email', 'name' => 'Email', 'type' => 'email', 'required' => true],
            ['id' => 'message', 'name' => 'Message', 'type' => 'textarea'],
        ],
    ];
    return $forms;
});
```

Verify by placing `<?php echo oriel_form('test-form'); ?>` in a template and checking the rendered HTML in browser.

**Step 4: Review all files for consistency**

Check that:
- All namespaces match file paths
- All `use` statements reference correct classes
- `ORIEL_META_PREFIX` constant used consistently
- Nonce field name matches between renderer and processor
- Transient key logic matches between FormRenderer and RedirectStep

**Step 5: Commit any fixes**

```bash
git add -A src/plugins/oriel/
git commit -m "fix(oriel): address integration issues found during verification"
```

---

### Task 9: Add .gitignore and clean up

**Files:**
- Create: `src/plugins/oriel/.gitignore`
- Modify: `src/plugins/oriel/README.md`

**Step 1: Create .gitignore**

```
/vendor/
```

**Step 2: Update README.md**

Update `src/plugins/oriel/README.md` with basic usage docs matching the design doc's form definition format, shortcode usage, helper functions, and hooks reference.

**Step 3: Final commit**

```bash
git add src/plugins/oriel/.gitignore src/plugins/oriel/README.md
git commit -m "chore(oriel): add gitignore and update README with usage docs"
```

---

## Task Dependency Summary

```
Task 1 (bootstrap) ─┬─► Task 2 (CPT)
                     ├─► Task 3 (registry)
                     └─► Task 5 (field type registry)
                              │
Task 4 (fields) ──────────────┤
                              ▼
                     Task 6 (renderer)
                              │
                     Task 7 (pipeline) ──► Task 8 (integration) ──► Task 9 (cleanup)
```

Tasks 2, 3, 4 can be done in parallel after Task 1. Task 5 depends on Task 4. Tasks 6-7 depend on everything before them. Tasks 8-9 are final.
