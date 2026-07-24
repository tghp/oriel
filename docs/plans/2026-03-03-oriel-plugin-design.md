# Oriel Plugin Design

Developer-first WordPress form plugin. Code-defined forms, no admin UI. Independent of Meta Box but mirrors its field definition format for easy migration from tghp-mb-contact.

## Decisions

- PHP 7.4+, PSR-4 namespaced (`Oriel\`)
- CPT storage (`oriel_submission`) + taxonomy (`oriel_form`)
- Filter-based registration (`oriel_forms`)
- Built-in rendering with filter hooks, no template overrides
- No CSS/JS shipped — unstyled, BYO
- Meta prefix: `_oriel_`
- Pipeline architecture with discrete processing steps
- AJAX via REST API (`POST /wp-json/oriel/v1/submit`), not admin-ajax
- Initial field types: text, email, textarea, checkbox, select, radio, hidden
- Field type system extensible via `oriel_field_types` filter
- Email notifications included (wp_mail)

## Plugin Structure

```
oriel/
  oriel.php                      # Entry, constants, bootstrap
  composer.json                  # PSR-4 autoload
  src/
    Plugin.php                   # Singleton, hooks registration
    FormRegistry.php             # Collects forms via oriel_forms filter
    FormRenderer.php             # Renders form HTML
    FormProcessor.php            # Orchestrates processing pipeline
    PostType.php                 # Registers oriel_submission CPT + taxonomy
    Field/
      FieldInterface.php         # render(), validate(), sanitize()
      AbstractField.php          # Shared wrapper/label/desc/error rendering
      TextField.php
      EmailField.php
      TextareaField.php
      CheckboxField.php
      SelectField.php
      RadioField.php
      HiddenField.php
    Processing/
      StepInterface.php          # process($context): $context
      ProcessingContext.php       # DTO: formId, data, postId, errors, shouldHalt
      ValidateStep.php
      CreatePostStep.php
      HooksStep.php
      EmailStep.php
      CleanupStep.php
      RedirectStep.php
    Email/
      EmailNotifier.php          # Builds + sends HTML email
```

## Form Definition Format

```php
add_filter('oriel_forms', function (array $forms): array {
    $forms['my-form'] = [
        'title'   => 'My Form',
        'options' => [
            'redirect'                => '/thanks',
            'confirmation'            => 'Thank you!',
            'ajax'                    => false,
            'email'                   => ['email' => 'to@example.com', 'title' => 'Subject'],
            'delete_after_processing' => false,
            'class'                   => '',
            'submit_class'            => '',
            'submit_text'             => 'Submit',
        ],
        'fields' => [
            [
                'id'          => 'field_name',
                'name'        => 'Label',
                'type'        => 'text',
                'required'    => false,
                'std'         => '',           // default value, can be callable
                'placeholder' => '',
                'desc'        => '',
                'email'       => false,        // include in email notification
                'class'       => '',
                'attributes'  => [],
                'options'     => [],           // select/radio: ['value' => 'Label']
            ],
        ],
    ];
    return $forms;
});
```

## Field System

### FieldInterface

```php
interface FieldInterface {
    public function render(array $field, $value): string;
    public function validate(array $field, $value): ?string;  // null=valid, string=error
    public function sanitize(array $field, $value);
}
```

### AbstractField provides

- Wrapper: `<div class="oriel-field oriel-field--{type} oriel-field--{id} {class}">`
- Label: `<label for="oriel_{formId}_{id}">{name}</label>` + required indicator
- Description: `<p class="oriel-field__desc">{desc}</p>`
- Error: `<p class="oriel-field__error">{message}</p>`

### Custom field types

```php
add_filter('oriel_field_types', function (array $types): array {
    $types['phone'] = MyPhoneField::class;
    return $types;
});
```

## Processing Pipeline

Steps run sequentially on `ProcessingContext` DTO.

### ProcessingContext

```php
class ProcessingContext {
    public string $formId;
    public array $formConfig;
    public array $submittedData;     // sanitized values
    public ?int $postId = null;
    public array $errors = [];
    public bool $shouldHalt = false;
    public bool $isRest = false;     // REST vs page POST
}
```

### Pipeline order

1. **ValidateStep** — each field's `validate()`, plus `oriel_validate` / `oriel_validate_{$formId}` filter hooks. On errors: sets shouldHalt.
2. **CreatePostStep** — `wp_insert_post` (type: `oriel_submission`, title: `"{title} - {date}"`). Saves fields as `_oriel_{id}` post meta. Sets `oriel_form` taxonomy term.
3. **HooksStep** — `do_action('oriel_after_process', $formId, $postId)` and `do_action("oriel_after_process_{$formId}", $postId)`.
4. **EmailStep** — if `email` option set, builds HTML from fields with `'email' => true`, sends via `wp_mail`.
5. **CleanupStep** — if `delete_after_processing` and `_oriel_do_not_delete` meta != '1', deletes post.
6. **RedirectStep** — REST: `wp_send_json_success/error`. Page POST: `wp_redirect`.

If shouldHalt after ValidateStep, skips 2-5, goes to RedirectStep with error state.

## Form Rendering

### HTML structure

```html
<div class="oriel-form oriel-form--{id} {options.class}">
  <div class="oriel-form__message oriel-form__message--success">...</div>
  <div class="oriel-form__message oriel-form__message--error">...</div>
  <form method="post" class="oriel-form__form">
    <input type="hidden" name="oriel_form_id" value="{id}">
    <input type="hidden" name="oriel_nonce" value="{nonce}">
    <!-- fields with oriel[field_id] names -->
    <div class="oriel-form__submit">
      <button type="submit" class="{submit_class}">{submit_text}</button>
    </div>
  </form>
</div>
```

### Entry points

- PHP: `oriel_form($id, $args = [])` returns HTML string
- Shortcode: `[oriel_form id="..." title="..." hide="0" hide_button_label="Show Form" hide_button_class="" background=""]`

### Field input naming

All inputs: `name="oriel[{field_id}]"` — namespaced to avoid collisions.

### State on reload (non-AJAX)

- Success: `?oriel-submitted={formId}` query arg, shows confirmation message
- Error: `?oriel-errors={formId}` query arg, errors + values stored in transient (keyed by user/session), repopulated on reload

## Submission Handling

### Non-AJAX

Hooked on `template_redirect`. Detects `$_POST['oriel_form_id']`. Verifies nonce (`oriel_submit_{$formId}`). Extracts data from `$_POST['oriel']`. Sanitizes. Runs pipeline.

### REST API (AJAX)

- Endpoint: `POST /wp-json/oriel/v1/submit`
- Body: `{ form_id, oriel: { ... }, oriel_nonce }`
- Permission: `__return_true` (nonce handles auth)
- Success: `{ success: true, message: "...", redirect: "..." }`
- Error: `{ success: false, errors: { field_id: "message", ... } }`

Same `FormProcessor` pipeline, `RedirectStep` checks `$context->isRest` to decide response format.

## Validation

### Built-in

- `required` check on all field types
- `EmailField`: `filter_var(FILTER_VALIDATE_EMAIL)`

### Custom validation hooks

```php
add_filter('oriel_validate', function (?array $errors, array $data, array $formConfig) { ... }, 10, 3);
add_filter('oriel_validate_{$formId}', function (?array $errors, array $data, array $formConfig) { ... }, 10, 3);
```

## Email Notifications

When `options.email` is set:
- Collects fields with `'email' => true`
- Builds HTML: `<h1>{title}</h1>` + `<p><strong>{label}</strong><br>{value}</p>` per field
- Checkbox values formatted as yes/no
- Select/radio formatted as value (label)
- Sends via `wp_mail` with `Content-Type: text/html; charset=UTF-8`

### Email filters

- `oriel_email_to` — recipient address
- `oriel_email_subject` — subject line
- `oriel_email_content` — full HTML content

## Helper Functions

```php
oriel_form(string $id, array $args = []): string   // render form
oriel_get_submission_data(int $postId, string $key): mixed  // get_post_meta with _oriel_ prefix
```

## All Hooks Reference

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
- `oriel_after_process` / `oriel_after_process_{$formId}` (actions) — post-submission

### Email
- `oriel_email_to`, `oriel_email_subject`, `oriel_email_content` (filters)

## Future Additions (not in initial release)

- Recaptcha field type
- File/image upload field type
- GraphQL mutation support
- Admin settings page
- Admin UI for viewing submissions
