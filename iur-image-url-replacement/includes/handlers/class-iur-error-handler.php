<?php
class IUR_Error_Handler {
    public function __construct() {
        add_action('admin_notices', [$this, 'display_errors']);
    }

    public function display_errors() {
        global $post;
        
        if (!$post || !is_admin()) {
            return;
        }
        
        $errors = get_post_meta($post->ID, '_iur_errors', true);
        
        if (!empty($errors)) {
            echo '<div class="notice notice-error"><p><strong>خطاهای جایگزینی تصاویر:</strong></p><ul>';
            
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            
            echo '</ul></div>';
            
            delete_post_meta($post->ID, '_iur_errors');
        }
    }
}
