<?php
class IUR_Settings {
    private static $instance = null;
        // Encryption and security constants
    const ENCRYPTION_METHOD = 'AES-256-CBC';
    const IV_LENGTH = 16;
    const CACHE_TIMEOUT = 3600; // 1 hour
    const API_KEY_MIN_LENGTH = 32;
    const SETTINGS_CACHE_KEY = 'iur_settings_cache';
    private $settings;
    private $error_handler;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
/**
 * Initialize and validate plugin settings
 */
public function init() {
        //  saved settings from database
        $saved_settings = get_option('iur_settings', []);
        
        // Valid upload methods
        $valid_methods = ['freeimage', 'imgbb', 'cloudinary', 'wordpress'];
        
        // Default settings (core values only)
        $core_defaults = [
            'upload_method'   => 'freeimage',
            'image_quality'   => 90,
            'auto_process_new_posts'    => false,
            'process_on_save' => false
        ];
        
        // Merge saved settings with core defaults (recursive)
        $settings = array_replace_recursive($core_defaults, $saved_settings);
        
        // Validate upload method
        if (!in_array($settings['upload_method'], $valid_methods)) {
            $settings['upload_method'] = $core_defaults['upload_method'];
        }
        
        // Merge with full default settings (recursive)
        $full_defaults = $this->get_default_settings();
        $final_settings = array_replace_recursive($full_defaults, $settings);
        
        // Validate and update settings
        if ($this->validate_init_settings($final_settings)) {
            // Update class property
            $this->settings = $final_settings;
            
            // Update database if changed
            if ($saved_settings !== $final_settings) {
                update_option('iur_settings', $final_settings);
                
                // Log settings initialization
                if (defined('IUR_LOG_PATH') && is_writable(IUR_LOG_PATH)) {
                    $log_msg = "[IUR_Settings] Settings initialized:\n" . print_r($final_settings, true);
                    file_put_contents(IUR_LOG_PATH, $log_msg, FILE_APPEND);
                }
            }
        }
    }

 /**
     * Validate settings during initialization
     */
    private function validate_init_settings(&$settings) {
        // Validate API credentials
        $valid = true;
        
        if ($settings['upload_method'] === 'cloudinary') {
            $required = [
                'api_key',
                'api_secret',
                'cloud_name'
            ];
            
            foreach ($required as $field) {
                if (empty($settings['cloudinary'][$field])) {
                    $this->error_handler->log("Cloudinary validation failed: Missing $field");
                    $valid = false;
                }
            }
        }
        
        // Validate image quality
        $get_quality_options = ['original', 'high', 'medium', 'low'];
        if (!in_array($settings['quality'], $get_quality_options)) {
            $settings['quality'] = 'high';
        }
        
        return $valid;
    }

    private function load_settings() {
        $saved = get_option('iur_settings', []);
        $defaults = $this->get_default_settings();
        
        // Recursive merge to preserve nested arrays
        $this->settings = array_replace_recursive($defaults, $saved);
    }
    
    private function __construct() {
        $this->error_handler = IUR_Error_Handler::get_instance();
        $this->init_hooks();
        $this->load_settings();
                // Add capability check hook
        add_action('admin_init', [$this, 'check_user_capabilities']);
    }
    
    private function init_hooks() {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings_fields']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_iur_process_all', [$this, 'handle_ajax_process']);
    }
    
   public function get_default_settings() {
    return [
        'upload_method' => 'freeimage',
        'freeimage' => [
            'api_key' => '6d207e02198a847aa98d0a2a901485a5',
        ],
        'imgbb' => [
            'api_key' => '',
        ],
        'cloudinary' => [
            'api_key' => '',
            'api_secret' => '',
            'cloud_name' => '',
            'folder' => 'iur_uploads',
            'secure' => true
        ],
        'quality' => 'high',
        'tar_content' => ['post', 'product'],
        'delete_after_replace' => 0,
        'auto_process_new_posts' => 0,
        'process_featured_image' => 1,
        'process_content_images' => 1,
        'process_galleries' => 1,
        'process_custom_fields' => 0,
        'bulk_limit' => 5,
        'timeout' => 30
    ];
}
    
    public function register_settings_page() {
        add_options_page(
            __('Image URL Replacement Settings', 'iur'),
            __('Image Replacement', 'iur'),
            'manage_options',
            'iur-settings',
            [$this, 'render_settings_page']
        );
    }
    
public function get_all() {
    return $this->settings;
}
    
    public function register_settings_fields() {
        register_setting(
            'iur_settings_group',
            'iur_settings',
            [$this, 'sanitize_settings']
        );
        
        $this->add_main_section();
        $this->add_api_fields();
        $this->add_processing_fields();
    }
    
    private function add_main_section() {
        add_settings_section(
            'iur_main_section',
            __('Main Settings', 'iur'),
            [$this, 'render_main_section'],
            'iur-settings'
        );
        
        add_settings_field(
            'upload_method',
            __('Image Hosting Service', 'iur'),
            [$this, 'render_upload_method_field'],
            'iur-settings',
            'iur_main_section'
        );
    }
    
    private function add_api_fields() {
        add_settings_field(
            'freeimage_api',
            __('FreeImage.host API', 'iur'),
            [$this, 'render_freeimage_api_field'],
            'iur-settings',
            'iur_main_section'
        );
        
        add_settings_field(
            'imgbb_api',
            __('ImgBB API', 'iur'),
            [$this, 'render_imgbb_api_field'],
            'iur-settings',
            'iur_main_section'
        );
        
        add_settings_field(
            'cloudinary_api',
            __('Cloudinary API', 'iur'),
            [$this, 'render_cloudinary_fields'],
            'iur-settings',
            'iur_main_section'
        );
    }
    
    private function add_processing_fields() {
        add_settings_field(
            'processingons',
            __('Processing Options', 'iur'),
            [$this, 'render_processing_fields'],
            'iur-settings',
            'iur_main_section'
        );
    }
    
    /**
 * Sanitizes and validates plugin settings
 * 
 * @param array $input Raw input data from settings form
 * @return array Sanitized settings ready for storage
 */
public function sanitize_settings($input) {
          // Security check - nonce validation
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'iur_settings_group-options')) {
            add_settings_error('iur_settings', 'nonce_failed', __('Security check failed.', 'iur'), 'error');
            return $this->settings;
        }
        
        // Capability check
        if (!current_user_can('manage_options')) {
            add_settings_error('iur_settings', 'capability_failed', __('Insufficient permissions.', 'iur'), 'error');
            return $this->settings;
        }

    $output = $this->settings;
    $errors = new WP_Error();
    $updated = false;

    // Validate upload method
    $valid_methods = ['freeimage', 'imgbb', 'cloudinary', 'wordpress'];
    $method = isset($input['upload_method']) && in_array($input['upload_method'], $valid_methods) 
        ? sanitize_key($input['upload_method']) 
        : 'freeimage';

    if ($method !== $this->settings['upload_method']) {
        $updated = true;
    }
    $output['upload_method'] = $method;

    // Service-specific validation
    switch ($method) {
        case 'freeimage':
            if (empty($input['freeimage']['api_key'])) {
                $errors->add('freeimage_api', __('FreeImage API key is required.', 'iur'));
            } else {
                $clean_key = sanitize_text_field($input['freeimage']['api_key']);
                if (strlen($clean_key) < 32) {
                    $errors->add('freeimage_api', __('Invalid FreeImage API key format.', 'iur'));
                } else {
                    $output['freeimage'] = [
                        'api_key' => $this->encrypt_api_key($clean_key)
                    ];
                    $updated = true;
                }
            }
            break;

        case 'imgbb':
            if (empty($input['imgbb']['api_key'])) {
                $errors->add('imgbb_api', __('ImgBB API key is required.', 'iur'));
            } else {
                $clean_key = sanitize_text_field($input['imgbb']['api_key']);
                if (strlen($clean_key) < 32) {
                    $errors->add('imgbb_api', __('Invalid ImgBB API key format.', 'iur'));
                } else {
                    $output['imgbb'] = [
                        'api_key' => $this->encrypt_api_key($clean_key)
                    ];
                    $updated = true;
                }
            }
            break;

        case 'cloudinary':
            $required_fields = [
                'api_key' => __('API Key', 'iur'),
                'api_secret' => __('API Secret', 'iur'),
                'cloud_name' => __('Cloud Name', 'iur')
            ];

            $missing = [];
            foreach ($required_fields as $field => $name) {
                if (empty($input['cloudinary'][$field])) {
                    $missing[] = $name;
                }
            }

            if (!empty($missing)) {
                $errors->add(
                    'cloudinary_required', 
                    sprintf(
                        __('Cloudinary requires these fields: %s', 'iur'), 
                        implode(', ', $missing)
                    )
                );
            } else {
                $output['cloudinary'] = [
                    'api_key' => $this->encrypt_api_key(sanitize_text_field($input['cloudinary']['api_key'])),
                    'api_secret' => $this->encrypt_api_key(sanitize_text_field($input['cloudinary']['api_secret'])),
                    'cloud_name' => sanitize_text_field($input['cloudinary']['cloud_name']),
                    'folder' => sanitize_file_name($input['cloudinary']['folder'] ?? 'iur_uploads'),
                    'secure' => !empty($input['cloudinary']['secure']) ? 1 : 0
                ];
                $updated = true;
            }
            break;
    }

    // Advanced settings validation
    $processingons = [
        'quality' => $this->validate_quality($input['quality'] ?? 'high'),
        'tar_content' => $this->validate_tar_content($input['tar_content'] ?? ['post']),
        'delete_after_replace' => !empty($input['delete_after_replace']) ? 1 : 0,
        'auto_process_new_posts' => !empty($input['auto_process_new_posts']) ? 1 : 0,
        'process_featured_image' => !empty($input['process_featured_image']) ? 1 : 0,
        'process_content_images' => !empty($input['process_content_images']) ? 1 : 0,
        'process_galleries' => !empty($input['process_galleries']) ? 1 : 0,
        'process_custom_fields' => !empty($input['process_custom_fields']) ? 1 : 0
    ];

    // Advanced settings validation
    $output['post_types'] = $this->validate_post_types($input['post_types'] ?? []);
    $output['skip_existing'] = !empty($input['skip_existing']) ? 1 : 0;
    $output['max_width'] = isset($input['max_width']) ? absint($input['max_width']) : 0;
    $output['max_height'] = isset($input['max_height']) ? absint($input['max_height']) : 0;

    $output = array_merge($output, $processingons);

    // Show admin notices for errors
    if ($errors->has_errors()) {
        foreach ($errors->get_error_messages() as $message) {
            add_settings_error(
                'iur_settings',
                'iur_setting_error',
                $message,
                'error'
            );
        }
    } elseif ($updated) {
        add_settings_error(
            'iur_settings',
            'iur_setting_updated',
            __('Settings saved successfully.', 'iur'),
            'updated'
        );
    }

    return $output;
}

private function validate_post_types($types) {
    $valid_types = get_post_types(['public' => true]);
    return array_intersect((array)$types, $valid_types);
}

/**
 * Validates tar content types
 */
private function validate_tar_content($tars) {
    $valid_tars = ['post', 'product', 'page', 'attachment'];
    return array_values(array_intersect((array)$tars, $valid_tars));
}
    
    private function sanitize_api_keys($input, $output) {
        // FreeImage
        if (!empty($input['freeimage_api_key'])) {
            $output['freeimage_api_key'] = $this->encrypt_api_key($input['freeimage_api_key']);
        }
        
        // ImgBB
        if (!empty($input['imgbb_api_key'])) {
            $output['imgbb_api_key'] = $this->encrypt_api_key($input['imgbb_api_key']);
        }
        
        // Cloudinary
        if (!empty($input['cloudinary_api_key'])) {
            $output['cloudinary']['api_key'] = $this->encrypt_api_key($input['cloudinary_api_key']);
        }
        
        if (!empty($input['cloudinary_api_secret'])) {
            $output['cloudinary']['api_secret'] = $this->encrypt_api_key($input['cloudinary_api_secret']);
        }
        
        if (!empty($input['cloudinary_cloud_name'])) {
            $output['cloudinary']['cloud_name'] = sanitize_text_field($input['cloudinary_cloud_name']);
        }
        
        return $output;
    }
    
    /**
 * Encrypts an API key for secure storage
 * 
 * @param string $key API key to encrypt
 * @return string Encrypted key or original if encryption fails
 */
    private function encrypt_api_key($key) {
        if (empty($key)) {
            return '';
        }

        // Check if encryption is available
        if (!defined('IUR_ENCRYPTION_KEY') || empty(IUR_ENCRYPTION_KEY)) {
            $this->error_handler->log('API key encryption skipped - encryption key not defined');
            return $key;
        }

        try {
            $iv = random_bytes(self::IV_LENGTH);
            $encrypted = openssl_encrypt(
                $key,
                self::ENCRYPTION_METHOD,
                hash('sha256', IUR_ENCRYPTION_KEY),
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($encrypted === false) {
                throw new Exception('Encryption failed');
            }
            
            return base64_encode($iv . $encrypted);
        } catch (Exception $e) {
            $this->error_handler->log('API key encryption failed: ' . $e->getMessage());
            return $key;
        }
    }

/**
     * Improved decryption with better security
     */
    private function decrypt_api_key($encrypted_key) {
        if (empty($encrypted_key)) {
            return '';
        }

        $decoded = base64_decode($encrypted_key, true);
        if (!$decoded) {
            return $encrypted_key; // Probably plaintext
        }

        if (!defined('IUR_ENCRYPTION_KEY') || empty(IUR_ENCRYPTION_KEY)) {
            return $encrypted_key;
        }

        try {
            $iv = substr($decoded, 0, self::IV_LENGTH);
            $encrypted_data = substr($decoded, self::IV_LENGTH);
            
            $decrypted = openssl_decrypt(
                $encrypted_data,
                self::ENCRYPTION_METHOD,
                hash('sha256', IUR_ENCRYPTION_KEY),
                OPENSSL_RAW_DATA,
                $iv
            );
            
            return $decrypted ?: $encrypted_key;
        } catch (Exception $e) {
            $this->error_handler->log('API key decryption failed: ' . $e->getMessage());
            return $encrypted_key;
        }
    }
    
    private function validate_quality($quality) {
        $validons = ['original', 'high', 'medium', 'low'];
        return in_array($quality, $validons) ? $quality : 'high';
    }
    
    public function render_main_section() {
        echo '<p>' . __('Configure image replacement settings', 'iur') . '</p>';
    }
    
    public function render_upload_method_field() {
        $method = $this->settings['upload_method'];
        ?>
        <select name="iur_settings[upload_method]" id="iur-upload-method" class="regular-text">
            <?php foreach ($this->_upload_methods() as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($method, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    private function get_upload_methods() {
        return [
            'freeimage' => __('FreeImage.host', 'iur'),
            'imgbb' => __('ImgBB', 'iur'),
            'cloudinary' => __('Cloudinary', 'iur'),
            'wordpress' => __('WordPress Media Library', 'iur')
        ];
    }
    
    public function render_freeimage_api_field() {
    ?>
    <div class="iur-service-field" data-service="freeimage">
        <!-- تغییر نام فیلد -->
        <input type="password" 
               name="iur_settings[freeimage][api_key]" 
               value="" 
               placeholder="<?php echo $this->_api_key_placeholder('freeimage.api_key'); ?>" 
               class="regular-text">
        <p class="description">
            <?php _e(' API key from', 'iur'); ?> 
            <a href="https://freeimage.host/page/api" tar="_blank">FreeImage.host</a>
        </p>
    </div>
    <?php
}

public function render_imgbb_api_field() {
    ?>
    <div class="iur-service-field" data-service="imgbb">
        <!-- تغییر نام فیلد -->
        <input type="password" 
               name="iur_settings[imgbb][api_key]" 
               value="" 
               placeholder="<?php echo $this->_api_key_placeholder('imgbb.api_key'); ?>" 
               class="regular-text">
        <p class="description">
            <?php _e(' API key from', 'iur'); ?> 
            <a href="https://api.imgbb.com/" tar="_blank">ImgBB</a>
        </p>
    </div>
    <?php
}

public function render_cloudinary_fields() {
    ?>
    <div class="iur-service-field" data-service="cloudinary">
        <div class="iur-api-field">
            <label><?php _e('API Key', 'iur'); ?></label>
            <!-- تغییر نام فیلد -->
            <input type="password" 
                   name="iur_settings[cloudinary][api_key]" 
                   value="" 
                   placeholder="<?php echo $this->_api_key_placeholder('cloudinary.api_key'); ?>" 
                   class="regular-text">
        </div>
        
        <div class="iur-api-field">
            <label><?php _e('API Secret', 'iur'); ?></label>
            <!-- تغییر نام فیلد -->
            <input type="password" 
                   name="iur_settings[cloudinary][api_secret]" 
                   value="" 
                   placeholder="<?php echo $this->_api_key_placeholder('cloudinary.api_secret'); ?>" 
                   class="regular-text">
        </div>
        
        <div class="iur-api-field">
            <label><?php _e('Cloud Name', 'iur'); ?></label>
            <!-- تغییر نام فیلد -->
            <input type="text" 
                   name="iur_settings[cloudinary][cloud_name]" 
                   value="<?php echo esc_attr($this->settings['cloudinary']['cloud_name']); ?>" 
                   class="regular-text">
        </div>
        
        <div class="iur-api-field">
            <label><?php _e('Folder', 'iur'); ?></label>
            <!-- تغییر نام فیلد -->
            <input type="text" 
                   name="iur_settings[cloudinary][folder]" 
                   value="<?php echo esc_attr($this->settings['cloudinary']['folder'] ?? 'iur_uploads'); ?>" 
                   class="regular-text">
            <p class="description"><?php _e('Optional. Default: iur_uploads', 'iur'); ?></p>
        </div>
        
        <div class="iur-api-field">
            <label>
                <!-- تغییر نام فیلد -->
                <input type="checkbox" 
                       name="iur_settings[cloudinary][secure]" 
                       value="1" 
                       <?php checked($this->settings['cloudinary']['secure'] ?? 1, 1); ?>>
                <?php _e('Use HTTPS', 'iur'); ?>
            </label>
        </div>
        
        <p class="description">
            <?php _e('Cloudinary dashboard', 'iur'); ?>: 
            <a href="https://cloudinary.com/console" tar="_blank">cloudinary.com/console</a>
        </p>
    </div>
    <?php
}
    
    public function render_processing_fields() {
        ?>
        <fieldset class="iur-processing-options">
            <div class="iur-field-group">
                <label><?php _e('Image Quality', 'iur'); ?></label>
                <?php $this->render_quality_field(); ?>
            </div>
            
            <div class="iur-field-group">
                <label><?php _e('Tar Content', 'iur'); ?></label>
                <?php $this->render_tar_content_field(); ?>
            </div>
            
            <div class="iur-field-group">
                <label>
                    <input type="checkbox" 
                           name="iur_settings[delete_after_replace]" 
                           value="1" 
                           <?php checked($this->settings['delete_after_replace'], 1); ?>>
                    <?php _e('Delete after replacement', 'iur'); ?>
                </label>
                <p class="description">
                    <?php _e('Remove original images after successful upload', 'iur'); ?>
                </p>
            </div>
            
            <div class="iur-field-group">
    <label>
        <input type="checkbox" 
               name="iur_settings[auto_process_new_posts]" 
               value="1" 
               <?php checked($this->settings['auto_process_new_posts'], 1); ?>>
        <?php _e('Auto-replace on save', 'iur'); ?>
    </label>
    <p class="description">
        <?php _e('Automatically process images when saving posts', 'iur'); ?>
    </p>
</div>
        </fieldset>
        <?php
    }
    
    private function _api_key_placeholder($key_path) {
    $parts = explode('.', $key_path);
    $value = $this->settings;
    
    foreach ($parts as $part) {
        if (isset($value[$part])) {
            $value = $value[$part];
        } else {
            return '';
        }
    }
    
    return $value ? '••••••••' : '';
}
    
    public function render_quality_field() {
        $quality = $this->settings['quality'];
        ?>
        <select name="iur_settings[quality]">
            <?php foreach ($this->get_quality_options() as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($quality, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    private function get_quality_options() {
        return [
            'original' => __('Original (no change)', 'iur'),
            'high' => __('High quality', 'iur'),
            'medium' => __('Medium quality', 'iur'),
            'low' => __('Low quality', 'iur')
        ];
    }
    
    public function render_tar_content_field() {
        $tars = $this->settings['tar_content'];
        ?>
        <div class="iur-checkbox-group">
            <?php foreach ($this->get_content_types() as $value => $label): ?>
                <label>
                    <input type="checkbox" 
                           name="iur_settings[tar_content][]" 
                           value="<?php echo esc_attr($value); ?>" 
                           <?php checked(in_array($value, $tars)); ?>>
                    <?php echo esc_html($label); ?>
                </label><br>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    private function get_content_types() {
        return [
            'post' => __('Posts', 'iur'),
            'product' => __('Products', 'iur'),
            'page' => __('Pages', 'iur'),
            'attachment' => __('Media Library', 'iur')
        ];
    }
    
    public function enqueue_admin_assets($hook) {
        if ('settings_page_iur-settings' !== $hook) return;
        
        wp_enqueue_style(
            'iur-admin-css',
            plugins_url('../assets/css/admin-settings.css', __FILE__),
            [],
            IUR_VERSION
        );
        
        wp_enqueue_script(
            'iur-admin-js',
            plugins_url('../assets/js/admin-settings.js', __FILE__),
            ['jquery'],
            IUR_VERSION,
            true
        );
        
        wp_localize_script('iur-admin-js', 'iurSettings', [
            'nonce' => wp_create_nonce('iur_settings_nonce'),
            'i18n' => [
                'confirmReset' => __('Are you sure you want to reset all settings?', 'iur')
            ]
        ]);
    }
    
public function render_settings_page() {
    ?>
    <div class="wrap iur-settings-wrap">
        <h1><?php _e('Image URL Replacement Settings', 'iur'); ?></h1>

        <?php settings_errors('iur_settings'); ?>

        <form method="post" action="options.php">
            <?php settings_fields('iur_settings_group'); ?>

            <div class="iur-settings-main">
                <?php
                // نمایش تنظیمات از فایل‌های جداگانه
                include IUR_PLUGIN_DIR . 'admin/partials/settings-api.php';
                include IUR_PLUGIN_DIR . 'admin/partials/settings-advanced.php';
                ?>
            </div>

            <input type="submit" name="submit" id="submit" class="button"
       style="background-color: #6A9CA5; border-color: #6A9CA5; color: #fff;"
       value="<?php esc_attr_e('Save Settings', 'iur'); ?>">
        </form>

        <aside class="iur-settings-sidebar">
            <div class="iur-settings-box">
                <h3><?php _e('Quick Actions', 'iur'); ?></h3>
               <button id="iur-reset-settings" class="button"
        style="background-color: #6A9CA5; border: 2px solid #dc3545; color: #fff;">
  <?php _e('Reset Settings', 'iur'); ?>
</button>
            </div>

            <div class="iur-settings-box" style="border: 1px solid #6A9CA5; background-color: #f5fbfc; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
  <h3 style="margin-top: 0; color: #6A9CA5; font-weight: 600;"><?php _e('System Info', 'iur'); ?></h3>
  <ul>
    <li><strong>PHP:</strong> <?php echo phpversion(); ?></li>
    <li><strong>WordPress:</strong> <?php echo get_bloginfo('version'); ?></li>
    <li><strong>Plugin Version:</strong> <?php echo IUR_VERSION; ?></li>
  </ul>
</div>
        </aside>
    </div>
<script>
document.getElementById('iur-reset-settings').addEventListener('click', function(e) {
  e.preventDefault();
  if (confirm('Are you sure you want to reset all settings? This action cannot be undone.')) {
    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=iur_reset_settings'
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        location.reload();
      } else {
        alert('Reset failed. Please try again.');
      }
    });
  }
});
</script>
    <?php
}
    
    public function get($key, $default = null) {
        $parts = explode('.', $key);
        $value = $this->settings;
        
        foreach ($parts as $part) {
            if (isset($value[$part])) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    public function get_api_key($service) {
    switch ($service) {
        case 'freeimage':
            return [
                'api_key' => $this->decrypt_api_key($this->settings['freeimage']['api_key'] ?? '')
            ];
            
        case 'imgbb':
            return [
                'api_key' => $this->decrypt_api_key($this->settings['imgbb']['api_key'] ?? '')
            ];
            
        case 'cloudinary':
            $cloudinary = $this->settings['cloudinary'] ?? [];
            return [
                'api_key' => $this->decrypt_api_key($cloudinary['api_key'] ?? ''),
                'api_secret' => $this->decrypt_api_key($cloudinary['api_secret'] ?? ''),
                'cloud_name' => $cloudinary['cloud_name'] ?? '',
                'folder' => $cloudinary['folder'] ?? 'iur_uploads',
                'secure' => $cloudinary['secure'] ?? true
            ];
            
        default:
            return [];
    }
}

public function update($new_settings) {
    $this->settings = array_merge($this->settings, $new_settings);
    
    update_option('iur_settings', $this->settings);
    
    $this->load_settings();
}

    /**
     * Check user capabilities for settings access
     */
    public function check_user_capabilities() {
        if (isset($_GET['page']) && $_GET['page'] === 'iur-settings') {
            if (!current_user_can('manage_options')) {
                wp_die(__('Permission denied.', 'iur'), '', ['response' => 403]);
            }
        }
    }
    
    /**
     * Add caching support methods
     */
    private function get_cached_settings() {
        return get_transient(self::SETTINGS_CACHE_KEY);
    }
    
    private function set_cached_settings($settings) {
        set_transient(self::SETTINGS_CACHE_KEY, $settings, self::CACHE_TIMEOUT);
    }
    
    private function clear_settings_cache() {
        delete_transient(self::SETTINGS_CACHE_KEY);
    }

}
