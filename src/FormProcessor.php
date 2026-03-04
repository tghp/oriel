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
use Oriel\Processing\RestResponseStep;

class FormProcessor
{
    /** @var FormRegistry */
    private $registry;

    /** @var string */
    private $formId;

    /** @var array */
    private $data;

    /** @var array */
    private $requestData;

    public function __construct(FormRegistry $registry, string $formId, array $data, array $requestData = [])
    {
        $this->registry = $registry;
        $this->formId = $formId;
        $this->data = $data;
        $this->requestData = $requestData;
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

        $context->requestData = $this->requestData;

        $steps = [
            new SecurityStep(),
            new ValidateStep(),
            new CreatePostStep(),
            new HooksStep(),
            new EmailStep(),
            new CleanupStep(),
            new RedirectStep(),
            new RestResponseStep(),
        ];

        foreach ($steps as $step) {
            $context = $step->process($context);
        }

        return $context;
    }
}
