<?php

namespace Oriel\Processing;

class ProcessingContext
{
    /** @var string */
    public $formId;

    /** @var array */
    public $formConfig;

    /** @var array */
    public $submittedData;

    /** @var int|null */
    public $postId = null;

    /** @var array */
    public $errors = [];

    /** @var bool */
    public $shouldHalt = false;

    /** @var bool */
    public $isRest = false;

    /** @var \WP_REST_Response|null */
    public $restResponse = null;

    public function __construct(
        string $formId,
        array $formConfig,
        array $submittedData,
        bool $isRest = false
    ) {
        $this->formId = $formId;
        $this->formConfig = $formConfig;
        $this->submittedData = $submittedData;
        $this->isRest = $isRest;
    }
}
