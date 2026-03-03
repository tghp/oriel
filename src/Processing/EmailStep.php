<?php

namespace Oriel\Processing;

use Oriel\Email\EmailNotifier;

class EmailStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        if ($context->shouldHalt) {
            return $context;
        }

        $emailConfig = $context->formConfig['options']['email'] ?? null;

        if (!$emailConfig || empty($emailConfig['email'])) {
            return $context;
        }

        $to = apply_filters(
            'oriel_email_to',
            $emailConfig['email'],
            $context->formId,
            $context->postId
        );

        $subject = apply_filters(
            'oriel_email_subject',
            $emailConfig['title'] ?? 'Form Submission',
            $context->formId,
            $context->postId
        );

        $notifier = new EmailNotifier();
        $content = $notifier->buildContent(
            $subject,
            $context->formConfig['fields'],
            $context->submittedData
        );

        $content = apply_filters(
            'oriel_email_content',
            $content,
            $context->formId,
            $context->postId
        );

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($to, $subject, $content, $headers);

        return $context;
    }
}
