# Security Layer Design

Replace nonce-only CSRF protection with a multi-layered security system that works under full-page caching. Nonces only verified for logged-in users; all other checks run unconditionally.

## Problem

WP nonces are user-specific tokens with a short lifespan. When pages are fully cached, anonymous users receive stale nonces — every submission fails. The previous form plugin had this exact issue.

## Architecture

New `SecurityStep` as the **first** pipeline step in `FormProcessor`. Internally orchestrates focused checker classes implementing `SecurityCheckInterface`. Short-circuits on first failure via `shouldHalt`.

```
Pipeline:  SecurityStep → ValidateStep → CreatePostStep → HooksStep → EmailStep → CleanupStep → RedirectStep
               |
               ├── HoneypotCheck      (always)
               ├── RateLimitCheck     (always)
               ├── TimingCheck        (always)
               └── NonceCheck         (logged-in only)
```

## Security Checks

### HoneypotCheck

Hidden field rendered in the form. Bots auto-fill it, humans don't. If filled, reject.

**Dynamic field name selection:** A prioritized list of "tempting" field names. For each form, iterate the list and pick the first name that doesn't collide with any existing field `id` in the form config. Deterministic — same form always gets the same honeypot name.

Candidate list (in priority order):

```
address_line_1, address_line_2, phone_number, company_email,
instagram_handle, comment, remark, feedback, notes, message,
description, additional_info, extra_details, user_bio,
profile_summary, personal_statement, about_me, contact_info,
social_media_link, website_url, linkedin_profile, twitter_handle
```

Rendering: `<input>` with `tabindex="-1"`, `autocomplete="off"`, `aria-hidden="true"`, positioned off-screen via inline style (not `display:none` — some bots skip those).

Filterable: `oriel_security_honeypot_candidates` (array of candidate names).

### RateLimitCheck

IP-based throttle using WP transients.

- Transient key: `oriel_rl_{md5(IP)}`
- Value: submission count (int)
- Expiry: the configured time window
- On each submission: increment count, reject if over limit

Defaults: 5 submissions per 600 seconds (10 minutes).

Filterable:
- `oriel_security_rate_limit` (int, default 5)
- `oriel_security_rate_window` (int seconds, default 600)

IP resolution: `$_SERVER['REMOTE_ADDR']`. No `X-Forwarded-For` parsing (unreliable/spoofable without known proxy config).

### TimingCheck

Hidden field rendered with the form containing an encoded timestamp. If submission arrives too quickly after page render (< N seconds), likely a bot.

Encoding: `base64_encode(time())` — not cryptographic, just obfuscation. Named `_oriel_tk`.

Default minimum: 3 seconds. Filterable: `oriel_security_min_time` (int seconds, default 3).

### NonceCheck

Only runs when `is_user_logged_in()` is true. Standard `wp_verify_nonce()` against `oriel_submit_{formId}`.

Replaces the existing nonce verification in `Plugin::handleSubmission()` and `Plugin::handleRestSubmission()`.

## File Structure

New files:

```
src/Security/
├── SecurityCheckInterface.php    # check(ProcessingContext): ?string
├── HoneypotCheck.php
├── RateLimitCheck.php
├── TimingCheck.php
├── NonceCheck.php
src/Processing/
├── SecurityStep.php
```

## Rendering Changes (FormRenderer.php)

- Nonce field (`wp_nonce_field`): only output when `is_user_logged_in()`
- Add honeypot hidden input (dynamic name, CSS off-screen)
- Add timing token hidden input (`_oriel_tk`)
- Honeypot and timing fields rendered via a new private method or extracted to a helper, keeping `render()` clean

## Plugin.php Changes

- Remove nonce verification from `handleSubmission()` and `handleRestSubmission()`
- The nonce check moves into `NonceCheck` inside the pipeline

## FormProcessor.php Changes

- Insert `new SecurityStep()` as the first step in the `$steps` array

## Error Handling

Security failures use a generic message — no information leakage. The error key is `security`.

- REST: 403 with `{"success": false, "message": "Submission rejected."}`
- Standard POST: halts pipeline, redirects back with errors (generic message shown)

Filterable: `oriel_security_error_message` (string).

## SecurityCheckInterface

```php
interface SecurityCheckInterface
{
    /**
     * Run the check. Return null on pass, error string on fail.
     */
    public function check(ProcessingContext $context): ?string;
}
```

## Unresolved Questions

None.
