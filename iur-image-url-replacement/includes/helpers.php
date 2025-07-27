<?php
function iur_download_image($image_url) {
    $clean_url = strtok($image_url, '?');
    
$response = wp_remote_get($clean_url, [
    'timeout' => 30,
    'redirection' => 5,
    'sslverify' => false,
    'user-agent' => 'Mozilla/5.0 (compatible; IUR-Plugin)'
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
    
    // استخراج srcset (اگر وجود دارد)
    if ($img->hasAttribute('srcset')) {
        $sources = explode(',', $img->getAttribute('srcset'));
        foreach ($sources as $source) {
            $srcset_src = trim(explode(' ', $source)[0]);
            if ($srcset_src && $srcset_src !== $src) { // جلوگیری از تکرار
                $images[] = ['src' => $srcset_src];
            }
        }
    }
    
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
    
    $url_domain = parse_url($url, PHP_URL_HOST);
    if (!$url_domain) return true;
    
    $test_urls = [
    '/wp-content/uploads/image.jpg', // باید داخلی تشخیص داده شود
    'https://cdn.example.net/image.jpg', // باید خارجی تشخیص داده شود
    'https://external.com/image.jpg' // باید خارجی تشخیص داده شود
];

    // دامنه‌های مجاز (پیشنهادی - باید با نیازهای شما تطبیق داده شود)
    $allowed_domains = [
        str_replace('www.', '', parse_url(site_url(), PHP_URL_HOST)),
        'cdn.example.net',
        'images.example.org'
    ];
    
    $url_domain = str_replace('www.', '', $url_domain);
    return !in_array($url_domain, $allowed_domains);
}

function iur_extract_all_images_from_post($post) {
    $images = [];

    // 1️⃣ تصاویر <img> داخل محتوای HTML
    if (class_exists('DOMDocument')) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML(mb_convert_encoding($post->post_content, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $img_tags = $dom->getElementsByTagName('img');

        foreach ($img_tags as $img) {
            $src = $img->getAttribute('src');
            if (strpos($src, 'data:image') === 0) continue;

            $images[] = [
                'src' => $src,
                'alt' => $img->getAttribute('alt'),
                'type' => 'html',
                'width' => $img->getAttribute('width'),
                'height' => $img->getAttribute('height')
            ];
        }
    }

    // 2️⃣ تصویر شاخص (featured image)
    $thumb_id = get_post_thumbnail_id($post->ID);
    if ($thumb_id) {
        $thumb_url = wp_get_attachment_url($thumb_id);
        if ($thumb_url) {
            $images[] = [
                'src' => $thumb_url,
                'type' => 'thumbnail'
            ];
        }
    }

    // 3️⃣ تصاویر گالری محصول (در ووکامرس یا متای سفارشی)
    $gallery = get_post_meta($post->ID, 'product_image_gallery', true);
    if (!empty($gallery)) {
        $ids = is_array($gallery) ? $gallery : explode(',', $gallery);
        foreach ($ids as $img_id) {
            $url = wp_get_attachment_url(trim($img_id));
            if ($url) {
                $images[] = [
                    'src' => $url,
                    'type' => 'gallery'
                ];
            }
        }
    }
    
    if (function_exists('get_field')) {
    $acf_images = get_field('gallery_field', $post->ID);
    if ($acf_images && is_array($acf_images)) {
        foreach ($acf_images as $img) {
            if (!empty($img['url'])) {
                $images[] = ['src' => $img['url'], 'type' => 'acf'];
            }
        }
    }
}

    // 4️⃣ بررسی محتوای تاپیک‌های bbPress (post_type = topic یا reply)
    $post_type = get_post_type($post);
    if (in_array($post_type, ['topic', 'reply'])) {
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $post->post_content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $src) {
                if (strpos($src, 'data:image') === 0) continue;
                $images[] = [
                    'src' => $src,
                    'type' => 'bbpress'
                ];
            }
        }
    }
    
    $images = array_unique($images, SORT_REGULAR);

    return $images;
}
