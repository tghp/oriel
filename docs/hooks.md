## Hooks

All hooks pass `$formId` and/or `$config` (the form configuration array) unless noted otherwise. Filters must return the filtered value.

### Registration

| Hook                     | Type   | Return  | Args      | Description                                                                                |
| ------------------------ | ------ | ------- | --------- | ------------------------------------------------------------------------------------------ |
| `oriel_forms`            | filter | `array` | `$forms`  | Define forms. Keys are form IDs, values are config arrays.                                 |
| `oriel_fields`           | filter | `array` | `$fields` | Modify fields globally across all forms                                                    |
| `oriel_fields_{$formId}` | filter | `array` | `$fields` | Modify fields for a specific form                                                          |
| `oriel_field_types`      | filter | `array` | `$types`  | Register custom field types. Keys are type slugs, values are `FieldInterface` class names. |

### Rendering

| Hook                  | Type   | Return   | Args                             | Description                                                             |
| --------------------- | ------ | -------- | -------------------------------- | ----------------------------------------------------------------------- |
| `oriel_form_html`     | filter | `string` | `$html, $formId, $config, $args` | Full rendered form HTML. Receives shortcode/display `$args` as 4th arg. |
| `oriel_field_html`    | filter | `string` | `$html, $field, $formId`         | Individual field HTML                                                   |
| `oriel_submit_button` | filter | `string` | `$html, $formId, $config`        | Submit button HTML                                                      |
| `oriel_form_before`   | filter | `string` | `$html, $formId, $config`        | HTML inserted before the form (default `''`)                            |
| `oriel_form_after`    | filter | `string` | `$html, $formId, $config`        | HTML inserted after the form (default `''`)                             |

#### Form IDs and Attributes

| Hook                              | Type   | Return   | Args                       | Description                                                                                      |
| --------------------------------- | ------ | -------- | -------------------------- | ------------------------------------------------------------------------------------------------ |
| `oriel_form_wrapper_id`           | filter | `string` | `$id, $formId, $config`    | Outer wrapper div id (default `oriel-{formId}`). Return empty to omit.                           |
| `oriel_form_element_id`           | filter | `string` | `$id, $formId, $config`    | `<form>` element id (default `oriel-form-{formId}`). Return empty to omit.                       |
| `oriel_form_element_attrs`        | filter | `array`  | `$attrs, $formId, $config` | Extra attributes on `<form>` (default `['novalidate' => 'novalidate', 'autocomplete' => 'off']`) |
| `oriel_form_fields_wrapper_attrs` | filter | `array`  | `$attrs, $formId, $config` | Extra attributes on fields wrapper div (default `[]`)                                            |

#### Form CSS Classes

| Hook                              | Type   | Return   | Args                       | Description                                                            |
| --------------------------------- | ------ | -------- | -------------------------- | ---------------------------------------------------------------------- |
| `oriel_form_wrapper_class`        | filter | `string` | `$class, $formId, $config` | Outer wrapper div class                                                |
| `oriel_form_title_class`          | filter | `string` | `$class, $formId, $config` | Title element class                                                    |
| `oriel_form_message_class`        | filter | `string` | `$class, $formId, $type`   | Message class. `$type` is `'success'` or `'error'`.                    |
| `oriel_form_element_class`        | filter | `string` | `$class, $formId, $config` | `<form>` element class                                                 |
| `oriel_form_use_fields_wrapper`   | filter | `bool`   | `$use, $formId, $config`   | Whether to wrap fields in a container div (default `true`)             |
| `oriel_form_fields_wrapper_class` | filter | `string` | `$class, $formId, $config` | Fields container div class                                             |
| `oriel_form_submit_class`         | filter | `string` | `$class, $formId, $config` | Submit button wrapper class                                            |
| `oriel_form_submit_inner_class`   | filter | `string` | `$class, $formId, $config` | Submit button inner wrapper class (default `oriel-form__submit-input`) |
| `oriel_form_toggle_class`         | filter | `string` | `$class, $formId`          | Toggle button class (when using `hide` option)                         |
| `oriel_form_hidden_class`         | filter | `string` | `$class, $formId`          | Hidden form wrapper class (when using `hide` option)                   |

#### Form Content Insertion

All return `string` (HTML), default `''`. Args: `$html, $formId, $config`.

| Hook                       | Type   | Description                        |
| -------------------------- | ------ | ---------------------------------- |
| `oriel_form_fields_before` | filter | HTML inserted before field list    |
| `oriel_form_fields_after`  | filter | HTML inserted after field list     |
| `oriel_form_submit_before` | filter | HTML inserted before submit button |
| `oriel_form_submit_after`  | filter | HTML inserted after submit button  |

#### Field IDs, Names, and Attributes

All args: `$value, $field, $formId`.

| Hook                              | Type   | Return   | Description                                                                                                      |
| --------------------------------- | ------ | -------- | ---------------------------------------------------------------------------------------------------------------- |
| `oriel_field_input_id`            | filter | `string` | Input element id (default `oriel_{fieldId}`). Propagates to label `for`, error `data-error-for`, and input name. |
| `oriel_field_input_name`          | filter | `string` | Input element name (default `oriel[{fieldId}]`)                                                                  |
| `oriel_field_input_attrs`         | filter | `array`  | Extra attributes on input element (default `[]`)                                                                 |
| `oriel_field_label_wrapper_attrs` | filter | `array`  | Extra attributes on label wrapper div (default `[]`)                                                             |
| `oriel_form_submit_button_attrs`  | filter | `array`  | Extra attributes on submit button (default `[]`). Args: `$attrs, $formId, $config`.                              |

#### Field CSS Classes

All return `string`. Args: `$class, $field, $formId`.

| Hook                              | Type   | Description                                              |
| --------------------------------- | ------ | -------------------------------------------------------- |
| `oriel_field_wrapper_class`       | filter | Field outer wrapper class                                |
| `oriel_field_use_label_wrapper`   | filter | `bool` — whether to wrap label in a div (default `true`) |
| `oriel_field_label_wrapper_class` | filter | Label wrapper div class                                  |
| `oriel_field_label_class`         | filter | `<label>` element class                                  |
| `oriel_field_input_wrapper_class` | filter | Input wrapper div class (default `oriel-field__input`)   |
| `oriel_field_input_class`         | filter | Input element class (default `''`)                       |
| `oriel_field_required_symbol`     | filter | Required indicator character (default `'*'`)             |
| `oriel_field_required_class`      | filter | Required indicator `<span>` class                        |
| `oriel_field_description_class`   | filter | Description paragraph class                              |
| `oriel_field_error_class`         | filter | Error message div class                                  |
| `oriel_field_radios_class`        | filter | Radio button group wrapper class                         |

### Security

| Hook                                 | Type   | Return   | Args          | Description                                                 |
| ------------------------------------ | ------ | -------- | ------------- | ----------------------------------------------------------- |
| `oriel_security_checks`              | filter | `array`  | `$checks`     | Array of `SecurityCheckInterface` instances to run          |
| `oriel_security_honeypot_candidates` | filter | `array`  | `$candidates` | Candidate honeypot field names                              |
| `oriel_security_rate_limit`          | filter | `int`    | `$max`        | Max submissions per window (default `5`)                    |
| `oriel_security_rate_window`         | filter | `int`    | `$seconds`    | Rate limit window in seconds (default `600`)                |
| `oriel_security_min_time`            | filter | `int`    | `$seconds`    | Minimum seconds between render and submit (default `3`)     |
| `oriel_security_max_time`            | filter | `int`    | `$seconds`    | Maximum seconds between render and submit (default `86400`) |
| `oriel_security_error_message`       | filter | `string` | `$message`    | Rejection message (keep generic to avoid info leakage)      |
| `oriel_trusted_ip_header`            | filter | `?string` | `$header`    | Forwarding header to resolve the client IP from (default `ORIEL_TRUSTED_IP_HEADER` constant or `null`) |
| `oriel_trusted_ip_environment`       | filter | `?string` | `$environment` | Environment shorthand: `cloudflare`, `kinsta`, `wpengine` (default `ORIEL_TRUSTED_IP_ENVIRONMENT` constant or `null`) |
| `oriel_client_ip`                    | filter | `string` | `$ip`         | Final resolved client IP used for rate limiting             |
| `oriel_captcha_providers`            | filter | `array`  | `$providers`  | Map of provider slug → class name for captcha verification  |

### Processing

| Hook                            | Type   | Return  | Args                      | Description                                                                      |
| ------------------------------- | ------ | ------- | ------------------------- | -------------------------------------------------------------------------------- |
| `oriel_validate`                | filter | `array` | `$errors, $data, $config` | Custom validation across all forms. See [Custom Validation](validation.md#custom-validation). |
| `oriel_validate_{$formId}`      | filter | `array` | `$errors, $data, $config` | Custom validation for a specific form                                            |
| `oriel_after_process`           | action | —       | `$formId, $postId`        | Fires after successful submission processing                                     |
| `oriel_after_process_{$formId}` | action | —       | `$postId`                 | Fires after successful submission processing for a specific form                 |

### Email

| Hook                  | Type   | Return   | Args                         | Description             |
| --------------------- | ------ | -------- | ---------------------------- | ----------------------- |
| `oriel_email_to`      | filter | `string` | `$to, $formId, $postId`      | Recipient email address |
| `oriel_email_subject` | filter | `string` | `$subject, $formId, $postId` | Email subject line      |
| `oriel_email_content` | filter | `string` | `$html, $formId, $postId`    | Email HTML body         |
