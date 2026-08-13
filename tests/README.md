# Oriel Tests

Two suites live here:

- **Unit** (`Unit/`) — Pest, no WordPress required. WP functions are stubbed in
  `wp-stubs.php` (loaded via `Pest.php`, which also resets stubs and `$_SERVER`
  between tests). Run from the repo root: `composer test` (or
  `./vendor/bin/pest`). Scenarios involving `ORIEL_*` constants shell out to
  `bin/resolve-client-ip.php` in a fresh PHP process per test, since constants
  can't be undefined and Pest can't use PHPUnit process isolation
  ([pestphp/pest#910](https://github.com/pestphp/pest/issues/910)).
- **E2E** (`e2e/`) — Docker-backed Playwright suite, documented below.

## E2E

Docker-backed Playwright E2E suite. It runs against a vanilla WordPress install
with the Oriel plugin mounted alongside the fixture mu-plugin
(`fixtures/mu-plugins/oriel-test-fixtures.php`), which registers the forms the
specs drive and tunes security for test speed.

## Stack

`docker compose` in `tests/docker/`:

| Service   | Role                                                                                          |
| --------- | --------------------------------------------------------------------------------------------- |
| `db`      | MariaDB 11                                                                                     |
| `wp`      | WordPress php-fpm (`ORIEL_WP_TAG`, default `php8.3-fpm`)                                        |
| `nginx`   | Two ports: **8788** uncached, **8789** with FastCGI cache                                       |
| `mailpit` | SMTP sink + UI/API on **8790**                                                                  |
| `cli`     | Run-only WP-CLI helper (`ORIEL_CLI_TAG`, default `cli-php8.3`), gated behind the `tools` profile |

Port and image overrides via env vars:

| Var                       | Default       | Purpose                        |
| ------------------------- | ------------- | ------------------------------ |
| `ORIEL_HTTP_PORT`         | `8788`        | Uncached nginx port            |
| `ORIEL_HTTP_CACHED_PORT`  | `8789`        | FastCGI-cached nginx port      |
| `ORIEL_MAILPIT_PORT`      | `8790`        | Mailpit UI/API                 |
| `ORIEL_WP_TAG`            | `php8.3-fpm`  | WordPress php-fpm image tag     |
| `ORIEL_CLI_TAG`           | `cli-php8.3`  | WP-CLI image tag                |

The compose stack defines `ORIEL_TEST` in `WORDPRESS_CONFIG_EXTRA`. The fixture
mu-plugin does nothing unless that constant is truthy.

## npm scripts

From `tests/package.json`:

| Script             | What it does                                                                                              |
| ------------------ | -------------------------------------------------------------------------------------------------------- |
| `env:up`           | `compose up --wait`, then idempotent bootstrap (WP install, permalinks, plugin activation, fixture pages) |
| `env:down`         | Stop the stack                                                                                            |
| `env:destroy`      | Stop and drop volumes                                                                                     |
| `env:flush-cache`  | Clear the nginx FastCGI cache                                                                             |
| `env:bootstrap`    | Re-run the bootstrap step against a running stack                                                         |
| `env:wp`           | WP-CLI passthrough, e.g. `npm run env:wp -- post list --post_type=oriel_submission`                       |
| `test`             | Run Playwright; global-setup auto-runs `env:up` if the stack isn't reachable                              |
| `test:ui`          | Playwright UI mode                                                                                        |

## Fixture forms

Each form lives on a page whose slug is the form ID with underscores replaced by
hyphens. Every form emails a distinct recipient (`{form_id}@example.test`) with
subject `Oriel Test: {form_id}` so specs can filter in Mailpit.

| Form ID                  | Page slug                  | Exercises                                                                       |
| ------------------------ | -------------------------- | ------------------------------------------------------------------------------- |
| `kitchen_sink`           | `/kitchen-sink/`           | All 7 non-captcha field types, non-AJAX POST + redirect-back, confirmation      |
| `kitchen_sink_ajax`      | `/kitchen-sink-ajax/`      | Same fields, AJAX (REST) submission with inline confirmation                     |
| `security_min`           | `/security-min/`           | Security specs driven by `X-Oriel-Test` header overrides                         |
| `security_min_ajax`      | `/security-min-ajax/`      | AJAX (REST) identity + nonce specs; stamps `_oriel_test_user_id` after process   |
| `compat_tghpmb`          | `/compat-tghpmb/`          | `tghpmb` compat output (rwmb-* classes), prefix `_tghptest_`                     |
| `captcha_turnstile`      | `/captcha-turnstile/`      | Turnstile widget + server verification passing                                   |
| `captcha_turnstile_fail` | `/captcha-turnstile-fail/` | Turnstile widget yields a token but server verification fails                    |
| `captcha_recaptcha`      | `/captcha-recaptcha/`      | reCAPTCHA v2 widget + server verification passing                                |
| `redirect_form`          | `/redirect-form/`          | Non-AJAX submission redirecting to `/redirect-target/`                            |
| `delete_after`           | `/delete-after/`           | `delete_after_processing` — submission post removed after hooks fire             |
| `toggle`                 | `/toggle/`                 | Rendered with shortcode `hide="1"` args; toggle button expand/collapse           |

`/redirect-target/` is a plain page that `redirect_form` lands on after success.

## Per-request test controls

Both headers are only honored because the compose stack defines `ORIEL_TEST`.
**Never define `ORIEL_TEST` in production** — these controls would let clients
weaken security and spoof their rate-limit identity.

### `X-Oriel-Test` — security knob overrides

Value is JSON. Recognized keys (all integers), each mapping to the matching
`oriel_security_*` filter and winning over the fixture defaults:

| JSON key      | Filter                       |
| ------------- | ---------------------------- |
| `min_time`    | `oriel_security_min_time`    |
| `max_time`    | `oriel_security_max_time`    |
| `rate_limit`  | `oriel_security_rate_limit`  |
| `rate_window` | `oriel_security_rate_window` |

Malformed JSON is ignored; no other keys are honored. Fixture defaults (when no
override is sent) are `min_time` → `0` and `rate_limit` → `9999`; `max_time` and
`rate_window` stay at plugin defaults.

### `X-Oriel-Test-IP` — rate-limit identity

The fixture points `oriel_trusted_ip_header` at `X-Oriel-Test-IP`, so each test
can send a unique IP and get its own rate-limit bucket without bleeding into
other tests.

## Captcha keys

Captcha specs use the providers' official test keys, so they need outbound
network access to the provider verification APIs:

| Form                     | Provider  | Sitekey                                     | Secret                                      | Behavior     |
| ------------------------ | --------- | ------------------------------------------- | ------------------------------------------- | ------------ |
| `captcha_turnstile`      | Turnstile | `1x00000000000000000000AA`                  | `1x0000000000000000000000000000000AA`       | always pass  |
| `captcha_turnstile_fail` | Turnstile | `1x00000000000000000000AA`                  | `2x0000000000000000000000000000000AA`       | server fails |
| `captcha_recaptcha`      | reCAPTCHA | `6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI`  | `6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe`  | always pass  |

## State hygiene

Tests assert on unique per-test marker values and search Mailpit by marker.
There is **no per-test DB cleanup** — submissions accumulate across a run, so
uniqueness of markers is what keeps assertions isolated.
