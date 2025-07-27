<?php
class IUR_Cloudinary_Service implements IUR_Upload_Interface {
    private $config;
    private $cloudinary;
    private $timeout;
    
    public function get_method_name() {
        return 'cloudinary';
    }

    public function __construct($config, $timeout= 30) {
        // بررسی وجود SDK
        if (!class_exists('Cloudinary\Cloudinary')) {
            throw new Exception('Cloudinary SDK not loaded. Run composer install.');
        }
        
        $this->config = wp_parse_args($config, [
            'cloud_name' => '',
            'api_key' => '',
            'api_secret' => '',
            'folder' => 'iur_uploads',
            'secure' => true
        ]);
        
        $this->timeout = $timeout;
        
        // Do not validate credentials here, we'll validate when uploading
        // Only initialize the Cloudinary object if we have credentials?
        // We cannot initialize without credentials, so we leave it until upload
    }

    private function initialize_cloudinary() {
        // Moved initialization to upload method after validation
        $this->cloudinary = new \Cloudinary\Cloudinary([
            'cloud' => [
                'cloud_name' => $this->config['cloud_name'],
                'api_key'    => $this->config['api_key'],
                'api_secret' => $this->config['api_secret'],
            ],
            'url' => [
                'secure' => $this->config['secure']
            ]
        ]);
    }
    
    private function validate_image_data($image_data) {
    // بررسی MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($image_data);
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Invalid image type: ' . $mime_type);
    }
    
    // بررسی اندازه
    if (strlen($image_data) > 10 * 1024 * 1024) { // 10MB
        throw new Exception('Image size too large');
    }
    
    return $mime_type;
}


    public function upload($image_data, $post_id = 0) {
    $this->validate_credentials();
    $result = null;
    $temp_file = null;
    
    try {
        if (!isset($this->cloudinary)) {
            $this->initialize_cloudinary();
        }
        
        $temp_file = $this->create_temp_file($image_data);
        
        $upload_options = [
            'folder' => $this->config['folder'],
            'public_id' => 'post_' . $post_id . '_' . time(),
            'overwrite' => false,
            'timeout' => $this->timeout
        ];
        
        $result = $this->cloudinary->uploadApi()->upload($temp_file, $upload_options);
        
        // گزارش کامل‌تر
        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
            'size' => $result['bytes'],
            'format' => $result['format'],
            'width' => $result['width'],
            'height' => $result['height']
        ];
        
    } catch (\Exception $e) {
        error_log('Cloudinary Error: ' . $e->getMessage());
        throw new Exception('Cloudinary upload failed: ' . $e->getMessage());
    } finally {
        // اطمینان از حذف فایل موقت
        if ($temp_file && file_exists($temp_file)) {
            @unlink($temp_file);
        }
    }
}

    private function create_temp_file($data) {
    $mime_type = $this->validate_image_data($data);
    
    // تعیین پسوند صحیح بر اساس MIME type
    $extension = [
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        'image/gif' => '.gif',
        'image/webp' => '.webp'
    ][$mime_type] ?? '.jpg';
    
    $temp_path = wp_tempnam() . $extension;
    
    if (file_put_contents($temp_path, $data) === false) {
        throw new Exception('Failed to write temporary file');
    }
    
    return $temp_path;
}


    public function validate_credentials() {
        if (empty($this->config['cloud_name']) || 
            empty($this->config['api_key']) || 
            empty($this->config['api_secret'])) {
            throw new Exception('Cloudinary credentials incomplete');
        }
    }
}