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
