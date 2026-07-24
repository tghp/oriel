# tghpmb Compat Mode Design

Target: achieve DOM structure + class name parity with tghpmb (Meta Box frontend submission) output so existing CSS applies without changes.

Reference output: `docs/tghpmbform.html`

## Core Structural Changes (Defaults)

Changes to base Oriel markup — not compat-specific.

1. **Input wrapper div** — wrap every field's input in `<div class="oriel-field__input">`. Filterable via `oriel_field_input_wrapper_class`.
2. **Submit outside fields wrapper** — move submit rendering after the fields wrapper closing tag.
3. **Submit inner wrapper div** — `<div class="oriel-form__submit-input">` inside submit wrapper, around button.
4. **Form attributes** — `novalidate` + `autocomplete="off"` on `<form>` by default. Filterable via `oriel_form_element_attrs`.
5. **Id attributes** — `id="oriel-{formId}"` on outer wrapper, `id="oriel-form-{formId}"` on `<form>`. Filterable via `oriel_form_wrapper_id`, `oriel_form_element_id`.

### Resulting default DOM

```html
<div id="oriel-{formId}" class="oriel-form oriel-form--{formId}">
  <form id="oriel-form-{formId}" method="post" class="oriel-form__form"
        enctype="multipart/form-data" novalidate="novalidate" autocomplete="off">
    {security fields}
    <div class="oriel-form__fields">
      <div class="oriel-field oriel-field--{type} oriel-field--oriel_{id}">
        <div class="oriel-field__label-wrapper">
          <label for="oriel_{id}" id="oriel_{id}-label" class="oriel-field__label">
            {name} <span class="oriel-field__required">*</span>
          </label>
        </div>
        <div class="oriel-field__input">
          <input id="oriel_{id}" name="oriel[oriel_{id}]" ... />
        </div>
        <div class="oriel-field__error" data-error-for="oriel_{id}"></div>
      </div>
    </div>
    <div class="oriel-form__submit">
      <div class="oriel-form__submit-input">
        <button type="submit">Submit</button>
      </div>
    </div>
  </form>
</div>
```

## New Filters (12)

### Form-level (4)

| Filter | Signature | Default |
|--------|-----------|---------|
| `oriel_form_wrapper_id` | `($id, $formId, $config)` | `oriel-{formId}` |
| `oriel_form_element_id` | `($id, $formId, $config)` | `oriel-form-{formId}` |
| `oriel_form_element_attrs` | `($attrs, $formId, $config)` | `['novalidate' => 'novalidate', 'autocomplete' => 'off']` |
| `oriel_form_fields_wrapper_attrs` | `($attrs, $formId, $config)` | `[]` |

### Field-level (6)

| Filter | Signature | Default |
|--------|-----------|---------|
| `oriel_field_input_wrapper_class` | `($class, $field, $formId)` | `oriel-field__input` |
| `oriel_field_input_class` | `($class, $field, $formId)` | `''` |
| `oriel_field_input_id` | `($id, $field, $formId)` | `oriel_{fieldId}` |
| `oriel_field_input_name` | `($name, $field, $formId)` | `oriel[oriel_{fieldId}]` |
| `oriel_field_label_wrapper_attrs` | `($attrs, $field, $formId)` | `[]` |
| `oriel_field_input_attrs` | `($attrs, $field, $formId)` | `[]` |

`oriel_field_input_id` filters at the `getInputId()` level — propagates to label `for`, error `data-error-for`, and name.

### Submit-level (2)

| Filter | Signature | Default |
|--------|-----------|---------|
| `oriel_form_submit_inner_class` | `($class, $formId, $config)` | `oriel-form__submit-input` |
| `oriel_form_submit_button_attrs` | `($attrs, $formId, $config)` | `[]` |

## TghpmbCompat Module

**Location:** `src/Compat/TghpmbCompat.php` — `Oriel\Compat\TghpmbCompat`

**Activation:** per-form via `'compat' => 'tghpmb'` in form options. Plugin instantiates after registry init, scans for compat-enabled forms, registers hooks. Every callback checks formId — no-ops for non-compat forms.

### Hook registrations

**Outer wrapper:**
- `oriel_form_wrapper_class` → `tghpform tghpform--{formId}`
- `oriel_form_wrapper_id` → `''` (remove)

**Form element:**
- `oriel_form_element_class` → `rwmb-form mbfs-form`
- `oriel_form_element_id` → `{formId}`

**Fields wrapper:**
- `oriel_form_fields_wrapper_class` → `rwmb-form-fields form`
- `oriel_form_fields_wrapper_attrs` → `['id' => 'form_{formId}']`

**Field wrapper:**
- `oriel_field_wrapper_class` → `rwmb-field rwmb-{type}-wrapper field-{fieldId}` + `required` class if applicable

**Label wrapper:**
- `oriel_field_label_wrapper_class` → `rwmb-label`
- `oriel_field_label_wrapper_attrs` → `['id' => '{inputId}-label']`

**Label element:**
- `oriel_field_label_class` → `''` (remove)

**Required span:**
- `oriel_field_required_class` → `rwmb-required`

**Input wrapper:**
- `oriel_field_input_wrapper_class` → `rwmb-input`

**Input element:**
- `oriel_field_input_class` → `rwmb-{type}`
- `oriel_field_input_attrs` → `['aria-labelledby' => '{inputId}-label']`

**Submit:**
- `oriel_form_submit_class` → `rwmb-field rwmb-button-wrapper rwmb-form-submit`
- `oriel_form_submit_inner_class` → `rwmb-input`
- `oriel_form_submit_button_attrs` → `['name' => 'rwmb_submit', 'value' => '1']`

**Not touched** (defaults already match):
- `oriel_form_element_attrs` — novalidate/autocomplete already default
- `oriel_field_input_id` / `oriel_field_input_name` — CSS doesn't target these
