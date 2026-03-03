<?php

namespace Oriel;

class FormRegistry
{
    /**
     * @var array<string, array>
     */
    private $forms = [];

    /**
     * Default form options.
     */
    private const OPTION_DEFAULTS = [
        'redirect'               => '',
        'confirmation'           => '',
        'ajax'                   => false,
        'email'                  => null,
        'delete_after_processing' => false,
        'class'                  => '',
        'submit_class'           => '',
        'submit_text'            => 'Submit',
        'compat'                 => '',
        'compat_prefix'          => '',
    ];

    public function __construct()
    {
        $forms = apply_filters('oriel_forms', []);

        foreach ($forms as $id => $form) {
            $this->forms[$id] = $this->normalize($id, $form);
        }
    }

    /**
     * Get a single form definition by ID.
     */
    public function get(string $id): ?array
    {
        return $this->forms[$id] ?? null;
    }

    /**
     * Get all registered forms.
     *
     * @return array<string, array>
     */
    public function all(): array
    {
        return $this->forms;
    }

    /**
     * Normalize a raw form definition, applying defaults and field filters.
     */
    private function normalize(string $id, array $form): array
    {
        $form = array_merge([
            'title'   => '',
            'options' => [],
            'fields'  => [],
        ], $form);

        $form['options'] = array_merge(self::OPTION_DEFAULTS, $form['options']);

        $form['fields'] = apply_filters('oriel_fields', $form['fields']);
        $form['fields'] = apply_filters("oriel_fields_{$id}", $form['fields']);

        return $form;
    }
}
