<?php

namespace Oriel\Processing;

use Oriel\Captcha\CaptchaProviderInterface;
use Oriel\Captcha\RecaptchaProvider;
use Oriel\Captcha\TurnstileProvider;

class CaptchaStep implements StepInterface
{
    private const PROVIDERS = [
        'recaptcha' => RecaptchaProvider::class,
        'turnstile' => TurnstileProvider::class,
    ];

    public function process(ProcessingContext $context): ProcessingContext
    {
        if ($context->shouldHalt) {
            return $context;
        }

        $captchaField = $this->findCaptchaField($context->formConfig['fields'] ?? []);

        if (!$captchaField) {
            return $context;
        }

        $provider = $captchaField['provider'] ?? '';
        $secret = $captchaField['secret'] ?? '';
        $fieldId = $captchaField['id'];

        if (!$provider || !$secret) {
            return $context;
        }

        $token = $context->submittedData['_captcha_token'] ?? '';

        if (empty($token)) {
            $context->errors[$fieldId] = 'Verification failed. Please try again.';
            $context->shouldHalt = true;

            return $context;
        }

        $providerInstance = $this->getProvider($provider);

        if (!$providerInstance) {
            return $context;
        }

        if (!$providerInstance->verify($token, $secret)) {
            $context->errors[$fieldId] = 'Verification failed. Please try again.';
            $context->shouldHalt = true;
        }

        return $context;
    }

    /**
     * Find the first captcha field in the form config.
     *
     * Triggers _doing_it_wrong if multiple captcha fields are found.
     */
    private function findCaptchaField(array $fields): ?array
    {
        $found = null;

        foreach ($fields as $field) {
            if (($field['type'] ?? '') !== 'captcha') {
                continue;
            }

            if ($found !== null) {
                _doing_it_wrong(
                    __METHOD__,
                    'Only one captcha field per form is supported. Additional captcha fields will be ignored.',
                    ORIEL_VERSION
                );

                break;
            }

            $found = $field;
        }

        return $found;
    }

    /**
     * Resolve a provider instance by name.
     */
    private function getProvider(string $provider): ?CaptchaProviderInterface
    {
        $providers = apply_filters('oriel_captcha_providers', self::PROVIDERS);

        if (!isset($providers[$provider])) {
            return null;
        }

        $class = $providers[$provider];

        return new $class();
    }
}
