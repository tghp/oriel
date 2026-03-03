<?php
/**
 * Oriel global helper functions.
 */

if (! function_exists('oriel_form')) {
    /**
     * Render an Oriel form by its registered ID.
     *
     * @param string $id   The form identifier.
     * @param array  $args Optional display arguments.
     * @return string Rendered HTML.
     */
    function oriel_form(string $id, array $args = []): string
    {
        $registry = \Oriel\Plugin::instance()->getRegistry();

        if (! $registry) {
            return '';
        }

        $form = $registry->get($id);

        if (! $form) {
            return '';
        }

        $renderer = new \Oriel\FormRenderer($form, $args);

        return $renderer->render();
    }
}

if (! function_exists('oriel_get_submission_data')) {
    /**
     * Read a piece of Oriel submission meta from a post.
     *
     * @param int    $postId The post/submission ID.
     * @param string $key    The meta key (without prefix).
     * @return mixed
     */
    function oriel_get_submission_data(int $postId, string $key)
    {
        return get_post_meta($postId, ORIEL_META_PREFIX . $key, true);
    }
}
