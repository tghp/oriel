## Submission Storage

Submissions are stored as the `oriel_submission` custom post type. Field values are stored as post meta with `_oriel_` prefix.

```php
$value = oriel_get_submission_data($postId, 'email');
// equivalent to: get_post_meta($postId, '_oriel_email', true);
```

## AJAX Submissions

When `'ajax' => true` is set in form options, the form submits via `fetch()` to the REST API instead of a full page reload.

- Validation errors display inline next to their fields
- Success shows the confirmation message and resets the form
- If `redirect` is set, the browser navigates after success
- Security fields (honeypot, timing token, nonce) are included automatically via `FormData`
- The timing token is regenerated after each successful submission

When `ajax` is `false` (default), forms POST normally and redirect back with `?oriel-submitted` or `?oriel-errors` query params. The JS handles scrolling to the form on reload.

The `oriel` script is enqueued automatically whenever any form renders. It provides:

1. **Scroll-to-form** — scrolls to the form on page load when `?oriel-errors={id}` or `?oriel-submitted={id}` is present
2. **Toggle buttons** — expands/collapses forms using the `hide` shortcode option
3. **AJAX submission** — only on forms with `ajax` enabled

## REST API

AJAX submissions post to: `POST /wp-json/oriel/v1/submit`

Body: `{ oriel_form_id, oriel: { field_id: value } }`

Nonce (`_oriel_nonce`) is only required for logged-in users. See [Security](security.md#security).
