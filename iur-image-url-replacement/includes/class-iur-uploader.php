<?php
// includes/class-iur-uploader.php
class IUR_Uploader {
    private $upload_service;
    private $error_handler;
    
    private function __construct() {
    $this->error_handler = IUR_Error_Handler::get_instance();
    $this->init_upload_service();
    
    // بررسی موفقیت initialization
    if (!$this->upload_service) {
        throw new Exception('Failed to initialize upload service.');
    }
}
    
    public static function get_instance() {
    static $instance = null;

    if ($instance === null) {
        $instance = new self();
    }

    return $instance;
}

   public function get_method() {
    $settings = IUR_Settings::get_instance();
    return $settings->get('upload_method');
}
    
    private function init_upload_service() {
    $settings = IUR_Settings::get_instance();
    $factory  = new IUR_Uploader_Factory($settings, IUR_Error_Handler::get_instance());
    $method   = $settings->get('upload_method');
    $this->upload_service = $factory->create($method);
}

    
    public function upload_images(array $image_urls, $post_id = 0) {
    $results = [];
    foreach ($image_urls as $url) {
        try {
            $results[] = [
                'success' => true,
                'url' => $this->upload_image($url, $post_id),
                'original' => $url
            ];
        } catch (Exception $e) {
            $results[] = [
                'success' => false,
                'error' => $e->getMessage(),
                'original' => $url
            ];
            $this->error_handler->log($e->getMessage(), 'upload', [
                'post_id' => $post_id,
                'image_url' => $url
            ]);
        }
    }
    return $results;
}

private function encrypt_data($data, $key) {
    $iv = openssl_random_pseudo_bytes(16);
    return openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
}
    
    private function download_image($url, $timeout = 60) {
    $args = [
        'timeout' => $timeout,
        'user-agent' => 'WordPress/IUR-Plugin; ' . home_url(),
        'headers' => [
            'Accept' => 'image/*'
        ],
        'sslverify' => true
    ];
    
    $response = wp_remote_get($url, $args);
    
    if (is_wp_error($response)) {
        throw new Exception('Download failed: ' . $response->get_error_message());
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        throw new Exception("HTTP Error $status_code while downloading image.");
    }
    
    $content_type = wp_remote_retrieve_header($response, 'content-type');
    if (!str_starts_with($content_type, 'image/')) {
        throw new Exception('Downloaded content is not an image.');
    }
    
    return wp_remote_retrieve_body($response);
}

private function validate_downloaded_image($image_data) {
    // بررسی اندازه
    if (strlen($image_data) > 10 * 1024 * 1024) { // 10MB
        throw new Exception('Downloaded image size exceeds limit (10MB).');
    }
    
    // بررسی MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($image_data);
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($mime_type, $allowed_mimes)) {
        throw new Exception('Invalid image MIME type: ' . $mime_type);
    }
    
    // بررسی signature فایل
    $file_signature = substr($image_data, 0, 4);
    $valid_signatures = [
        "\xFF\xD8\xFF",      // JPEG
        "\x89\x50\x4E\x47", // PNG
        "\x47\x49\x46\x38", // GIF
        "\x52\x49\x46\x46"  // WebP
    ];
    
    $is_valid_signature = false;
    foreach ($valid_signatures as $signature) {
        if (strpos($file_signature, substr($signature, 0, strlen($signature))) === 0) {
            $is_valid_signature = true;
            break;
        }
    }
    
    if (!$is_valid_signature) {
        throw new Exception('Invalid image file signature.');
    }
}

private function is_blocked_domain($domain) {
    // دامنه‌های محلی و خطرناک
    $blocked_domains = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '::1'
    ];
    
    // بررسی IP های محلی
    if (filter_var($domain, FILTER_VALIDATE_IP)) {
        if (filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
    }
    
    return in_array(strtolower($domain), $blocked_domains);
}


    public function upload_image($image_url, $post_id = 0, $max_retries = 3) {
    // اعتبارسنجی پیشرفته URL
    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Invalid image URL.');
    }
    
    // بررسی پروتکل امن
    $parsed_url = parse_url($image_url);
    if (!in_array($parsed_url['scheme'], ['http', 'https'])) {
        throw new InvalidArgumentException('Only HTTP/HTTPS protocols are allowed.');
    }
    
    // بررسی دامنه‌های مجاز
    if ($this->is_blocked_domain($parsed_url['host'])) {
        throw new InvalidArgumentException('Domain is blocked for security reasons.');
    }
    
    // اعتبارسنجی پسوند فایل
    $path_info = pathinfo($parsed_url['path']);
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (isset($path_info['extension']) && !in_array(strtolower($path_info['extension']), $allowed_extensions)) {
        throw new InvalidArgumentException('File extension not allowed.');
    }
    
    $retry_count = 0;
    while ($retry_count < $max_retries) {
        try {
            $image_data = $this->download_image($image_url);
            $this->validate_downloaded_image($image_data);
            return $this->upload_service->upload($image_data, $post_id);
        } catch (Exception $e) {
            $retry_count++;
            if ($retry_count === $max_retries) {
                if (method_exists($this->error_handler, 'log')) {
                    $this->error_handler->log($e->getMessage(), 'upload', [
                        'post_id' => $post_id,
                        'image_url' => $image_url
                    ]);
                } else {
                    error_log('IUR Upload Error: ' . $e->getMessage());
                }
                throw $e;
            }
            sleep(1);
        }
    }
}
}
