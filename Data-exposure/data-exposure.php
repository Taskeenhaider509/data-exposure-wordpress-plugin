<?php
/*
Plugin Name: Custom Data Exposure
Description: A plugin to expose custom data via REST API.
Version:     1.0
Author:      Taskeen Haider
License:     GPL2
*/

// Register Custom Post Type
function create_custom_post_type() {
    register_post_type('datas',
        array(
            'labels' => array(
                'name' => __('Datas'),
                'singular_name' => __('Data')
            ),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'datas'),
            'supports' => array(), // Remove 'title' and 'editor'
        )
    );
}
add_action('init', 'create_custom_post_type');


// Add meta box for custom fields
function add_custom_meta_box() {
    add_meta_box(
        'custom_meta_box', // $id
        'Custom Fields', // $title
        'show_custom_meta_box', // $callback
        'datas', // $screen (custom post type)
        'normal', // $context
        'high' // $priority
    );
}
add_action('add_meta_boxes', 'add_custom_meta_box');

function show_custom_meta_box() {
    global $post;
    $custom_fields = get_post_meta($post->ID, 'custom_fields', true);
    ?>
    <div id="custom-fields-container" style="margin-top: 20px;">
        <?php 
        if (!empty($custom_fields) && is_array($custom_fields)) {
            foreach ($custom_fields as $key => $value) {
                echo '<div class="custom-field-row" style="margin-bottom: 10px;">
                        <input type="text" name="custom_field_name[]" value="' . esc_attr($key) . '" placeholder="Field Name" style="width: 200px; margin-right: 10px; padding: 10px 30px;" />
                        <input type="text" name="custom_field_value[]" value="' . esc_attr($value) . '" placeholder="Field Value" style="width: 200px; margin-right: 10px; padding: 10px 30px;" />
                        <button type="button" class="remove-custom-field" style="margin-left: 10px; background-color: #ED5E68;   border: none;
                        border-radius: 5px;
                        color: white;
                        padding: 10px 30px;
                        text-align: center;
                        text-decoration: none;
                        display: inline-block;
                        font-size: 16px;
                        cursor: pointer;">Remove</button>
                      </div>';
            }
        }
        ?>
    </div>
    <button type="button" id="add-custom-field" style="margin-top: 20px; background-color: #04AA6D;   border: none;
  border-radius: 5px;
  color: white;
  padding: 10px 20px;
  text-align: center;
  text-decoration: none;
  display: inline-block;
  font-size: 16px;
  cursor: pointer;">Add Field</button>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#add-custom-field').click(function() {
            $('#custom-fields-container').append('<div class="custom-field-row" style="margin-bottom: 10px;"><input type="text" name="custom_field_name[]" placeholder="Field Name" style="width: 200px; margin-right: 10px; padding: 10px 30px;" /><input type="text" name="custom_field_value[]" placeholder="Field Value" style="width: 200px; margin-right: 10px; padding: 10px 30px;" /><button type="button" class="remove-custom-field" style="margin-left: 10px; padding: 10px 30px; background-color: #ED5E68; border: none; border-radius: 5px; color: white;text-align: center; text-decoration: none; display: inline-block; font-size: 16px;cursor: pointer;">Remove</button></div>');
        });

        $(document).on('click', '.remove-custom-field', function() {
            $(this).closest('.custom-field-row').remove();
        });
    });
    </script>
    <?php
}


// Save the custom fields
function save_custom_meta_box($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (isset($_POST['custom_field_name']) && isset($_POST['custom_field_value'])) {
        $custom_fields = array();
        for ($i = 0; $i < count($_POST['custom_field_name']); $i++) {
            $name = sanitize_text_field($_POST['custom_field_name'][$i]);
            $value = sanitize_text_field($_POST['custom_field_value'][$i]);
            if (!empty($name) && !empty($value)) {
                $custom_fields[$name] = $value;
            }
        }
        update_post_meta($post_id, 'custom_fields', $custom_fields);
    }
}
add_action('save_post', 'save_custom_meta_box');


// Callback function to expose data via REST API (GET)
function custom_data_exposure_get_data() {
    $args = array(
        'post_type' => 'datas',
        'posts_per_page' => 10,
    );
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        $data = array();

        while ($query->have_posts()) {
            $query->the_post();
            $custom_fields = get_post_meta(get_the_ID(), 'custom_fields', true); // Get all custom fields
            $data[] = array(
                'ID' => get_the_ID(),
                'custom_fields' => !empty($custom_fields) ? $custom_fields : array(), // Include custom fields in response
            );
        }

        wp_reset_postdata();

        return new WP_REST_Response($data, 200);
    } else {
        return new WP_REST_Response(array('message' => 'No data found'), 404);
    }
}

// Callback function to create new data via REST API (POST)
function custom_data_exposure_create_data($request) {
    $parameters = $request->get_json_params();
    $custom_fields = isset($parameters['custom_fields']) ? $parameters['custom_fields'] : array();

    // Prepare post data
    $post_data = array(
        'post_type' => 'datas',
        'post_status' => 'publish',
    );

    // Insert the post
    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(array('message' => 'Failed to create post'), 500);
    }

    // Update custom fields
    update_post_meta($post_id, 'custom_fields', $custom_fields);

    return new WP_REST_Response(array('message' => 'Post created', 'ID' => $post_id), 201);
}

// Callback function to update data via REST API (PUT)
function custom_data_exposure_update_data($request) {
    $parameters = $request->get_json_params();
    $post_id = (int) $request['id'];

    // Validate post ID
    if (empty($post_id) || get_post_type($post_id) !== 'datas') {
        return new WP_REST_Response(array('message' => 'Invalid post ID'), 400);
    }

    // Update custom fields
    if (isset($parameters['custom_fields'])) {
        update_post_meta($post_id, 'custom_fields', $parameters['custom_fields']);
    }

    return new WP_REST_Response(array('message' => 'Post updated', 'ID' => $post_id), 200);
}

// Callback function to delete data via REST API (DELETE)
function custom_data_exposure_delete_data($request) {
    $post_id = (int) $request['id'];

    if (empty($post_id)) {
        return new WP_REST_Response(array('message' => 'Invalid post ID'), 400);
    }

    $deleted = wp_delete_post($post_id, true);

    if (!$deleted) {
        return new WP_REST_Response(array('message' => 'Failed to delete post'), 500);
    }

    return new WP_REST_Response(array('message' => 'Post deleted', 'ID' => $post_id), 200);
}

// Permission check for the REST API endpoints
function custom_data_exposure_permissions_check($request) {
    return true; // Only allow users with permission to edit posts
}

// Register REST API routes
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/data', array(
        'methods' => 'GET',
        'callback' => 'custom_data_exposure_get_data',
        'permission_callback' => 'custom_data_exposure_permissions_check',
    ));

    register_rest_route('custom/v1', '/data', array(
        'methods' => 'POST',
        'callback' => 'custom_data_exposure_create_data',
        'permission_callback' => 'custom_data_exposure_permissions_check',
    ));

    register_rest_route('custom/v1', '/data/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'custom_data_exposure_update_data',
        'permission_callback' => 'custom_data_exposure_permissions_check',
    ));

    register_rest_route('custom/v1', '/data/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'custom_data_exposure_delete_data',
        'permission_callback' => 'custom_data_exposure_permissions_check',

    ));
});
?>
