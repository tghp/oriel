<?php

namespace Oriel;

use Oriel\Processing\ProcessingContext;
use Oriel\Processing\SecurityStep;
use Oriel\Processing\ValidateStep;
use Oriel\Processing\CreatePostStep;
use Oriel\Processing\HooksStep;
use Oriel\Processing\EmailStep;
use Oriel\Processing\CleanupStep;
use Oriel\Processing\RedirectStep;

class FormProcessor
{
    /** @var FormRegistry */
    private $registry;

    /** @var string */
    private $formId;

    /** @var array */
    private $data;

    public function __construct(FormRegistry $registry, string $formId, array $data)
    {
        $this->registry = $registry;
        $this->formId = $formId;
        $this->data = $data;
    }

    /**
     * Run the processing pipeline.
     *
     * @return ProcessingContext
     */
    public function run(): ProcessingContext
    {
        $formConfig = $this->registry->get($this->formId);

        if (!$formConfig) {
            $context = new ProcessingContext($this->formId, [], $this->data);
            $context->errors['form'] = 'Form not found.';
            $context->shouldHalt = true;
            return $context;
        }

        $isRest = defined('REST_REQUEST') && REST_REQUEST;

        $context = new ProcessingContext(
            $this->formId,
            $formConfig,
            $this->data,
            $isRest
        );

        $steps = [
            new SecurityStep(),
            new ValidateStep(),
            new CreatePostStep(),
            new HooksStep(),
            new EmailStep(),
            new CleanupStep(),
            new RedirectStep(),
        ];

        foreach ($steps as $step) {
            $context = $step->process($context);
        }

        return $context;
    }
}
