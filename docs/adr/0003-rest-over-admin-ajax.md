# AJAX submissions use the REST API, not admin-ajax

AJAX forms post to `POST /wp-json/oriel/v1/submit` rather than `admin-ajax.php`. Two reasons: real HTTP semantics — the endpoint returns proper status codes (200 success, 403 security rejection, 422 validation errors) with a JSON body, where admin-ajax conventionally returns 200 for everything; and a registered route with a stable URL is testable and documented, usable by consumers other than the bundled JS. The pipeline builds a `WP_REST_Response` (rather than calling `wp_send_json` and exiting) for the same reasons.

The endpoint URL and response shape are public contract — changing them breaks deployed JS and any external consumers.
