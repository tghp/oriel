# Oriel

Developer-first WordPress form plugin. Define forms entirely in code — no admin UI. Independent of Meta Box, but mirrors its field definition format for easy migration.

## Requirements

- PHP 7.4+
- WordPress 6.0+

## Installation

```bash
composer dump-autoload
```

Activate the plugin in WordPress admin.

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

Key | Description
--- | ---
`id` | Field identifier (used in meta storage and form data)
`name` | Label text
`type` | Field type string
`required` | Boolean, adds validation and required attribute
`std` | Default value (string or callable)
`placeholder` | Placeholder text
`desc` | Description text below field (or inline for checkbox)
`email` | Boolean, include field in email notifications
`class` | Extra CSS class on field wrapper
`attributes` | Array of extra HTML attributes on the input
`options` | Key/value pairs for select and radio fields

### Custom Field Types

```php
add_filter('oriel_field_types', function (array $types): array {
    $types['phone'] = MyPhoneField::class; // must implement Oriel\Field\FieldInterface
    return $types;
});
```

## Form Options

Key | Description
--- | ---
`redirect` | URL to redirect to after submission
`confirmation` | Success message shown after submission
`ajax` | Boolean, enables AJAX submission via REST API
`email` | Array with `email` (recipient) and `title` (subject) keys
`delete_after_processing` | Delete the submission post after hooks fire (unless `_oriel_do_not_delete` meta is `'1'`)
`class` | Extra CSS class on form wrapper
`submit_class` | CSS class on submit button
`submit_text` | Submit button label (default: `Submit`)

## Submission Storage

Submissions are stored as the `oriel_submission` custom post type. Field values are stored as post meta with `_oriel_` prefix.

```php
$value = oriel_get_submission_data($postId, 'email');
// equivalent to: get_post_meta($postId, '_oriel_email', true);
```

## REST API

AJAX submissions post to: `POST /wp-json/oriel/v1/submit`

Body: `{ oriel_form_id, _oriel_nonce, oriel: { field_id: value } }`

## Hooks

### Registration

- `oriel_forms` (filter) — define forms
- `oriel_fields` (filter) — modify fields globally
- `oriel_fields_{$formId}` (filter) — modify fields per form
- `oriel_field_types` (filter) — register custom field types

### Rendering

- `oriel_form_html` (filter) — full form HTML
- `oriel_field_html` (filter) — individual field HTML
- `oriel_submit_button` (filter) — submit button HTML
- `oriel_form_before` / `oriel_form_after` (actions)

### Processing

- `oriel_validate` / `oriel_validate_{$formId}` (filters) — custom validation
- `oriel_after_process` / `oriel_after_process_{$formId}` (actions) — post-submission hooks

### Email

- `oriel_email_to` (filter) — recipient address
- `oriel_email_subject` (filter) — subject line
- `oriel_email_content` (filter) — HTML content
