<?php
// includes/services/class-iur-freeimage-service.php
class IUR_FreeImage_Service implements IUR_Upload_Interface {
    private $api_key;
    private $quality;
    private $timeout;
    
    public function __construct($api_key, $quality = 'high', $timeout= '30') {
        $this->api_key = $api_key;
        $this->quality = $quality;
        $this->timeout = $timeout;
    }
    
    private function validate_image_data($image_data) {
    // بررسی MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($image_data);
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('نوع تصویر مجاز نیست: ' . $mime_type);
    }
    // بررسی اندازه (مثلاً حداکثر 10MB)
    if (strlen($image_data) > 10 * 1024 * 1024) {
        throw new Exception('حجم تصویر بیش از حد مجاز است');
    }
    return $mime_type;
}

    
    public function upload($image_data, $post_id = 0) {
        $this->validate_credentials();
        $mime_type = $this->validate_image_data($image_data);
        
        $response = wp_remote_post('https://freeimage.host/api/1/upload', [
            'body' => [
                'key' => $this->api_key,
                'source' => base64_encode($image_data),
                'format' => 'json',
                'quality' => $this->map_quality()
            ],
            'timeout' => $this->timeout,
            'headers' => ['User-Agent' => 'WordPress/IUR-Plugin; ' . home_url()]
        ]);
        
        $data = $this->parse_response($response);
        return $data['image']['url'] ?? $data['image']['display_url'];
    }
    
    private function parse_response($response) {
        if (is_wp_error($response)) {
    $error_message = $response->get_error_message();
    error_log('FreeImage Upload Error: ' . $error_message);
    throw new Exception('HTTP Error: ' . $error_message);
}

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($data['status_code']) || $data['status_code'] !== 200) {
    $error = $data['error']['message'] ?? ($data['error'] ?? 'Unknown error');
    error_log('FreeImage API Error: ' . print_r($error, true));
    throw new Exception('API Error: ' . $error);
}

    if (empty($data['image']['url']) && empty($data['image']['display_url'])) {
    throw new Exception('Image URL not found in API response');
}

return $data;

    }
    
    private function map_quality() {
    $quality_map = [
        'low'     => 50,
        'medium'  => 75,
        'high'    => 90,
        'original'=> 100
    ];
    return $quality_map[$this->quality] ?? 90;
}
    
    public function validate_credentials() {
        if (empty($this->api_key)) {
            throw new Exception('FreeImage API key is required');
        }
    }
    
    public function get_method_name() {
        return 'freeimage';
    }
}