## Security

Oriel uses a multi-layered security approach designed to work under full-page caching. WP nonces are only verified for logged-in users (cached pages serve stale tokens to anonymous visitors). All other checks run unconditionally.

**Built-in checks (in order):**

1. **Honeypot** — hidden field that bots auto-fill. Field name is dynamically chosen to avoid collisions with form field IDs.
2. **Rate limiting** — IP-based throttle using transients. Sliding window: the counter resets after a full window of inactivity. See [Client IP resolution](#client-ip-resolution) if the site sits behind a proxy or CDN.
3. **Timing** — rejects submissions that arrive too quickly (< 3s) or too long after render (> 24h).
4. **Nonce** — standard WP nonce verification, logged-in users only.

### Security Hooks

| Filter                               | Description                                            | Default                                                    |
| ------------------------------------ | ------------------------------------------------------ | ---------------------------------------------------------- |
| `oriel_security_checks`              | Array of `SecurityCheckInterface` instances to run     | All 4 built-in checks                                      |
| `oriel_security_honeypot_candidates` | Array of candidate honeypot field names                | 22 tempting names (`address_line_1`, `phone_number`, etc.) |
| `oriel_security_rate_limit`          | Max submissions per window                             | `5`                                                        |
| `oriel_security_rate_window`         | Rate limit window in seconds                           | `600` (10 min)                                             |
| `oriel_security_min_time`            | Minimum seconds between render and submit              | `3`                                                        |
| `oriel_security_max_time`            | Maximum seconds between render and submit              | `86400` (24h)                                              |
| `oriel_security_error_message`       | Rejection message (keep generic to avoid info leakage) | `'Submission rejected.'`                                   |
| `oriel_trusted_ip_header`            | Forwarding header to resolve the client IP from        | `null` (`ORIEL_TRUSTED_IP_HEADER` constant if defined)     |
| `oriel_trusted_ip_environment`       | Hosting environment shorthand for the above            | `null` (`ORIEL_TRUSTED_IP_ENVIRONMENT` constant if defined) |
| `oriel_client_ip`                    | Final resolved client IP                               | Resolved IP (see [Client IP resolution](#client-ip-resolution)) |
| `oriel_captcha_providers`            | Map of provider slug → class name                      | `['recaptcha' => RecaptchaProvider::class, 'turnstile' => TurnstileProvider::class]` |

#### Adding a custom security check

```php
use Oriel\Security\SecurityCheckInterface;
use Oriel\Processing\ProcessingContext;

class RecaptchaCheck implements SecurityCheckInterface
{
    public function check(ProcessingContext $context): ?string
    {
        // Verify reCAPTCHA token...
        return null; // null = pass, string = rejection message
    }
}

add_filter('oriel_security_checks', function (array $checks): array {
    $checks[] = new RecaptchaCheck();
    return $checks;
});
```

#### Adjusting rate limits

```php
add_filter('oriel_security_rate_limit', fn () => 10);     // 10 submissions
add_filter('oriel_security_rate_window', fn () => 300);    // per 5 minutes
```

### Client IP resolution

Rate limiting keys off the client IP. By default this is `REMOTE_ADDR` — the only value a client can't spoof. But behind a reverse proxy or CDN (Cloudflare, a load balancer, nginx in front of PHP-FPM), `REMOTE_ADDR` is the proxy's IP, so all visitors share one rate limit bucket.

Forwarding headers like `X-Forwarded-For` are client-controlled and can't be trusted automatically — Oriel only reads one when you declare your proxy topology. Do **not** configure a header unless the proxy actually sets it; on an unproxied site this would let clients spoof their IP.

**Environment shorthand** — for common setups, name the platform:

```php
// wp-config.php
define('ORIEL_TRUSTED_IP_ENVIRONMENT', 'cloudflare');

// …or via filter
add_filter('oriel_trusted_ip_environment', fn () => 'cloudflare');
```

Supported values: `cloudflare` (CF-Connecting-IP), `kinsta` (Cloudflare-shaped), `wpengine` (X-Forwarded-For).

**Explicit header** — for anything else, name the header your proxy sets. Takes precedence over the environment shorthand:

```php
// wp-config.php
define('ORIEL_TRUSTED_IP_HEADER', 'X-Real-IP');

// …or via filter
add_filter('oriel_trusted_ip_header', fn () => 'X-Real-IP');
```

Chain-style headers (`X-Forwarded-For`) are parsed right-to-left, taking the first public IP — trusted proxies append to the right, so client-seeded entries are ignored. Every value is validated as an IP; missing or invalid headers fall back to `REMOTE_ADDR`.

**Full control** — for multi-hop or unusual topologies, filter the resolved value directly:

```php
add_filter('oriel_client_ip', function (string $ip): string {
    // Custom resolution logic...
    return $ip;
});
```
