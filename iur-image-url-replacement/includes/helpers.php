<?php
function iur_download_image($image_url) {
    $clean_url = strtok($image_url, '?');
    
    $response = wp_remote_get($clean_url, [
        'timeout' => 30,
        'redirection' => 5,
        'sslverify' => false
    ]);
    
    if (is_wp_error($response)) {
        error_log('IUR Download Error: ' . $response->get_error_message());
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        error_log("IUR Download Failed. Status: $response_code - URL: $clean_url");
        return false;
    }
    
    $image_data = wp_remote_retrieve_body($response);
    $image_type = wp_remote_retrieve_header($response, 'content-type');
    
    $valid_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($image_type, $valid_types)) {
        error_log("IUR Invalid Image Type: $image_type - URL: $clean_url");
        return false;
    }
    
    return [
        'data' => $image_data,
        'type' => $image_type
    ];
}

function iur_extract_images_from_content($content) {
    if (!class_exists('DOMDocument')) {
        error_log('IUR Error: DOMDocument not found. Please enable PHP XML extension.');
        return [];
    }
    
    $images = [];
    $dom = new DOMDocument();
    
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();
    
    $img_tags = $dom->getElementsByTagName('img');
    
    foreach ($img_tags as $img) {
        $src = $img->getAttribute('src');
        if (strpos($src, 'data:image') === 0) continue;
        
        $images[] = [
            'src' => $src,
            'alt' => $img->getAttribute('alt'),
            'width' => $img->getAttribute('width'),
            'height' => $img->getAttribute('height')
        ];
    }
    
    return $images;
}

function iur_is_external_url($url) {
    if (strpos($url, '/') === 0) {
        $url = site_url() . $url;
    }
    
    $site_domain = parse_url(site_url(), PHP_URL_HOST);
    $url_domain = parse_url($url, PHP_URL_HOST);
    
    if (!$site_domain || !$url_domain) {
        return true;
    }
    
    $site_domain = str_replace('www.', '', $site_domain);
    $url_domain = str_replace('www.', '', $url_domain);
    
    return $site_domain !== $url_domain;
}
