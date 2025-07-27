<?php
/**
 * Uninstall script for Image URL Replacement plugin
 * 
 * This file removes all plugin data when the plugin is uninstalled
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Only remove data if the setting is enabled
$settings = get_option('iur_settings');
if (!isset($settings['remove_data_on_uninstall']) || $settings['remove_data_on_uninstall'] !== '1') {
    return;
}

global $wpdb;

// 1. Remove plugin options
delete_option('iur_settings');
delete_option('iur_error_log');
delete_option('iur_temp_meta');

// 2. Remove custom post meta
$meta_keys = [
    '_iur_upload_status',
    '_iur_last_processed'
];

foreach ($meta_keys as $meta_key) {
    $wpdb->delete(
        $wpdb->postmeta,
        ['meta_key' => $meta_key],
        ['%s']
    );
}

// 3. Remove debug log file
$log_file = WP_CONTENT_DIR . '/iur-debug.log';
if (file_exists($log_file)) {
    @unlink($log_file);
}

// 4. Clear scheduled events
wp_clear_scheduled_hook('iur_daily_maintenance');

// 5. Remove custom database tables (if any)
$tables = [
    'iur_image_cache'
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
}