<?php

namespace Oriel\Security;

use Oriel\Processing\ProcessingContext;

class HoneypotCheck implements SecurityCheckInterface
{
    /**
     * Candidate honeypot field names in priority order.
     * The first name not colliding with an existing form field ID is used.
     */
    private const CANDIDATES = [
        'comment',
        'remark',
        'feedback',
        'notes',
        'message',
        'website_url',
        'company_email',
        'phone_number',
        'address_line_1',
        'address_line_2',
        'instagram_handle',
        'description',
        'additional_info',
        'extra_details',
        'user_bio',
        'profile_summary',
        'personal_statement',
        'about_me',
        'contact_info',
        'social_media_link',
        'linkedin_profile',
        'twitter_handle',
    ];

    public function check(ProcessingContext $context): ?string
    {
        $fieldName = self::resolveFieldName($context->formConfig);

        if ($fieldName === null) {
            return null;
        }

        $value = $_POST[$fieldName] ?? null;

        if ($value !== null && $value !== '') {
            return 'Submission rejected.';
        }

        return null;
    }

    /**
     * Resolve the honeypot field name for a given form config.
     *
     * Picks the first candidate that doesn't collide with an existing field ID.
     * Returns null if all candidates are taken (extremely unlikely).
     *
     * Static so FormRenderer can call it at render time too.
     */
    public static function resolveFieldName(array $formConfig): ?string
    {
        $candidates = apply_filters('oriel_security_honeypot_candidates', self::CANDIDATES);

        $existingIds = array_column($formConfig['fields'] ?? [], 'id');

        foreach ($candidates as $candidate) {
            if (!in_array($candidate, $existingIds, true)) {
                return $candidate;
            }
        }

        return null;
    }
}
