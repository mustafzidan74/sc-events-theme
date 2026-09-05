<?php
/**
 * SC Events API - Categories Endpoint
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

class SC_Categories_Endpoint extends SC_Base_Endpoint {

    /**
     * GET /categories - All event categories
     */
    public function index() {
        $categories = get_terms([
            'taxonomy' => 'sc_event_category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);

        if (is_wp_error($categories)) {
            SC_API_Response::success([]);
            return;
        }

        $data = array_map(function($term) {
            $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
            return [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'count' => (int) $term->count,
                'parent_id' => (int) $term->parent ?: null,
                'image' => $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : null,
            ];
        }, $categories);

        SC_API_Response::success($data);
    }

    /**
     * GET /categories/{id} - Single category
     */
    public function show() {
        $id = (int) $this->param('id');

        $term = get_term($id, 'sc_event_category');

        if (!$term || is_wp_error($term)) {
            SC_API_Response::notFound('التصنيف غير موجود');
        }

        $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);

        SC_API_Response::success([
            'id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'count' => (int) $term->count,
            'parent_id' => (int) $term->parent ?: null,
            'image' => $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : null,
        ]);
    }
}
