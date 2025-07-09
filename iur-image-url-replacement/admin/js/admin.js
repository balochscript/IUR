jQuery(document).ready(function($) {
    // ======= مدیریت نمایش فیلدهای API ======= //
    function toggleServiceFields() {
        // مخفی کردن تمام فیلدهای سرویس
        $('.iur-service-field').hide();
        
        // دریافت سرویس انتخاب شده
        const service = $('#iur-upload-method').val();
        
        // نمایش فیلد مربوط به سرویس انتخاب شده
        if(service === 'freeimage') {
            $('#iur-freeimage-field').show();
        } else if(service === 'imgbb') {
            $('#iur-imgbb-field').show();
        }
        // برای 'wordpress' هیچ فیلدی نمایش داده نمی‌شود
    }

    // اجرای تابع هنگام بارگذاری صفحه
    toggleServiceFields();
    
    // اجرای تابع هنگام تغییر انتخاب
    $('#iur-upload-method').change(function() {
        toggleServiceFields();
    });

    // ======= مدیریت پیشرفت پردازش دسته‌ای ======= //
    $('#iur-process-all').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const originalText = $button.text();
        const $progressBar = $('.iur-progress-bar');
        const $progressContainer = $('#iur-progress');
        const $resultsContainer = $('#iur-results');
        
        $button.prop('disabled', true).text('در حال پردازش...');
        $progressContainer.show();
        $resultsContainer.hide();
        $progressBar.css('width', '0%');
        
        // ارسال درخواست AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'iur_process_all_posts',
                security: iur_vars.nonce
            },
            dataType: 'json',
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                
                // ردیابی پیشرفت
                xhr.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        $progressBar.css('width', percent + '%');
                    }
                });
                
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    $resultsContainer.html(`
                        <div class="notice notice-success">
                            <p><strong>پردازش با موفقیت انجام شد!</strong></p>
                            <p>تعداد پست‌های پردازش شده: ${response.data.processed}</p>
                            <p>تعداد تصاویر جایگزینی شده: ${response.data.replaced}</p>
                            <p>تعداد خطاها: ${response.data.errors}</p>
                        </div>
                    `).show();
                } else {
                    $resultsContainer.html(`
                        <div class="notice notice-error">
                            <p><strong>خطا در پردازش:</strong> ${response.data.message}</p>
                        </div>
                    `).show();
                }
            },
            error: function(xhr) {
                $resultsContainer.html(`
                    <div class="notice notice-error">
                        <p><strong>خطای سیستمی:</strong> ${xhr.responseText || 'خطای ناشناخته'}</p>
                    </div>
                `).show();
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // ======= پاکسازی خطاها ======= //
    $('#iur-clear-errors').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('آیا مطمئن هستید می‌خواهید تمام خطاها را پاک کنید؟')) {
            return;
        }
        
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('در حال پاکسازی...');
        
        $.post(ajaxurl, {
            action: 'iur_clear_errors',
            security: iur_vars.nonce
        }, function(response) {
            if (response.success) {
                $('#iur-error-log').html('<p>هیچ خطایی ثبت نشده است</p>');
            } else {
                alert('خطا در پاکسازی خطاها: ' + response.data.message);
            }
            $button.prop('disabled', false).text(originalText);
        });
    });

    // ======= نمایش/پنهان کردن تنظیمات پیشرفته ======= //
    $('.iur-toggle-advanced').on('click', function(e) {
        e.preventDefault();
        $('.iur-advanced-settings').slideToggle();
    });
});
