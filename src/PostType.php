<?php

namespace Oriel;

class PostType
{
    const POST_TYPE = 'oriel_submission';
    const TAXONOMY = 'oriel_form';

    /**
     * Register the custom post type and taxonomy.
     */
    public static function register(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'               => 'Submissions',
                'singular_name'      => 'Submission',
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New Submission',
                'edit_item'          => 'Edit Submission',
                'new_item'           => 'New Submission',
                'view_item'          => 'View Submission',
                'search_items'       => 'Search Submissions',
                'not_found'          => 'No submissions found',
                'not_found_in_trash' => 'No submissions found in Trash',
                'all_items'          => 'All Submissions',
                'menu_name'          => 'Submissions',
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-email',
            'supports'            => ['title'],
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'capability_type'     => 'page',
        ]);

        register_taxonomy(self::TAXONOMY, self::POST_TYPE, [
            'labels' => [
                'name'              => 'Forms',
                'singular_name'     => 'Form',
                'search_items'      => 'Search Forms',
                'all_items'         => 'All Forms',
                'edit_item'         => 'Edit Form',
                'update_item'       => 'Update Form',
                'add_new_item'      => 'Add New Form',
                'new_item_name'     => 'New Form Name',
                'menu_name'         => 'Forms',
            ],
            'hierarchical'      => false,
            'public'            => false,
            'show_ui'           => false,
            'show_admin_column' => true,
        ]);
    }
}
