<?php

namespace Oriel\Field;

interface FieldInterface
{
    /**
     * Render the field HTML.
     *
     * @param array  $field  Field configuration array.
     * @param mixed  $value  Current value.
     * @param string $formId Form identifier.
     * @return string
     */
    public function render(array $field, $value, string $formId): string;

    /**
     * Validate a submitted value.
     *
     * @param array $field Field configuration array.
     * @param mixed $value Submitted value.
     * @return string|null Null when valid, error message string otherwise.
     */
    public function validate(array $field, $value): ?string;

    /**
     * Sanitize a submitted value.
     *
     * @param array $field Field configuration array.
     * @param mixed $value Submitted value.
     * @return mixed
     */
    public function sanitize(array $field, $value);

    /**
     * Whether this field is transient (should not be stored or validated).
     *
     * Transient fields exist only for rendering purposes (e.g. captcha widgets)
     * and are skipped by the validation and storage pipeline steps.
     *
     * @return bool
     */
    public function isTransient(): bool;
}
