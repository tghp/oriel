<?php

namespace Oriel\Security;

use Oriel\Processing\ProcessingContext;

class NonceCheck implements SecurityCheckInterface
{
    public function check(ProcessingContext $context): ?string
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $nonce = $_POST['_oriel_nonce'] ?? '';

        if (!wp_verify_nonce($nonce, 'oriel_submit_' . $context->formId)) {
            return 'Submission rejected.';
        }

        return null;
    }
}
