## Field Types

Built-in: `text`, `email`, `textarea`, `checkbox`, `select`, `radio`, `hidden`, `captcha`

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

### Captcha

The `captcha` field type adds reCAPTCHA or Cloudflare Turnstile verification. The widget renders where the field is placed in the field list, and verification runs server-side as a dedicated pipeline step between security checks and validation.

```php
'fields' => [
    ['id' => 'name',    'name' => 'Name',  'type' => 'text',  'required' => true, 'email' => true],
    ['id' => 'email',   'name' => 'Email', 'type' => 'email', 'required' => true, 'email' => true],
    ['id' => 'message', 'name' => 'Message', 'type' => 'textarea', 'email' => true],
    [
        'id'       => 'captcha',
        'type'     => 'captcha',
        'provider' => 'turnstile',  // 'turnstile' or 'recaptcha'
        'sitekey'  => env('TURNSTILE_SITEKEY'),
        'secret'   => env('TURNSTILE_SECRET'),
    ],
],
```

| Key        | Description                                                         |
| ---------- | ------------------------------------------------------------------- |
| `id`       | Field identifier (user-chosen, used for error display targeting)    |
| `type`     | Must be `'captcha'`                                                 |
| `provider` | `'turnstile'` (Cloudflare) or `'recaptcha'` (Google reCAPTCHA v2)  |
| `sitekey`  | Public site key (rendered client-side)                              |
| `secret`   | Secret key (used server-side only for verification)                 |
| `name`     | Optional label text (defaults to screen-reader-only "Verification") |

**How it works:**

- The field's `render()` outputs a target div with data attributes and a hidden input (`oriel[_captcha_token]`), and enqueues the provider's SDK script
- The JS explicitly renders the widget via the provider API, writing the token into the hidden input on completion
- On form submission, `CaptchaStep` reads the token and verifies it against the provider's server-side API
- On failure, a field-level error ("Verification failed. Please try again.") displays inline near the widget
- After a successful AJAX submission, the widget resets via the provider's `reset()` API
- The captcha field is transient — it is not stored as post meta, not included in emails, and not run through field validation

Only one captcha field per form is supported. Additional captcha fields trigger a `_doing_it_wrong()` notice and are ignored.

#### Custom captcha providers

Register additional providers via the `oriel_captcha_providers` filter. Each provider must implement `Oriel\Captcha\CaptchaProviderInterface`:

```php
use Oriel\Captcha\CaptchaProviderInterface;

class HCaptchaProvider implements CaptchaProviderInterface
{
    public function verify(string $token, string $secret): bool
    {
        $response = wp_remote_post('https://api.hcaptcha.com/siteverify', [
            'body' => ['secret' => $secret, 'response' => $token],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return !empty($body['success']);
    }
}

add_filter('oriel_captcha_providers', function (array $providers): array {
    $providers['hcaptcha'] = HCaptchaProvider::class;
    return $providers;
});
```

The JS side also needs a matching entry in the `captchaProviders` map in `oriel.js` for client-side rendering.
