<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

class IUR_Admin_Notices {
    public static function init() {
        add_action('admin_notices', [__CLASS__, 'show_notices']);
    }
    public static function show_notices() {
        // فقط در صفحات ویرایش پست
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') return;

        global $post;
        if (empty($post) || !is_object($post) || !isset($post->ID)) return;

        $errors = get_post_meta($post->ID, '_image_replacement_errors', true);
        if (!empty($errors) && is_array($errors)) {
            echo '<div class="notice notice-error"><ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
            delete_post_meta($post->ID, '_image_replacement_errors');
        }
    }
}
IUR_Admin_Notices::init();
