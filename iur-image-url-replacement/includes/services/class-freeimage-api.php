<?php
class FreeImage_API {
    const API_ENDPOINT = 'https://freeimage.host/api/1/upload';
    
    private $api_key;
    
    public function __construct($api_key) {
        $this->api_key = $api_key;
    }
    
    public function upload_image($image_data, $image_type) {
        if (empty($this->api_key)) {
            return new WP_Error('missing_api_key', 'FreeImage.host API key is missing');
        }
        
        // ساخت فرمت صحیح base64 با پیشوند MIME type
        $base64_image = 'data:' . $image_type . ';base64,' . base64_encode($image_data);
        
        $args = [
            'body' => [
                'key' => $this->api_key,
                'source' => $base64_image, // تغییر مهم: ارسال با فرمت data URI
                'format' => 'json',
                'action' => 'upload', // اضافه کردن پارامتر action
                'type' => 'file' // مشخص کردن نوع آپلود
            ],
            'timeout' => 45,
            'headers' => [
                'Accept' => 'application/json' // درخواست پاسخ JSON
            ]
        ];
        
        $response = wp_remote_post(self::API_ENDPOINT, $args);
        
        if (is_wp_error($response)) {
            error_log('FreeImage HTTP Error: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // لاگ‌گیری پیشرفته برای دیباگ
        error_log('FreeImage API Response Code: ' . $response_code);
        error_log('FreeImage API Response: ' . print_r($data, true));
        
        // مدیریت خطاهای API
        if ($response_code !== 200) {
            $error_msg = $data['error']['message'] ?? 'Unknown API error';
            return new WP_Error('api_error', 'HTTP ' . $response_code . ': ' . $error_msg);
        }
        
        // استخراج URL تصویر از ساختارهای مختلف پاسخ
        if (isset($data['image']['url'])) {
            return $data['image']['url'];
        }
        
        if (isset($data['image']['display_url'])) {
            return $data['image']['display_url'];
        }
        
        if (isset($data['image']['medium']['url'])) {
            return $data['image']['medium']['url'];
        }
        
        return new WP_Error('invalid_response', 'URL not found in API response');
    }
}
