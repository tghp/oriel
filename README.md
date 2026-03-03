# Oriel

Developer-first WordPress form plugin. Define forms entirely in code — no admin UI. Independent of Meta Box, but mirrors its field definition format for easy migration.

## Defining Forms

Register forms via the `oriel_forms` filter:

```php
add_filter('oriel_forms', function (array $forms): array {
    $forms['contact'] = [
        'title'   => 'Contact Form',
        'options' => [
            'confirmation'            => 'Thanks for your message!',
            'redirect'                => '',
            'ajax'                    => false,
            'email'                   => ['email' => 'hello@example.com', 'title' => 'New Contact'],
            'delete_after_processing' => false,
            'class'                   => '',
            'submit_class'            => 'btn',
            'submit_text'             => 'Send',
        ],
        'fields' => [
            ['id' => 'name',    'name' => 'Name',    'type' => 'text',     'required' => true, 'email' => true],
            ['id' => 'email',   'name' => 'Email',   'type' => 'email',    'required' => true, 'email' => true],
            ['id' => 'message', 'name' => 'Message',  'type' => 'textarea', 'email' => true],
        ],
    ];
    return $forms;
});
```

## Rendering Forms

**PHP function:**

```php
echo oriel_form('contact');
```

**Shortcode:**

```
[oriel_form id="contact" title="Get in Touch"]
```

Shortcode parameters: `id`, `title`, `hide`, `hide_button_label`, `hide_button_class`, `background`

## Field Types

Built-in: `text`, `email`, `textarea`, `checkbox`, `select`, `radio`, `hidden`

### Field Configuration

| Key           | Description                                           |
| ------------- | ----------------------------------------------------- |
| `id`          | Field identifier (used in meta storage and form data) |
| `name`        | Label text                                            |
| `type`        | Field type string                                     |
| `required`    | Boolean, adds validation and required attribute       |
| `std`         | Default value (string or callable)                    |
| `placeholder` | Placeholder text                                      |
| `desc`        | Description text below field (or inline for checkbox) |
| `email`       | Boolean, include field in email notifications         |
| `class`       | Extra CSS class on field wrapper                      |
| `attributes`  | Array of extra HTML attributes on the input           |
| `options`     | Key/value pairs for select and radio fields           |

### Custom Field Types

```php
add_filter('oriel_field_types', function (array $types): array {
    $types['phone'] = MyPhoneField::class; // must implement Oriel\Field\FieldInterface
    return $types;
});
```

## Form Options

| Key                       | Description                                                                               |
| ------------------------- | ----------------------------------------------------------------------------------------- |
| `redirect`                | URL to redirect to after submission                                                       |
| `confirmation`            | Success message shown after submission                                                    |
| `ajax`                    | Boolean, enables AJAX submission via REST API                                             |
| `email`                   | Array with `email` (recipient) and `title` (subject) keys                                 |
| `delete_after_processing` | Delete the submission post after hooks fire (unless `_oriel_do_not_delete` meta is `'1'`) |
| `class`                   | Extra CSS class on form wrapper                                                           |
| `submit_class`            | CSS class on submit button                                                                |
| `submit_text`             | Submit button label (default: `Submit`)                                                   |
| `compat`                  | Compat mode string. `'tghpmb'` enables Meta Box frontend submission output parity.        |
| `compat_prefix`           | Field prefix for compat mode (e.g. `'_tghpcontact_'`). Falls back to `_tghp{formId}_`.   |

## Submission Storage

Submissions are stored as the `oriel_submission` custom post type. Field values are stored as post meta with `_oriel_` prefix.

```php
$value = oriel_get_submission_data($postId, 'email');
// equivalent to: get_post_meta($postId, '_oriel_email', true);
```

## REST API

AJAX submissions post to: `POST /wp-json/oriel/v1/submit`

Body: `{ oriel_form_id, oriel: { field_id: value } }`

Nonce (`_oriel_nonce`) is only required for logged-in users. See [Security](#security) below.

## Validation

A validate step in the processing pipeline calls `validate()` on each registered field type. Each field has it's own requirements as to what is a valid field.

### Custom Validation

Use the `oriel_validate` (all forms) or `oriel_validate_{$formId}` (specific form) filters to add custom validation. Both receive three arguments:

| Argument      | Type    | Description                                      |
| ------------- | ------- | ------------------------------------------------ |
| `$errors`     | `array` | Existing errors from built-in field validation   |
| `$data`       | `array` | Sanitized submission data, keyed by field ID     |
| `$formConfig` | `array` | Full form configuration (`fields`, `options`, etc.) |

Return the `$errors` array. Keys are field IDs, values are error message strings. Any non-empty errors array halts processing — no post is created, no emails are sent.

```php
// Cross-field validation on a specific form
add_filter('oriel_validate_registration', function (array $errors, array $data, array $config): array {
    if (!empty($data['password']) && $data['password'] !== ($data['confirm_password'] ?? '')) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    return $errors;
}, 10, 3);

// Validation across all forms
add_filter('oriel_validate', function (array $errors, array $data, array $config): array {
    if (!empty($data['url']) && !wp_http_validate_url($data['url'])) {
        $errors['url'] = 'Please enter a valid URL.';
    }

    return $errors;
}, 10, 3);
```

Per-field errors display inline next to their field. For REST/AJAX submissions, errors are returned as JSON with a `422` status:

```json
{ "success": false, "errors": { "confirm_password": "Passwords do not match." } }
```

## Security

Oriel uses a multi-layered security approach designed to work under full-page caching. WP nonces are only verified for logged-in users (cached pages serve stale tokens to anonymous visitors). All other checks run unconditionally.

**Built-in checks (in order):**

1. **Honeypot** — hidden field that bots auto-fill. Field name is dynamically chosen to avoid collisions with form field IDs.
2. **Rate limiting** — IP-based throttle using transients. Sliding window: the counter resets after a full window of inactivity.
3. **Timing** — rejects submissions that arrive too quickly (< 3s) or too long after render (> 24h).
4. **Nonce** — standard WP nonce verification, logged-in users only.

### Security Hooks

| Filter                               | Description                                            | Default                                                    |
| ------------------------------------ | ------------------------------------------------------ | ---------------------------------------------------------- |
| `oriel_security_checks`              | Array of `SecurityCheckInterface` instances to run     | All 4 built-in checks                                      |
| `oriel_security_honeypot_candidates` | Array of candidate honeypot field names                | 22 tempting names (`address_line_1`, `phone_number`, etc.) |
| `oriel_security_rate_limit`          | Max submissions per window                             | `5`                                                        |
| `oriel_security_rate_window`         | Rate limit window in seconds                           | `600` (10 min)                                             |
| `oriel_security_min_time`            | Minimum seconds between render and submit              | `3`                                                        |
| `oriel_security_max_time`            | Maximum seconds between render and submit              | `86400` (24h)                                              |
| `oriel_security_error_message`       | Rejection message (keep generic to avoid info leakage) | `'Submission rejected.'`                                   |

#### Adding a custom security check

```php
use Oriel\Security\SecurityCheckInterface;
use Oriel\Processing\ProcessingContext;

class RecaptchaCheck implements SecurityCheckInterface
{
    public function check(ProcessingContext $context): ?string
    {
        // Verify reCAPTCHA token...
        return null; // null = pass, string = rejection message
    }
}

add_filter('oriel_security_checks', function (array $checks): array {
    $checks[] = new RecaptchaCheck();
    return $checks;
});
```

#### Adjusting rate limits

```php
add_filter('oriel_security_rate_limit', fn () => 10);     // 10 submissions
add_filter('oriel_security_rate_window', fn () => 300);    // per 5 minutes
```

## Hooks

All hooks pass `$formId` and/or `$config` (the form configuration array) unless noted otherwise. Filters must return the filtered value.

### Registration

| Hook | Type | Return | Args | Description |
| ---- | ---- | ------ | ---- | ----------- |
| `oriel_forms` | filter | `array` | `$forms` | Define forms. Keys are form IDs, values are config arrays. |
| `oriel_fields` | filter | `array` | `$fields` | Modify fields globally across all forms |
| `oriel_fields_{$formId}` | filter | `array` | `$fields` | Modify fields for a specific form |
| `oriel_field_types` | filter | `array` | `$types` | Register custom field types. Keys are type slugs, values are `FieldInterface` class names. |

### Rendering

| Hook | Type | Return | Args | Description |
| ---- | ---- | ------ | ---- | ----------- |
| `oriel_form_html` | filter | `string` | `$html, $formId, $config, $args` | Full rendered form HTML. Receives shortcode/display `$args` as 4th arg. |
| `oriel_field_html` | filter | `string` | `$html, $field, $formId` | Individual field HTML |
| `oriel_submit_button` | filter | `string` | `$html, $formId, $config` | Submit button HTML |
| `oriel_form_before` | filter | `string` | `$html, $formId, $config` | HTML inserted before the form (default `''`) |
| `oriel_form_after` | filter | `string` | `$html, $formId, $config` | HTML inserted after the form (default `''`) |

#### Form IDs and Attributes

| Hook | Type | Return | Args | Description |
| ---- | ---- | ------ | ---- | ----------- |
| `oriel_form_wrapper_id` | filter | `string` | `$id, $formId, $config` | Outer wrapper div id (default `oriel-{formId}`). Return empty to omit. |
| `oriel_form_element_id` | filter | `string` | `$id, $formId, $config` | `<form>` element id (default `oriel-form-{formId}`). Return empty to omit. |
| `oriel_form_element_attrs` | filter | `array` | `$attrs, $formId, $config` | Extra attributes on `<form>` (default `['novalidate' => 'novalidate', 'autocomplete' => 'off']`) |
| `oriel_form_fields_wrapper_attrs` | filter | `array` | `$attrs, $formId, $config` | Extra attributes on fields wrapper div (default `[]`) |

#### Form CSS Classes

| Hook | Type | Return | Args | Description |
| ---- | ---- | ------ | ---- | ----------- |
| `oriel_form_wrapper_class` | filter | `string` | `$class, $formId, $config` | Outer wrapper div class |
| `oriel_form_title_class` | filter | `string` | `$class, $formId, $config` | Title element class |
| `oriel_form_message_class` | filter | `string` | `$class, $formId, $type` | Message class. `$type` is `'success'` or `'error'`. |
| `oriel_form_element_class` | filter | `string` | `$class, $formId, $config` | `<form>` element class |
| `oriel_form_use_fields_wrapper` | filter | `bool` | `$use, $formId, $config` | Whether to wrap fields in a container div (default `true`) |
| `oriel_form_fields_wrapper_class` | filter | `string` | `$class, $formId, $config` | Fields container div class |
| `oriel_form_submit_class` | filter | `string` | `$class, $formId, $config` | Submit button wrapper class |
| `oriel_form_submit_inner_class` | filter | `string` | `$class, $formId, $config` | Submit button inner wrapper class (default `oriel-form__submit-input`) |
| `oriel_form_toggle_class` | filter | `string` | `$class, $formId` | Toggle button class (when using `hide` option) |
| `oriel_form_hidden_class` | filter | `string` | `$class, $formId` | Hidden form wrapper class (when using `hide` option) |

#### Form Content Insertion

All return `string` (HTML), default `''`. Args: `$html, $formId, $config`.

| Hook | Type | Description |
| ---- | ---- | ----------- |
| `oriel_form_fields_before` | filter | HTML inserted before field list |
| `oriel_form_fields_after` | filter | HTML inserted after field list |
| `oriel_form_submit_before` | filter | HTML inserted before submit button |
| `oriel_form_submit_after` | filter | HTML inserted after submit button |

#### Field IDs, Names, and Attributes

All args: `$value, $field, $formId`.

| Hook | Type | Return | Description |
| ---- | ---- | ------ | ----------- |
| `oriel_field_input_id` | filter | `string` | Input element id (default `oriel_{fieldId}`). Propagates to label `for`, error `data-error-for`, and input name. |
| `oriel_field_input_name` | filter | `string` | Input element name (default `oriel[oriel_{fieldId}]`) |
| `oriel_field_input_attrs` | filter | `array` | Extra attributes on input element (default `[]`) |
| `oriel_field_label_wrapper_attrs` | filter | `array` | Extra attributes on label wrapper div (default `[]`) |
| `oriel_form_submit_button_attrs` | filter | `array` | Extra attributes on submit button (default `[]`). Args: `$attrs, $formId, $config`. |

#### Field CSS Classes

All return `string`. Args: `$class, $field, $formId`.

| Hook | Type | Description |
| ---- | ---- | ----------- |
| `oriel_field_wrapper_class` | filter | Field outer wrapper class |
| `oriel_field_use_label_wrapper` | filter | `bool` — whether to wrap label in a div (default `true`) |
| `oriel_field_label_wrapper_class` | filter | Label wrapper div class |
| `oriel_field_label_class` | filter | `<label>` element class |
| `oriel_field_input_wrapper_class` | filter | Input wrapper div class (default `oriel-field__input`) |
| `oriel_field_input_class` | filter | Input element class (default `''`) |
| `oriel_field_required_symbol` | filter | Required indicator character (default `'*'`) |
| `oriel_field_required_class` | filter | Required indicator `<span>` class |
| `oriel_field_description_class` | filter | Description paragraph class |
| `oriel_field_error_class` | filter | Error message div class |
| `oriel_field_radios_class` | filter | Radio button group wrapper class |

### Security

| Hook | Type | Return | Args | Description |
| ---- | ---- | ------ | ---- | ----------- |
| `oriel_security_checks` | filter | `array` | `$checks` | Array of `SecurityCheckInterface` instances to run |
| `oriel_security_honeypot_candidates` | filter | `array` | `$candidates` | Candidate honeypot field names |
| `oriel_security_rate_limit` | filter | `int` | `$max` | Max submissions per window (default `5`) |
| `oriel_security_rate_window` | filter | `int` | `$seconds` | Rate limit window in seconds (default `600`) |
| `oriel_security_min_time` | filter | `int` | `$seconds` | Minimum seconds between render and submit (default `3`) |
| `oriel_security_max_time` | filter | `int` | `$seconds` | Maximum seconds between render and submit (default `86400`) |
| `oriel_security_error_message` | filter | `string` | `$message` | Rejection message (keep generic to avoid info leakage) |

### Processing

| Hook | Type | Return | Args | Description |
| ---- | ---- | ------ | ---- | ----------- |
| `oriel_validate` | filter | `array` | `$errors, $data, $config` | Custom validation across all forms. See [Custom Validation](#custom-validation). |
| `oriel_validate_{$formId}` | filter | `array` | `$errors, $data, $config` | Custom validation for a specific form |
| `oriel_after_process` | action | — | `$formId, $postId` | Fires after successful submission processing |
| `oriel_after_process_{$formId}` | action | — | `$postId` | Fires after successful submission processing for a specific form |

### Email

| Hook | Type | Return | Args | Description |
| ---- | ---- | ------ | ---- | ----------- |
| `oriel_email_to` | filter | `string` | `$to, $formId, $postId` | Recipient email address |
| `oriel_email_subject` | filter | `string` | `$subject, $formId, $postId` | Email subject line |
| `oriel_email_content` | filter | `string` | `$html, $formId, $postId` | Email HTML body |

## Compat Mode

Oriel can emulate the HTML output of other form plugins so existing CSS applies without changes. Compat mode is enabled per-form via the `compat` option.

### tghpmb (Meta Box Frontend Submission)

Swaps all Oriel class names and DOM attributes to match Meta Box's `rwmb-*` output. Existing stylesheets targeting `.tghpform`, `.rwmb-field`, `.rwmb-label`, `.rwmb-input`, etc. will apply.

```php
$forms['contact_block_generic'] = [
    'options' => [
        'compat'        => 'tghpmb',
        'compat_prefix' => '_tghpcontact_',
        'submit_class'  => 'rwmb-button button button--blue-dark',
    ],
    'fields' => [
        ['id' => 'sender_email', 'name' => 'Email',   'type' => 'email', 'required' => true, 'placeholder' => 'Email'],
        ['id' => 'message',      'name' => 'Message', 'type' => 'text',  'required' => true, 'placeholder' => 'Message'],
    ],
];
```

**What changes:** Outer wrapper class (`tghpform`), form element class/id (`rwmb-form mbfs-form`), fields wrapper (`rwmb-form-fields form`), field wrappers (`rwmb-field rwmb-{type}-wrapper`), label/input wrappers (`rwmb-label`/`rwmb-input`), required span (`rwmb-required`), input classes (`rwmb-{type}`), submit structure (`rwmb-button-wrapper`), and `aria-labelledby` attributes using the configured prefix.

**What stays the same:** Input `id`/`name` format (still `oriel_*`), security fields, error placeholders, form processing pipeline.
