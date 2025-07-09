<?php
class IUR_Processor {
    private static $instance;
    private $uploader;
    private $batch_size = 5; // تعداد پست‌ها در هر بسته
    
    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $settings = get_option('iur_settings');
        
        require_once plugin_dir_path(__FILE__) . 'class-iur-uploader.php';
        $this->uploader = new IUR_Uploader();
        
        if (isset($settings['auto_replace']) && $settings['auto_replace'] === 'yes') {
            $post_types = isset($settings['post_types']) ? $settings['post_types'] : ['post'];
            foreach ($post_types as $post_type) {
                add_action("save_post_{$post_type}", [$this, 'process_post_images'], 10, 2);
            }
        }
    }
    
    public function process_post_images($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        
        $images = $this->extract_images_from_content($post->post_content);
        $new_content = $post->post_content;
        $count_replaced = 0;
        $errors = [];
        
        foreach ($images as $image) {
            $src = $image['src'];
            if (strpos($src, '/') === 0) {
                $src = site_url() . $src;
            }
            
            if ($this->is_external_url($src)) {
                continue;
            }
            
            try {
                $new_url = $this->uploader->upload_image($src, $post_id);
                $new_content = str_replace($image['src'], $new_url, $new_content);
                $count_replaced++;
            } catch (Exception $e) {
                $errors[] = [
                    'time' => current_time('mysql'),
                    'message' => "Upload failed for {$src}: " . $e->getMessage(),
                    'post_id' => $post_id
                ];
            }
        }
        
        if ($count_replaced > 0) {
            $post_type = get_post_type($post_id);
            remove_action("save_post_{$post_type}", [$this, 'process_post_images'], 10);
            
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $new_content
            ]);
            
            add_action("save_post_{$post_type}", [$this, 'process_post_images'], 10, 2);
        }
        
        if (!empty($errors)) {
            $this->log_errors($errors);
        }
    }
    
    public static function process_all_posts() {
        $processor = self::init();
        $settings = get_option('iur_settings');
        $post_types = isset($settings['post_types']) ? $settings['post_types'] : ['post'];
        $target_content = $settings['target_content'] ?? ['post', 'product'];
        
        $args = [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids'
        ];
        
        $post_ids = get_posts($args);
        $total_replaced = 0;
        $errors = [];
        $processed = 0;
        
        foreach ($post_ids as $post_id) {
            if (!in_array(get_post_type($post_id), $target_content)) {
                continue;
            }
            
            $post = get_post($post_id);
            $result = $processor->process_post($post_id, $post);
            
            $total_replaced += $result['replaced'];
            $errors = array_merge($errors, $result['errors']);
            $processed++;
            
            // توقف پس از هر 5 پست برای جلوگیری از تایم‌اوت
            if ($processed % $processor->batch_size === 0) {
                sleep(1); // استراحت کوتاه
            }
        }
        
        return [
            'total_processed' => $processed,
            'total_replaced' => $total_replaced,
            'total_errors' => count($errors)
        ];
    }
    
    private function process_post($post_id, $post) {
        $images = $this->extract_images_from_content($post->post_content);
        $new_content = $post->post_content;
        $count_replaced = 0;
        $errors = [];
        
        foreach ($images as $image) {
            $src = $image['src'];
            if (strpos($src, '/') === 0) {
                $src = site_url() . $src;
            }
            
            if ($this->is_external_url($src)) {
                continue;
            }
            
            try {
                $new_url = $this->uploader->upload_image($src, $post_id);
                $new_content = str_replace($image['src'], $new_url, $new_content);
                $count_replaced++;
            } catch (Exception $e) {
                $errors[] = [
                    'time' => current_time('mysql'),
                    'message' => "Upload failed for {$src}: " . $e->getMessage(),
                    'post_id' => $post_id
                ];
            }
        }
        
        if ($count_replaced > 0) {
            $post_type = get_post_type($post_id);
            remove_action("save_post_{$post_type}", [$this, 'process_post_images'], 10);
            
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $new_content
            ]);
            
            add_action("save_post_{$post_type}", [$this, 'process_post_images'], 10, 2);
        }
        
        return [
            'replaced' => $count_replaced,
            'errors' => $errors
        ];
    }
    
    private function extract_images_from_content($content) {
        $images = [];
        
        // الگوی پیشرفته‌تر برای شناسایی تمام تصاویر
        preg_match_all('/<img[^>]+src=([\'"])(?<src>.+?)\1[^>]*>/i', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $images[] = [
                'src' => $match['src'],
                'full_tag' => $match[0]
            ];
        }
        
        // شناسایی تصاویر پس‌زمینه
        preg_match_all('/style=[\'"][^\'"]*url\(([\'"]?)(?<url>.+?)\1\)/i', $content, $bg_matches, PREG_SET_ORDER);
        
        foreach ($bg_matches as $match) {
            $images[] = [
                'src' => $match['url'],
                'full_tag' => $match[0]
            ];
        }
        
        return $images;
    }
    
    private function is_external_url($url) {
        $site_url = site_url();
        $home_url = home_url();
        
        return strpos($url, $site_url) === false && strpos($url, $home_url) === false;
    }
    
    private function log_errors($errors) {
        $error_log = get_option('iur_error_log', []);
        $error_log = array_merge($error_log, $errors);
        
        // ذخیره فقط 100 خطای آخر
        if (count($error_log) > 100) {
            $error_log = array_slice($error_log, -100);
        }
        
        update_option('iur_error_log', $error_log);
    }
}
