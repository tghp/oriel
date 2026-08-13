# Captcha is a transient field plus a dedicated pipeline step, not a security check

`SecurityCheckInterface` exists and a captcha could have been one more check — the security docs even use one as the example. It was built as a field type plus its own `CaptchaStep` instead, for three reasons: a captcha must **render** a widget at a developer-chosen position in the field list, and security checks have no render path; failures must display **inline next to the widget** through the field's error mechanism, not as the generic form-level security rejection; and its configuration (provider, sitekey, secret) is **per-form**, which fields already carry, where security checks are global.

Because the field renders but must not behave like data, `FieldInterface::isTransient()` exists: transient fields are skipped by validation, storage, and email. Verification runs as `CaptchaStep`, placed after the security checks and before validation.

## Consequences

- `isTransient()` is part of the field contract for every custom field type, even though captcha is its only built-in user.
- Only one captcha field per form is supported.
