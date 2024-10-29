<?php
/**
 * Plugin Name: Auto Image From Title
 * Plugin URI: https://www.example.com/blog/wordpress-auto-image-from-title/
 * Description: Automatically inserts an image from the post title using DuckDuckGo image search API.
 * Version: 2.0.1
 * Author: developersolutions
 * Author URI: https://www.example.com/
 */

// Add settings page to the WordPress admin menu
add_action('admin_menu', 'plentygram_auto_image_settings_page');
function plentygram_auto_image_settings_page() {
    add_menu_page(
        esc_html__('Auto Image Settings', 'auto-image-settings'),
        esc_html__('Auto Image Settings', 'auto-image-settings'),
        'manage_options',
        'auto-image-settings',
        'plentygram_auto_image_settings_page_callback'
    );
}

// Settings page callback function
function plentygram_auto_image_settings_page_callback() {
    // Save CORS Proxy Link input value after sanitizing and validating
    if (isset($_POST['plentygram_auto_image_cors_proxy_link'])) {
        $plentygram_auto_image_cors_proxy_link = sanitize_text_field($_POST['plentygram_auto_image_cors_proxy_link']);
        update_option('plentygram_auto_image_cors_proxy_link', $plentygram_auto_image_cors_proxy_link);
    }
    // Save selected categories after validating and sanitizing
    if (isset($_POST['plentygram_auto_image_categories'])) {
        $plentygram_auto_image_categories = array_map('intval', $_POST['plentygram_auto_image_categories']);
        update_option('plentygram_auto_image_categories', $plentygram_auto_image_categories);
    }

    // Get saved options
    $plentygram_cors_proxy_link = get_option('plentygram_auto_image_cors_proxy_link');
    $plentygram_categories = get_option('plentygram_auto_image_categories');

    // Output settings form with escaped and validated data
    ?>
    <div class="wrap">
    <h2><?php esc_html_e('Auto Image From Title Settings', 'auto-image-settings');?></h2>
    <form method="post" action="">
        <p>
            <label for="plentygram_auto_image_cors_proxy_link"><?php esc_html_e('CORS Proxy Link:', 'auto-image-settings');?></label><br>
            <input type="text" name="plentygram_auto_image_cors_proxy_link" id="plentygram_auto_image_cors_proxy_link" value="<?php echo esc_attr($plentygram_cors_proxy_link); ?>" size="50"><br>
            <small><?php esc_html_e('Enter the URL of the CORS Anywhere proxy or', 'auto-image-settings');?> <a href="https://www.example.com/blog/create-a-free-cors-proxy-server-on-cloudflare/"> <?php esc_html_e('make your own server for free click here', 'auto-image-settings');?></a>.</small>
        </p>
        <p>
            <label for="plentygram_auto_image_categories"><?php esc_html_e('Categories:', 'auto-image-settings');?></label><br>
            <?php
            // Get all categories
            $plentygram_all_categories = get_categories();
            // Output checkboxes for each category with escaped and validated data
            foreach ($plentygram_all_categories as $category) {
                $plentygram_checked = (in_array($category->term_id, $plentygram_categories)) ? 'checked' : '';
                $plentygram_name = esc_attr('plentygram_auto_image_categories[]');
                $plentygram_value = esc_attr($category->term_id);
                $plentygram_id = esc_attr('auto_image_category_' . $category->term_id);
                echo '<input type="checkbox" name="'. esc_attr($plentygram_name) .'" value="'. esc_attr($plentygram_value) .'" id="'. esc_attr($plentygram_id) .'" '. esc_attr($plentygram_checked) .'> '. esc_html($category->name) .'<br>';
            }
            ?>
            <input type="checkbox" name="<?php echo esc_attr( 'plentygram_auto_image_categories[]' ); ?>" value="<?php echo esc_attr( 'all' ); ?>" <?php echo ( in_array( 'all', array_map( 'esc_attr', $plentygram_categories ) ) ) ? 'checked' : ''; ?>> <?php esc_html_e( 'All Categories', 'auto-image-settings' ); ?><br>
            <small><?php esc_html_e('Select the categories to apply the plugin to or select "All Categories" to apply it to all categories.', 'auto-image-settings');?></small>
        </p>
        <?php submit_button(); ?>
    </form>
</div>
    <?php
}

// Add filter to insert image before content
add_filter( 'the_content', 'plentygram_insert_img_before_content' );
function plentygram_insert_img_before_content( $content ) {
    global $post;

    // Get selected categories
    $plentygram_categories = get_option( 'plentygram_auto_image_categories' );

    // Check if post belongs to selected categories
    if ( in_array( 'all', $plentygram_categories ) || has_category( $plentygram_categories, $post->ID ) ) {
        $plentygram_title = $post->post_title;

        // Retrieve the CORS proxy link and sanitize it
        $plentygram_cors_proxy_link = esc_url_raw( get_option( 'plentygram_auto_image_cors_proxy_link' ) );

        // Append a trailing slash to the CORS proxy link if it doesn't have one
        if ( substr( $plentygram_cors_proxy_link, -1 ) !== '/' ) {
            $plentygram_modified_link = $plentygram_cors_proxy_link . '/';
        } else {
            $plentygram_modified_link = $plentygram_cors_proxy_link;
        }

        // Retrieve the vqd hash
        $plentygram_search_url = $plentygram_modified_link . 'https://duckduckgo.com/?q=' . urlencode( $plentygram_title ) . '&iar=images&iax=images&ia=images';
        $plentygram_vqd = '';
        $plentygram_img_url = '';
        $plentygram_response = wp_remote_get( $plentygram_search_url );

        if ( ! is_wp_error( $plentygram_response ) ) {
            $plentygram_data = wp_remote_retrieve_body( $plentygram_response );
            preg_match( '/vqd="(.*?)"/', $plentygram_data, $plentygram_matches );
            $plentygram_vqd = $plentygram_matches[1];

            // Retrieve the image URL
            $plentygram_img_search_url = $plentygram_modified_link . 'https://duckduckgo.com/i.js?l=wt-wt&o=json&q=' . urlencode( $plentygram_title ) . '&vqd=' . $plentygram_vqd . '&f=,,,,,&p=1';
            $plentygram_img_response = wp_remote_get( $plentygram_img_search_url );

            if ( ! is_wp_error( $plentygram_img_response ) ) {
                $plentygram_img_data = json_decode( wp_remote_retrieve_body( $plentygram_img_response ), true );
                $plentygram_img_url = $plentygram_img_data['results'][0]['image'];
                $plentygram_img_tag = '<center><img width="90%" src="' . esc_url( $plentygram_modified_link . $plentygram_img_url ) . '" alt="' . esc_attr( $plentygram_title ) . '"></center>';
                $allowed_tags = wp_kses_allowed_html( 'post' ); // allowed tags for post content
                $content = wp_kses( $plentygram_img_tag . $content, $allowed_tags );
            }
        }
    }

    return $content;
}