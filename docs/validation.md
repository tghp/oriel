## Validation

A validate step in the processing pipeline calls `validate()` on each registered field type. Each field has it's own requirements as to what is a valid field.

### Custom Validation

Use the `oriel_validate` (all forms) or `oriel_validate_{$formId}` (specific form) filters to add custom validation. Both receive three arguments:

| Argument      | Type    | Description                                         |
| ------------- | ------- | --------------------------------------------------- |
| `$errors`     | `array` | Existing errors from built-in field validation      |
| `$data`       | `array` | Sanitized submission data, keyed by field ID        |
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
{
  "success": false,
  "errors": { "confirm_password": "Passwords do not match." }
}
```
