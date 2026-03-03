<?php

namespace Oriel\Processing;

class RedirectStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        // REST responses are handled in Plugin::handleRestSubmission().
        if ($context->isRest) {
            return $context;
        }

        $baseUrl = strtok(home_url($_SERVER['REQUEST_URI'] ?? ''), '?');

        if (!empty($context->errors)) {
            $this->storeState($context->formId, [
                'errors' => $context->errors,
                'values' => $context->submittedData,
            ]);

            $redirectUrl = add_query_arg('oriel-errors', $context->formId, $baseUrl);
            wp_redirect($redirectUrl);
            exit;
        }

        $redirect = $context->formConfig['options']['redirect'] ?? '';

        if ($redirect) {
            wp_redirect($redirect);
        } else {
            $redirectUrl = add_query_arg('oriel-submitted', $context->formId, $baseUrl);
            wp_redirect($redirectUrl);
        }

        exit;
    }

    private function storeState(string $formId, array $state): void
    {
        $key = $this->getTransientKey($formId);
        set_transient($key, $state, 300);
    }

    private function getTransientKey(string $formId): string
    {
        if (is_user_logged_in()) {
            return 'oriel_state_' . get_current_user_id() . '_' . $formId;
        }

        if (!session_id()) {
            session_start();
        }

        return 'oriel_state_' . session_id() . '_' . $formId;
    }
}
