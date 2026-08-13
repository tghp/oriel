#!/bin/sh
# Idempotent WP setup for the Oriel E2E stack. Runs inside the `cli` service:
#   docker compose run --rm cli sh /opt/oriel/bootstrap.sh
set -eu

SITE_URL="http://localhost:${ORIEL_HTTP_PORT:-8788}"

if ! wp core is-installed 2>/dev/null; then
    echo "Installing WordPress at ${SITE_URL}..."
    wp core install \
        --url="${SITE_URL}" \
        --title="Oriel E2E" \
        --admin_user=admin \
        --admin_password=password \
        --admin_email=admin@example.test \
        --skip-email
fi

wp option get permalink_structure | grep -q postname \
    || wp rewrite structure '/%postname%/'

wp plugin is-active oriel 2>/dev/null || wp plugin activate oriel

# One page per fixture form (fixture mu-plugin defines the forms themselves).
create_page() {
    slug="$1"
    title="$2"
    content="$3"

    if [ -z "$(wp post list --post_type=page --name="$slug" --field=ID)" ]; then
        wp post create \
            --post_type=page \
            --post_status=publish \
            --post_name="$slug" \
            --post_title="$title" \
            --post_content="$content" \
            --porcelain >/dev/null
        echo "Created page /$slug/"
    fi
}

create_page kitchen-sink          "Kitchen Sink"           '[oriel_form id="kitchen_sink"]'
create_page kitchen-sink-ajax     "Kitchen Sink (AJAX)"    '[oriel_form id="kitchen_sink_ajax"]'
create_page security-min          "Security Minimal"       '[oriel_form id="security_min"]'
create_page security-min-ajax     "Security Minimal (AJAX)" '[oriel_form id="security_min_ajax"]'
create_page compat-tghpmb         "Compat tghpmb"          '[oriel_form id="compat_tghpmb"]'
create_page captcha-turnstile     "Captcha Turnstile"      '[oriel_form id="captcha_turnstile"]'
create_page captcha-turnstile-fail "Captcha Turnstile Fail" '[oriel_form id="captcha_turnstile_fail"]'
create_page captcha-recaptcha     "Captcha reCAPTCHA"      '[oriel_form id="captcha_recaptcha"]'
create_page redirect-form         "Redirect Form"          '[oriel_form id="redirect_form"]'
create_page redirect-target       "Redirect Target"        'Redirect landed.'
create_page delete-after          "Delete After"           '[oriel_form id="delete_after"]'
create_page toggle                "Toggle"                 '[oriel_form id="toggle" hide="1" hide_button_label="Show form"]'

echo "Bootstrap complete."
