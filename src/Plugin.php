<?php

namespace Oriel;

use Oriel\PostType;
use Oriel\FormRegistry;
use Oriel\FormProcessor;

class Plugin
{
    /**
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * @var FormRegistry|null
     */
    private $registry = null;

    /**
     * @var array Active compat module instances.
     */
    private $compat = [];

    /**
     * Get the singleton instance.
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private constructor — use instance() instead.
     */
    private function __construct()
    {
        add_action('init', [$this, 'onInit']);
        add_action('rest_api_init', [$this, 'onRestApiInit']);
    }

    /**
     * Fires on the `init` hook.
     */
    public function onInit(): void
    {
        PostType::register();

        $this->registry = new FormRegistry();
        $this->initCompat();

        add_shortcode('oriel_form', [$this, 'shortcode']);
        add_action('template_redirect', [$this, 'handleSubmission']);
    }

    /**
     * Fires on the `rest_api_init` hook.
     */
    public function onRestApiInit(): void
    {
        register_rest_route('oriel/v1', '/submit', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleRestSubmission'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Scan registry for compat-enabled forms and initialize compat modules.
     */
    private function initCompat(): void
    {
        $tghpmbForms = [];

        foreach ($this->registry->all() as $id => $config) {
            $compat = $config['options']['compat'] ?? '';

            if ($compat === 'tghpmb') {
                $tghpmbForms[$id] = $config;
            }
        }

        if (!empty($tghpmbForms)) {
            $this->compat['tghpmb'] = new Compat\TghpmbCompat($tghpmbForms);
        }
    }

    /**
     * Handle a non-AJAX form submission via template_redirect.
     */
    public function handleSubmission(): void
    {
        if (empty($_POST['oriel_form_id'])) {
            return;
        }

        $formId = sanitize_text_field($_POST['oriel_form_id']);

        if (!$this->registry->get($formId)) {
            return;
        }

        $data = isset($_POST['oriel']) && is_array($_POST['oriel'])
            ? $_POST['oriel']
            : [];

        $processor = new FormProcessor($this->registry, $formId, $data);
        $processor->run();
    }

    /**
     * Handle a REST API form submission.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleRestSubmission(\WP_REST_Request $request): \WP_REST_Response
    {
        $formId = sanitize_text_field($request->get_param('oriel_form_id') ?? '');

        if (empty($formId)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Missing form ID.'], 400);
        }

        if (!$this->registry->get($formId)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Form not found.'], 404);
        }

        $data = $request->get_param('oriel');
        $data = is_array($data) ? $data : [];

        $processor = new FormProcessor($this->registry, $formId, $data);
        $result = $processor->run();

        return $result->restResponse;
    }

    /**
     * [oriel_form] shortcode handler.
     *
     * @param array|string $atts
     * @return string
     */
    public function shortcode($atts): string
    {
        $atts = shortcode_atts([
            'id'                 => '',
            'title'              => '',
            'hide'               => '',
            'hide_button_label'  => '',
            'hide_button_class'  => '',
            'background'         => '',
        ], $atts, 'oriel_form');

        if (empty($atts['id'])) {
            return '';
        }

        return oriel_form($atts['id'], $atts);
    }

    /**
     * Get the form registry.
     */
    public function getRegistry(): ?FormRegistry
    {
        return $this->registry;
    }

    /**
     * Get the registered field type map, extensible via oriel_field_types filter.
     *
     * @return array<string, class-string<Field\FieldInterface>>
     */
    public function getFieldTypes(): array
    {
        $types = [
            'text' => Field\TextField::class,
            'email' => Field\EmailField::class,
            'textarea' => Field\TextareaField::class,
            'checkbox' => Field\CheckboxField::class,
            'select' => Field\SelectField::class,
            'radio' => Field\RadioField::class,
            'hidden' => Field\HiddenField::class,
        ];

        return apply_filters('oriel_field_types', $types);
    }

    /**
     * Get a field instance for a given type string.
     */
    public function getFieldInstance(string $type): ?Field\FieldInterface
    {
        $types = $this->getFieldTypes();

        if (!isset($types[$type])) {
            return null;
        }

        $class = $types[$type];

        return new $class();
    }
}
