# Class Filter Hooks Design

Add `apply_filters()` to every CSS class string output in the plugin, enabling full class customization.

## Hooks

### FormRenderer.php

- `oriel_form_wrapper_class` — `oriel-form oriel-form--{id}` — params: `$formId, $formConfig`
- `oriel_form_title_class` — `oriel-form__title` — params: `$formId, $formConfig`
- `oriel_form_message_class` — `oriel-form__message oriel-form__message--{type}` — params: `$formId, $type`
- `oriel_form_element_class` — `oriel-form__form` — params: `$formId, $formConfig`
- `oriel_form_submit_class` — `oriel-form__submit` — params: `$formId, $formConfig`
- `oriel_form_toggle_class` — `oriel-form__toggle` — params: `$formId`
- `oriel_form_hidden_class` — `oriel-form__hidden` — params: `$formId`

### AbstractField.php

- `oriel_field_wrapper_class` — `oriel-field oriel-field--{type} oriel-field--{id}` — params: `$fieldConfig, $formId`
- `oriel_field_required_class` — `oriel-field__required` — params: `$fieldConfig, $formId`
- `oriel_field_description_class` — `oriel-field__desc` — params: `$fieldConfig, $formId`
- `oriel_field_error_class` — `oriel-field__error` — params: `$fieldConfig, $formId`

### RadioField.php

- `oriel_field_radios_class` — `oriel-field__radios` — params: `$fieldConfig, $formId`

## Pattern

Class string passed as first param, context follows. Single string, not array.

```php
$class = apply_filters('oriel_form_wrapper_class', 'oriel-form oriel-form--' . $formId, $formId, $formConfig);
```

Consumer usage:

```php
add_filter('oriel_field_wrapper_class', function ($class, $fieldConfig, $formId) {
    if ($formId === 'contact') {
        $class .= ' custom-field';
    }
    return $class;
}, 10, 3);
```
