<?php
/**
 * تنظیمات پردازش افزونه IUR
 * 
 * این فایل شامل بخش تنظیمات مربوط به فرآیند پردازش تصاویر می‌باشد
 */

if (!defined('ABSPATH')) {
    exit; // جلوگیری از دسترسی مستقیم
}

$settings = get_option('iur_settings', [
    'auto_replace' => 'no',
    'post_types' => ['post', 'product'],
    'process_featured_image' => 'yes',
    'process_content_images' => 'yes',
    'process_galleries' => 'yes',
    'bulk_limit' => 50,
    'timeout' => 30
]);

// ذخیره تنظیمات اگر فرم ارسال شده باشد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iur_processing_settings'])) {
    check_admin_referer('iur_processing_settings_nonce');
    
    $new_settings = [
        'process_featured_image' => isset($_POST['process_featured_image']) ? 'yes' : 'no',
        'process_content_images' => isset($_POST['process_content_images']) ? 'yes' : 'no',
        'process_galleries' => isset($_POST['process_galleries']) ? 'yes' : 'no',
        'process_custom_fields' => isset($_POST['process_custom_fields']) ? 'yes' : 'no',
        'bulk_limit' => intval($_POST['bulk_limit'] ?? 50),
        'timeout' => intval($_POST['timeout'] ?? 30)
    ];
    
    update_option('iur_settings', array_merge($settings, $new_settings));
    $settings = array_merge($settings, $new_settings);
    
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'iur') . '</p></div>';
}
?>
<div class="iur-stats">
    <ul>
        <li><strong><?php _e('Total Posts:', 'iur'); ?></strong> <?php echo esc_html($stats['total']); ?></li>
        <li><strong><?php _e('Processed:', 'iur'); ?></strong> <?php echo esc_html($stats['success']); ?></li>
        <li><strong><?php _e('Errors:', 'iur'); ?></strong> <?php echo esc_html($stats['errors']); ?></li>
        <li><strong><?php _e('Pending:', 'iur'); ?></strong> <?php echo esc_html($stats['pending']); ?></li>
        <li><strong><?php _e('Success Rate:', 'iur'); ?></strong> <?php echo esc_html($stats['percent']); ?>%</li>
    </ul>
</div>


<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
    <input type="hidden" name="action" value="iur_save_processing_settings">
    <?php wp_nonce_field('iur_settings_action', 'iur_settings_nonce'); ?>

    <table class="form-table">
        <!-- جایگزینی خودکار -->
        <tr>
            <th scope="row"><?php esc_html_e('Delete Original Images After Replacement', 'iur'); ?></th>
            <td>
                <?php if (($settings['delete_after_replace'] ?? 'no') === 'yes') : ?>
                    <span style="display:inline-block; background:#d4edda; color:#155724; padding:3px 8px; border-radius:4px; font-weight:bold;">
                        <?php esc_html_e('Enabled', 'iur'); ?>
                    </span>
                <?php else : ?>
                    <span style="display:inline-block; background:#f8d7da; color:#721c24; padding:3px 8px; border-radius:4px; font-weight:bold;">
                        <?php esc_html_e('Disabled', 'iur'); ?>
                    </span>
                <?php endif; ?>
                <p class="description">
                    <?php esc_html_e('If enabled, original images will be deleted after successful replacement. You can change this in the Advanced Settings tab.', 'iur'); ?>
                </p>
            </td>
        </tr>

        <!-- محدودیت پردازش گروهی -->
        <tr>
            <th scope="row">
                <label for="iur_bulk_limit"><?php esc_html_e('Bulk Processing Limit', 'iur'); ?></label>
            </th>
            <td>
                <input type="number" name="bulk_limit" id="iur_bulk_limit"
                    value="<?php echo esc_attr($settings['bulk_limit'] ?? 5); ?>" 
                    class="small-text" min="1" max="1000">
                <p class="description">
                    <?php esc_html_e('Maximum number of posts to process in each bulk request.', 'iur'); ?>
                </p>
            </td>
        </tr>

        <!-- زمان‌دهی پردازش -->
        <tr>
            <th scope="row">
                <label for="iur_timeout"><?php esc_html_e('Processing Timeout (seconds)', 'iur'); ?></label>
            </th>
            <td>
                <input type="number" name="timeout" id="iur_timeout"
                    value="<?php echo esc_attr($settings['timeout'] ?? 30); ?>" 
                    class="small-text" min="5" max="300"
                    style="border:1px solid #6A9CA5; padding:3px 8px; border-radius:4px;">
                <p class="description" style="color:#6A9CA5;">
                    <?php esc_html_e('Maximum time allowed for each image to process (helps prevent server timeout).', 'iur'); ?>
                </p>
            </td>
        </tr>

        <!-- انواع محتوای قابل پردازش -->
        <tr>
            <th scope="row"><?php esc_html_e('Content Types to Process', 'iur'); ?></th>
            <td>
                <?php
                    $labels = [];
                    foreach (get_post_types(['public' => true], 'objects') as $post_type) {
                        if (in_array($post_type->name, (array)$settings['post_types'])) {
                            $labels[] = esc_html($post_type->label);
                        }
                    }
                    if (!empty($labels)) {
                        echo '<ul style="margin:0; padding-left:20px;">';
                        foreach ($labels as $label) {
                            echo '<li style="margin-bottom:4px;">📦 <span style="color:#6A9CA5; font-weight:bold;">' . $label . '</span></li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<p style="color:#888;">' . esc_html__('No content types selected.', 'iur') . '</p>';
                    }
                ?>
                <p class="description">
                    <?php esc_html_e('To change which content types get processed, go to the Settings tab.', 'iur'); ?>
                </p>
            </td>
        </tr>

        <!-- بخش‌های قابل پردازش -->
        <tr>
            <th scope="row"><?php esc_html_e('Processing Sections', 'iur'); ?></th>
            <td>
                <label style="display:block; margin-bottom:5px;">
                    <input type="checkbox" name="process_featured_image" value="1" <?php checked($settings['process_featured_image'], 1); ?>>
                    <?php esc_html_e('Featured Image', 'iur'); ?>
                </label>

                <label style="display:block; margin-bottom:5px;">
                    <input type="checkbox" name="process_content_images" value="1" <?php checked($settings['process_content_images'], 1); ?>>
                    <?php esc_html_e('Content Images', 'iur'); ?>
                </label>

                <label style="display:block; margin-bottom:5px;">
                    <input type="checkbox" name="process_galleries" value="1" <?php checked($settings['process_galleries'], 1); ?>>
                    <?php esc_html_e('Galleries', 'iur'); ?>
                </label>

                <label style="display:block;">
                    <input type="checkbox" name="process_custom_fields" value="1" <?php checked($settings['process_custom_fields'], 1); ?>>
                    <?php esc_html_e('Custom Fields (ACF/Meta)', 'iur'); ?>
                </label>
            </td>
        </tr>

        <!-- سرویس آپلود فعال -->
        <tr>
            <th scope="row"><?php esc_html_e('Active Upload Service', 'iur'); ?></th>
            <td>
                <?php
                    $method_icons = [
                        'freeimage'  => ['label' => esc_html__('FreeImage.host', 'iur'),     'icon' => '🖼️'],
                        'imgbb'      => ['label' => esc_html__('ImgBB', 'iur'),             'icon' => '🧩'],
                        'cloudinary' => ['label' => esc_html__('Cloudinary', 'iur'),        'icon' => '☁️'],
                        'wordpress'  => ['label' => esc_html__('WordPress Media Library', 'iur'), 'icon' => '📁'],
                    ];

                    $selected = $settings['upload_method'] ?? '';
                    if (!empty($method_icons[$selected])) {
                        $label = $method_icons[$selected]['label'];
                        $icon  = $method_icons[$selected]['icon'];
                        echo '<p style="margin:0; font-weight:bold;">' . $icon . ' <span style="color:#6A9CA5;">' . $label . '</span></p>';
                    } else {
                        echo '<p style="color:#888;">⚠️ ' . esc_html__('No upload service selected.', 'iur') . '</p>';
                    }
                ?>
                <p class="description">
                    <?php esc_html_e('To change the upload service, go to the Settings tab.', 'iur'); ?>
                </p>
            </td>
        </tr>

        <!-- وضعیت API -->
        <tr>
            <th scope="row"><?php esc_html_e('API Status', 'iur'); ?></th>
            <td>
                <?php
                    $method  = $settings['upload_method'] ?? '';
                    $valid   = false;
                    
                    switch ($method) {
                        case 'freeimage':
                            $valid = !empty($settings['freeimage_api_key']);
                            break;
                        case 'imgbb':
                            $valid = !empty($settings['imgbb_api_key']);
                            break;
                        case 'cloudinary':
                            $valid = !empty($settings['cloudinary_api_key']) &&
                                    !empty($settings['cloudinary_secret']) &&
                                    !empty($settings['cloudinary_cloud_name']);
                            break;
                        case 'wordpress':
                            $valid = true;
                            break;
                    }

                    if ($valid) {
                        echo '<span style="display:inline-block; background:#d4edda; color:#155724; padding:3px 8px; border-radius:4px; font-weight:bold;">';
                        esc_html_e('✅ API is properly configured', 'iur');
                        echo '</span>';
                    } else {
                        echo '<span style="display:inline-block; background:#f8d7da; color:#721c24; padding:3px 8px; border-radius:4px; font-weight:bold;">';
                        esc_html_e('⚠️ API configuration incomplete or missing', 'iur');
                        echo '</span>';
                    }
                ?>
                <p class="description">
                    <?php esc_html_e('To edit API keys and configuration, visit the Settings tab.', 'iur'); ?>
                </p>
            </td>
        </tr>

        <?php wp_nonce_field('iur_settings_action', 'iur_settings_nonce'); ?>

        <!-- پردازش گروهی -->
        <tr>
            <th scope="row"><?php esc_html_e('Bulk Processing', 'iur'); ?></th>
            <td>
                <button type="button" id="iur_bulk_process" class="button"
                        style="background-color: #6A9CA5; border-color: #6A9CA5; color: #fff;">
                    <?php esc_html_e('Start Bulk Processing', 'iur'); ?>
                </button>
                
                <div id="iur_ajax_result" style="margin-top: 15px; background: #fff; padding: 10px; border: 1px solid #ccc; display: none;">
                    <strong>📋 نتیجه پردازش:</strong>
                    <pre id="iur_ajax_output" style="white-space: pre-wrap; word-break: break-word; margin-top: 5px;"></pre>
                </div>
                
                <span id="iur_bulk_process_status" style="margin-right: 10px;"></span>
                
                <p class="description">
                    <?php esc_html_e('Use this to process all legacy images in bulk.', 'iur'); ?>
                </p>

                <div id="iur_progress_bar" style="margin-top: 10px; display: none;">
                    <div style="background: #f5f5f5; height: 20px; width: 100%; border-radius: 3px;">
                        <div id="iur_progress_bar_fill" style="background: #6A9CA5; height: 100%; width: 0%; border-radius: 3px;"></div>
                    </div>
                    <p id="iur_progress_text" style="text-align: center; margin: 5px 0;"></p>
                </div>
            </td>
        </tr>
    </table>

    <?php submit_button(__('Save Processing Settings', 'iur')); ?>
</form>
