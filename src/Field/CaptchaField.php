<?php

namespace Oriel\Field;

class CaptchaField extends AbstractField
{
    private const SDK_URLS = [
        'recaptcha' => 'https://www.google.com/recaptcha/api.js?onload=orielCaptchaReady&render=explicit',
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=orielCaptchaReady&render=explicit',
    ];

    protected function getType(): string
    {
        return 'captcha';
    }

    public function isTransient(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $field, $value): ?string
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function sanitize(array $field, $value)
    {
        return '';
    }

    /**
     * Override label rendering to produce a screen-reader-only label.
     */
    protected function renderLabel(array $field, string $formId): string
    {
        $labelText = !empty($field['name']) ? $field['name'] : 'Verification';
        $inputId = $this->getInputId($field, $formId);
        $labelId = $inputId . '-label';

        return '<label id="' . esc_attr($labelId) . '" class="oriel-field__label screen-reader-text">'
            . esc_html($labelText)
            . '</label>';
    }

    protected function renderInput(array $field, $value, string $formId): string
    {
        $provider = $field['provider'] ?? '';
        $sitekey = $field['sitekey'] ?? '';

        if (!$provider || !$sitekey) {
            return '';
        }

        $this->enqueueProviderScript($provider);

        // oriel.js targets .oriel-captcha, so the filtered class is appended
        // rather than replacing it.
        $class = 'oriel-captcha';
        $inputClass = apply_filters('oriel_field_input_class', '', $field, $formId);

        if ($inputClass) {
            $class .= ' ' . $inputClass;
        }

        $html = '<div class="' . esc_attr($class) . '"';
        $html .= ' data-captcha-provider="' . esc_attr($provider) . '"';
        $html .= ' data-captcha-sitekey="' . esc_attr($sitekey) . '"';
        $html .= '></div>';
        $html .= '<input type="hidden" name="oriel[_captcha_token]" value="" />';

        return $html;
    }

    private function enqueueProviderScript(string $provider): void
    {
        $url = self::SDK_URLS[$provider] ?? null;

        if (!$url) {
            return;
        }

        $handle = 'oriel-captcha-' . $provider;

        wp_enqueue_script($handle, $url, ['oriel'], null, true);
    }
}
