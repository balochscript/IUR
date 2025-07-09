<?php
class IUR_Uploader {
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('iur_settings');
    }
    
    public function upload_image($image_url, $post_id) {
        $method = $this->settings['upload_method'] ?? 'freeimage';
        
        switch ($method) {
            case 'imgbb':
                return $this->upload_to_imgbb($image_url);
            case 'wordpress':
                return $this->upload_to_wordpress($image_url, $post_id);
            case 'freeimage':
            default:
                return $this->upload_to_freeimage($image_url);
        }
    }
    
    private function upload_to_freeimage($image_url) {
        $api_key = $this->settings['freeimage_api_key'] ?? '';
        
        if (empty($api_key)) {
            throw new Exception('FreeImage API key is missing');
        }
        
        $api = new FreeImage_API($api_key);
        
        // استفاده از cURL به جای file_get_contents برای مدیریت بهتر
        $ch = curl_init($image_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $image_data = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("CURL error: $error");
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("Failed to download image. HTTP code: $http_code");
        }
        
        $image_type = $this->get_image_mime_type($image_data);
        
        $result = $api->upload_image($image_data, $image_type);
        
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        
        return $result;
    }
    
    private function upload_to_imgbb($image_url) {
        $api_key = $this->settings['imgbb_api_key'] ?? '';
        
        if (empty($api_key)) {
            throw new Exception('ImgBB API key is missing');
        }
        
        // دانلود تصویر با cURL
        $ch = curl_init($image_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $image_data = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("CURL error: $error");
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("Failed to download image. HTTP code: $http_code");
        }
        
        $url = 'https://api.imgbb.com/1/upload';
        
        $args = [
            'body' => [
                'key' => $api_key,
                'image' => base64_encode($image_data)
            ],
            'timeout' => 30 // افزایش زمان انتظار
        ];
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('ImgBB: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            throw new Exception('ImgBB: ' . $data['error']['message']);
        }
        
        if (isset($data['data']['url'])) {
            return $data['data']['url'];
        }
        
        throw new Exception('ImgBB: آدرس تصویر در پاسخ وجود ندارد');
    }
    
    private function upload_to_wordpress($image_url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp_file = download_url($image_url);
        
        if (is_wp_error($tmp_file)) {
            throw new Exception('وردپرس: ' . $tmp_file->get_error_message());
        }
        
        $file_array = [
            'name' => basename($image_url),
            'tmp_name' => $tmp_file
        ];
        
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        if (is_wp_error($attachment_id)) {
            @unlink($tmp_file);
            throw new Exception('وردپرس: ' . $attachment_id->get_error_message());
        }
        
        // حذف تصویر اصلی اگر در تنظیمات فعال باشد
        $settings = get_option('iur_settings');
        if ($settings['delete_after_replace']) {
            $this->delete_original_image($image_url, $post_id);
        }
        
        return wp_get_attachment_url($attachment_id);
    }
    
    private function get_image_mime_type($image_data) {
        if (function_exists('finfo_open')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            return $finfo->buffer($image_data);
        }
        
        // راه حل جایگزین برای سرورهایی که finfo ندارند
        $signatures = [
            'image/jpeg' => "\xFF\xD8\xFF",
            'image/png'  => "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A",
            'image/gif'  => "GIF",
        ];
        
        foreach ($signatures as $mime => $signature) {
            if (strpos($image_data, $signature) === 0) {
                return $mime;
            }
        }
        
        return 'application/octet-stream';
    }
    
    private function delete_original_image($image_url, $post_id) {
        $attachment_id = attachment_url_to_postid($image_url);
        
        if ($attachment_id) {
            // حذف فایل فیزیکی و رکورد دیتابیس
            wp_delete_attachment($attachment_id, true);
            
            // حذف از متا پست
            delete_post_meta($post_id, '_thumbnail_id', $attachment_id);
        }
    }
}
