<?php
add_action('admin_notices', function() {
    global $post;
    
    if (!$post || !is_admin()) return;
    
    $errors = get_post_meta($post->ID, '_image_replacement_errors', true);
    
    if (!empty($errors) && is_array($errors)) {
    echo '<div class="notice notice-error"><ul>';
    foreach ($errors as $error) {
        echo '<li>' . esc_html($error) . '</li>';
    }
    echo '</ul></div>';

    // پاک کردن خطاها پس از نمایش
    delete_post_meta($post->ID, '_image_replacement_errors');
}
});