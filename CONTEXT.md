# Oriel

Developer-first WordPress forms plugin. Forms are defined in code, rendered by the plugin, and processed through a fixed pipeline. No admin UI.

## Language

### Forms

**Form definition**:
The declarative array — `title`, `options`, `fields` — that a developer registers under a form ID via the `oriel_forms` filter. The registry normalizes it (option defaults, field filters) before anything else sees it.
_Avoid_: form config, form configuration (code's `$formConfig` holds a normalized form definition)

**Form ID**:
The string key a form definition is registered under. It names the form everywhere: hook suffixes (`oriel_validate_{$formId}`), query args, the taxonomy term.

**Form options**:
The `options` member of a form definition. Settings that control form behavior (redirect, confirmation, ajax, email, compat) rather than what fields it has.
_Avoid_: settings

**Form registry**:
The per-request collection of all normalized form definitions, built once on `init` from the `oriel_forms` filter.

### Fields

**Field**:
One entry in a form definition's `fields` array — a configuration array (`id`, `name`, `type`, …). Pure data; behavior lives in the field type it names.
_Avoid_: field definition, field config

**Field type**:
A stateless class implementing `FieldInterface`, registered under a type slug, that renders, validates, and sanitizes any field of its type.
_Avoid_: field class, input type

**Transient field**:
A field whose type renders but is never validated, stored, or emailed. The captcha field is the only built-in one.

**Captcha provider**:
A verification backend (reCAPTCHA, Turnstile) that the captcha field delegates server-side token verification to. Registered under a provider slug.

### Processing

**Submission**:
One processed instance of submitted form data — the event, not the record. A submission exists even when nothing is stored (`delete_after_processing`).
_Avoid_: entry, form post

**Submission post**:
The stored record of a submission: an `oriel_submission` post with field values in `_oriel_`-prefixed meta, tagged with the form's taxonomy term.
_Avoid_: submission record, entry

**Processing pipeline**:
The fixed, ordered sequence of processing steps every submission passes through.
_Avoid_: workflow, chain

**Processing step**:
One stage of the pipeline. Every step runs on every submission; a step that must not act inspects the context and returns it unchanged.

**Processing context**:
The mutable object threaded through the pipeline, carrying the submission's data, errors, and outcome.
_Avoid_: DTO, state object

**Security check**:
A pre-validation inspection of a submission that either passes or returns a rejection message. Built-ins: honeypot, rate limit, timing, nonce. Distinct from validation — a failed check rejects the submission wholesale with one generic message; validation produces per-field errors.

### Compat

**Compat mode**:
Per-form emulation of another form plugin's DOM structure and class names, so CSS written for that plugin applies unchanged. Enabled with the `compat` form option; `tghpmb` is the only built-in mode.

**Compat prefix**:
The per-form field-name prefix (e.g. `_tghpcontact_`) a compat mode uses to reproduce the emulated plugin's element IDs and `aria-labelledby` targets.
