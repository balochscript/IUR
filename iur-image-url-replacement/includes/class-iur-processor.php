<?php
class IUR_Processor {
    private static $instance = null;
    private $uploader;
    private $error_handler;
    private $settings;
    
    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->uploader = IUR_Uploader::get_instance();
        $this->error_handler = IUR_Error_Handler::get_instance();
        $this->settings = IUR_Settings::get_instance();
        
        $this->register_auto_replace_hooks();
    }

    /**
     * Register auto-replace hooks based on settings
     */
    private function register_auto_replace_hooks() {
        $auto_replace = $this->settings->get('auto_replace', 'no');
        $post_types = $this->settings->get('target_content', ['post', 'product']);
        
        if ($auto_replace === 'yes') {
            foreach ($post_types as $post_type) {
                add_action("save_post_{$post_type}", [$this, 'process_post_on_save'], 10, 2);
            }
        }
    }

    /**
     * Handle post processing on save
     */
    public function process_post_on_save($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        return $this->process_post($post_id, $post);
    }

    /**
     * Process a single post
     */
    public function process_post($post_id, $post = null) {
        if (!$post) {
            $post = get_post($post_id);
        }
        
        if (!$this->should_process_post($post_id)) {
            return ['replaced' => 0, 'errors' => []];
        }

        $images = $this->extract_all_images($post);
        $results = $this->process_images($images, $post_id);
        
        $this->update_post_content($post_id, $post->post_content, $results['new_content']);
        $this->save_report($post_id, $results);
        
        return [
            'replaced' => $results['count_replaced'],
            'errors' => $results['errors']
        ];
    }
    
    public function process_batch($offset = 0, $limit = 5) {
    // دریافت پست‌های واجد شرایط
    $post_ids = get_posts([
        'post_type' => $this->get_eligible_post_types(),
        'post_status' => 'publish',
        'fields' => 'ids',
        'offset' => $offset,
        'posts_per_page' => $limit,
        'meta_query' => [
            [
                'key' => '_iur_processed',
                'compare' => 'NOT EXISTS'
            ]
        ]
    ]);
    
    $total_posts = $this->count_eligible_posts();
    $processed = 0;
    $errors = 0;
    
    foreach ($post_ids as $post_id) {
        try {
            $this->process_post($post_id);
            update_post_meta($post_id, '_iur_processed', time());
            $processed++;
        } catch (Exception $e) {
            $errors++;
            error_log("IUR Error processing post {$post_id}: " . $e->getMessage());
        }
    }
    
    return [
        'processed' => $processed,
        'errors' => $errors,
        'total' => $total_posts,
        'completed' => (($offset + $limit) >= $total_posts)
    ];
}

private function count_eligible_posts() {
    // محاسبه کل پست‌های واجد شرایط
    $count = 0;
    $post_types = $this->get_eligible_post_types();
    
    foreach ($post_types as $post_type) {
        $count += wp_count_posts($post_type)->publish;
    }
    
    return $count;
}

private function get_eligible_post_types() {
    // دریافت انواع پست‌های قابل پردازش از تنظیمات
    $settings = IUR_Settings::get_instance()->get_all();
    return $settings['post_types'] ?? ['post'];
}

    /**
     * Count available posts for processing
     */
    private function count_available_posts($post_types) {
        $query = new WP_Query([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_iur_upload_status',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);
        
        return $query->found_posts;
    }

    /**
     * Check if post should be processed
     */
    private function should_process_post($post_id) {
        $post_type = get_post_type($post_id);
        $target_content = $this->settings->get('target_content', ['post', 'product']);
        
        return in_array($post_type, $target_content);
    }
    
    /**
 * Extract images from post content
 */
private function extract_images_from_content($content) {
    $images = [];
    
    // اگر محتوا خالی باشد، آرایه خالی برگردان
    if (empty(trim($content))) {
        return $images;
    }
    
    // استفاده از DOMDocument برای تجزیه محتوای HTML
    libxml_use_internal_errors(true); // فعال‌سازی مدیریت خطاهای داخلی
    $dom = new DOMDocument();
    
    // فقط اگر محتوا غیر خالی باشد، تجزیه را انجام بده
    @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors(); // پاک کردن خطاهای تجزیه
    
    // یافتن تمام تگ‌های تصویر
    $img_tags = $dom->getElementsByTagName('img');
    foreach ($img_tags as $img) {
        $src = $img->getAttribute('src');
        if (!empty($src)) {
            $images[] = [
                'src' => $src,
                'type' => 'content'
            ];
        }
    }
    
    // یافتن تصاویر در گالری‌ها (شورتکدهای وردپرس)
    preg_match_all('/\[gallery[^\]]*ids="([^"]+)"[^\]]*\]/', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $ids) {
            $attachment_ids = explode(',', $ids);
            foreach ($attachment_ids as $id) {
                $src = wp_get_attachment_url($id);
                if ($src) {
                    $images[] = [
                        'src' => $src,
                        'type' => 'gallery'
                    ];
                }
            }
        }
    }
    
    return $images;
}

/**
 * Extract images from galleries
 */
private function extract_gallery_images($post) {
    $images = [];
    
    // Get gallery shortcodes from content
    preg_match_all('/\[gallery[^\]]*ids="([^"]+)"[^\]]*\]/', $post->post_content, $matches);
    
    if (!empty($matches[1])) {
        $ids = explode(',', $matches[1][0]);
        foreach ($ids as $id) {
            $src = wp_get_attachment_url($id);
            if ($src) {
                $images[] = [
                    'src' => $src,
                    'type' => 'gallery'
                ];
            }
        }
    }
    
    return $images;
}

/**
 * Extract images from custom fields (ACF/Meta)
 */
private function extract_custom_field_images($post) {
    $images = [];
    
    // Example for ACF image field
    if (function_exists('get_field')) {
        $fields = ['featured_image', 'product_images', 'gallery'];
        
        foreach ($fields as $field) {
            $image = get_field($field, $post->ID);
            if ($image) {
                if (is_array($image)) {
                    // Image array format
                    $images[] = [
                        'src' => $image['url'],
                        'type' => 'custom_field'
                    ];
                } elseif (is_numeric($image)) {
                    // Attachment ID
                    $src = wp_get_attachment_url($image);
                    if ($src) {
                        $images[] = [
                            'src' => $src,
                            'type' => 'custom_field'
                        ];
                    }
                } elseif (is_string($image)) {
                    // Direct URL
                    $images[] = [
                        'src' => $image,
                        'type' => 'custom_field'
                    ];
                }
            }
        }
    }
    
    return $images;
}

    /**
     * Extract all images from post
     */
    
    /**
 * Extract all images from post
 */
private function extract_all_images($post) {
    $images = [];
    
    // Process content images if enabled
    if ($this->settings->get('process_content_images', 1)) {
        $content_images = $this->extract_images_from_content($post->post_content);
        $images = array_merge($images, $content_images);
    }
    
    // Process featured image if enabled
    if ($this->settings->get('process_featured_image', 1)) {
        $featured_image = $this->extract_featured_image($post->ID);
        if ($featured_image) {
            $images[] = $featured_image;
        }
    }
    
    // Process galleries if enabled
    if ($this->settings->get('process_galleries', 1)) {
        $gallery_images = $this->extract_gallery_images($post);
        $images = array_merge($images, $gallery_images);
    }
    
    // Process custom fields if enabled
    if ($this->settings->get('process_custom_fields', 0)) {
        $custom_field_images = $this->extract_custom_field_images($post);
        $images = array_merge($images, $custom_field_images);
    }
    
    // Remove duplicates
    $images = array_unique($images, SORT_REGULAR);
    
    return $images;
}

    /**
     * Extract featured image
     */
    private function extract_featured_image($post_id) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if (!$thumbnail_id) {
            return false;
        }
        
        $src = wp_get_attachment_url($thumbnail_id);
        return [
            'src' => $src,
            'type' => 'featured'
        ];
    }

    /**
     * Process list of images
     */
    private function process_images($images, $post_id) {
       $post = get_post($post_id);
    $new_content = $post->post_content; 
    $count_replaced = 0;
    $errors = [];
    $report_images = [];

    foreach ($images as $image) {
        $result = $this->process_single_image($image, $post_id, $new_content);
        
        if ($result['replaced']) {
            $new_content = $result['new_content']; // محتوای آپدیت شده
            $count_replaced++;
            }
            
            $report_images[] = $result['report'];
            
            if (!empty($result['error'])) {
                $errors[] = $result['error'];
            }
        }

        return [
            'new_content' => $new_content,
            'count_replaced' => $count_replaced,
            'errors' => $errors,
            'report_images' => $report_images
        ];
    }

    /**
     * Process single image
     */
    private function process_single_image($image, $post_id) {
        $src = $this->normalize_image_url($image['src']);
        
        if ($this->is_external_url($src)) {
            return [
                'replaced' => false,
                'report' => $this->create_image_report($src, false, 'external'),
                'error' => null
            ];
        }

        try {
            $new_url = $this->uploader->upload_image($src, $post_id);
            $new_content = $this->replace_image_in_content($src, $new_url);
            
            return [
                'replaced' => true,
                'new_content' => $new_content,
                'report' => $this->create_image_report($src, true, '', $new_url),
                'error' => null
            ];
        } catch (Exception $e) {
            $error_msg = "آپلود ناموفق برای {$src}: " . $e->getMessage();
            $this->error_handler->log($error_msg);
            
            return [
                'replaced' => false,
                'report' => $this->create_image_report($src, false, $error_msg),
                'error' => $this->create_error_log($post_id, $error_msg)
            ];
        }
    }

    /**
     * Replace image in content safely
     */
    private function replace_image_in_content($old_url, $new_url, $content = '') {
        $escaped_old = preg_quote(htmlspecialchars($old_url, ENT_QUOTES), '/');
        return preg_replace(
            '/("|\')' . $escaped_old . '("|\')/',
            '$1' . htmlspecialchars($new_url, ENT_QUOTES) . '$2',
            $content
        );
    }

    /**
     * Update post content if changed
     */
    private function update_post_content($post_id, $old_content, $new_content) {
        if (!empty($new_content) && $old_content !== $new_content) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $new_content
            ]);
        }
    }

    /**
     * Save processing report
     */
    private function save_report($post_id, $results) {
        $report_data = [
            'status' => $results['count_replaced'] > 0 ? 'done' : 'skipped',
            'service' => $this->uploader->get_method(),
            'images' => $results['report_images'],
            'timestamp' => current_time('mysql')
        ];

        if (!update_post_meta($post_id, '_iur_upload_status', $report_data)) {
            $this->error_handler->log("Failed to save report for post {$post_id}");
        }
    }

    /**
     * Standard image report structure
     */
    private function create_image_report($src, $success, $reason = '', $new_url = null) {
        return [
            'original_url' => $src,
            'uploaded_url' => $new_url,
            'success' => $success,
            'reason' => $reason,
            'timestamp' => current_time('mysql')
        ];
    }

    /**
     * Standard error log structure
     */
    private function create_error_log($post_id, $message) {
        return [
            'time' => current_time('mysql'),
            'message' => $message,
            'post_id' => $post_id
        ];
    }

    /**
     * Check if URL is external
     */
    private function is_external_url($url) {
        $site_url = site_url();
        return strpos($url, $site_url) === false;
    }

    /**
     * Normalize image URL
     */
    private function normalize_image_url($url) {
        return str_replace(['https://', 'http://'], '//', $url);
    }
}
