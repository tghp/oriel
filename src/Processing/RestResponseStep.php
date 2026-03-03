<?php

namespace Oriel\Processing;

class RestResponseStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        if (!$context->isRest) {
            return $context;
        }

        if (!empty($context->errors)) {
            if (isset($context->errors['security'])) {
                $context->restResponse = new \WP_REST_Response([
                    'success' => false,
                    'message' => $context->errors['security'],
                ], 403);

                return $context;
            }

            $context->restResponse = new \WP_REST_Response([
                'success' => false,
                'errors' => $context->errors,
            ], 422);

            return $context;
        }

        $response = ['success' => true];

        if (!empty($context->formConfig['options']['confirmation'])) {
            $response['message'] = $context->formConfig['options']['confirmation'];
        }

        if (!empty($context->formConfig['options']['redirect'])) {
            $response['redirect'] = $context->formConfig['options']['redirect'];
        }

        $context->restResponse = new \WP_REST_Response($response, 200);

        return $context;
    }
}
