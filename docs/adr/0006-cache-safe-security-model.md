# Nonces are verified for logged-in users only; anonymous traffic gets layered checks instead

This contradicts WordPress dogma ("always verify nonces") and is deliberate. Under full-page caching, anonymous visitors receive cached pages containing stale nonces, so verifying nonces for them rejects legitimate submissions. Instead, the nonce check applies only to logged-in users (whose pages are not cached), and every submission passes a layered set of security checks that work under caching: honeypot, IP-based rate limiting, and render-to-submit timing. All rejections return one generic message to avoid telling bots which check caught them.

Two related choices follow from the same reasoning:

- The REST endpoint's `permission_callback` is `__return_true` — authentication would break anonymous submissions; abuse control lives in the pipeline's security step, not REST auth.
- Rate limiting keys off `REMOTE_ADDR` by default and only trusts a forwarding header (`X-Forwarded-For` etc.) when the site declares its proxy topology, because those headers are client-controlled.

## Considered Options

- Always verify nonces (the design's original position) — rejected once full-page caching was accounted for.
- Exclude form pages from cache — rejected; forms appear on high-traffic pages and Oriel cannot dictate hosting.

## Consequences

- Do not "fix" the missing anonymous nonce check or the open `permission_callback`; both are load-bearing.
- Anything else Oriel does per-visitor must also survive caching (see the sessions issue in the tracker).
