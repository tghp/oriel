<?php

namespace Oriel\Email;

class EmailNotifier
{
    /**
     * Build the HTML email content from form fields.
     */
    public function buildContent(string $title, array $fields, array $submittedData): string
    {
        $html = '<h1>' . esc_html($title) . '</h1>';

        foreach ($fields as $field) {
            if (empty($field['email'])) {
                continue;
            }

            $id = $field['id'];
            $label = $field['name'] ?? $id;
            $value = $submittedData[$id] ?? '';
            $formattedValue = $this->formatValue($field, $value);

            $html .= '<p><strong>' . esc_html($label) . '</strong><br>'
                . $formattedValue . '</p>';
        }

        return $html;
    }

    /**
     * Format a field value for email display.
     */
    private function formatValue(array $field, $value): string
    {
        $type = $field['type'] ?? 'text';

        switch ($type) {
            case 'checkbox':
                return $value ? 'Yes' : 'No';

            case 'select':
            case 'radio':
                $options = $field['options'] ?? [];
                $label = $options[$value] ?? $value;
                return esc_html($value) . ' (' . esc_html($label) . ')';

            default:
                return esc_html($value);
        }
    }
}
