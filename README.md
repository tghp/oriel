# Oriel

Developer-first WordPress form plugin. Define forms entirely in code — no admin UI. Independent of Meta Box, but mirrors its field definition format for easy migration.

## Documentation

- [Defining & Rendering Forms](docs/defining-forms.md) — register forms via the `oriel_forms` filter and output them with `oriel_form()` or the shortcode
- [Field Types](docs/fields.md) — built-in types, field configuration, custom field types, and captcha
- [Form Options](docs/form-options.md) — per-form options (`redirect`, `confirmation`, `email`, `compat`, etc.)
- [Submissions](docs/submissions.md) — submission storage, AJAX submissions, and the REST API
- [Validation](docs/validation.md) — the validate step and custom validation filters
- [Security](docs/security.md) — layered security checks, hooks, and client IP resolution
- [Hooks](docs/hooks.md) — full reference of registration, rendering, security, processing, and email hooks
- [Compat Mode](docs/compat-mode.md) — emulate other plugins' HTML output (e.g. Meta Box `rwmb-*`)
