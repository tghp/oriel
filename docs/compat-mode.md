## Compat Mode

Oriel can emulate the HTML output of other form plugins so existing CSS applies without changes. Compat mode is enabled per-form via the `compat` option.

### tghpmb (tghp-mb-contact / Meta Box Frontend Submission)

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
