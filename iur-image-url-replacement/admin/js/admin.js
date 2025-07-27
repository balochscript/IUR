jQuery(document).ready(function($) {
    // ======= مدیریت نمایش فیلدهای API ======= //
    function toggleServiceFields() {
        $('.iur-service-field').hide();
        const service = $('#iur-upload-method').val();
        
        switch(service) {
            case 'freeimage':
                $('#tr_freeimage_api_key').show();
                break;
            case 'imgbb':
                $('#tr_imgbb_api_key').show();
                break;
            case 'cloudinary':
                $('#tr_cloudinary_api_key, #tr_cloudinary_api_secret, #tr_cloudinary_cloud_name').show();
                break;
        }
    }

    // اجرای اولیه و رویداد تغییر
    toggleServiceFields();
    $('#iur-upload-method').on('change', toggleServiceFields);

    // ======= پاکسازی خطاها ======= //
    $('#iur-clear-errors').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('آیا مطمئن هستید می‌خواهید تمام خطاها را پاک کنید؟')) return;
        
        const $button = $(this);
        const originalText = $button.text();
        $button.prop('disabled', true).text('در حال پاکسازی...');
        
        $.post(iur_vars.ajaxurl, {
            action: 'iur_clear_errors',
            security: iur_vars.nonce
        }).done(function(response) {
            if (response?.success) {
                $('#iur-error-log').html('<p class="notice notice-success">خطاها با موفقیت پاک شدند</p>');
            } else {
                alert('خطا: ' + (response?.data?.message || 'پاسخ نامعتبر از سرور'));
            }
        }).always(() => {
            $button.prop('disabled', false).text(originalText);
        });
    });

    // ======= نمایش/پنهان کردن تنظیمات پیشرفته ======= //
    $('.iur-toggle-advanced').on('click', function(e) {
        e.preventDefault();
        $('.iur-advanced-settings').stop(true, true).slideToggle();
    });
    
    // ======= پردازش تکی پست‌ها ======= //
    $('.iur-process-post').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const post_id = $button.data('postid');
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('در حال پردازش...');
        
        $.post(iur_vars.ajaxurl, {
            action: 'iur_process_single_post',
            security: iur_vars.nonce,
            post_id: post_id
        }).done(function(response) {
            if (response.success) {
                alert('پست با موفقیت پردازش شد! تصاویر جایگزین شده: ' + response.data.replaced);
                location.reload();
            } else {
                alert('خطا: ' + response.data.message);
            }
        }).fail(function() {
            alert('خطا در ارتباط با سرور');
        }).always(() => {
            $button.prop('disabled', false).text(originalText);
        });
    });
    // ======= پردازش گروهی ======= //
$('#iur_bulk_process').on('click', function(e) {
    e.preventDefault();
    
    const $button = $(this);
    const $status = $('#iur_bulk_process_status');
    const $progressBar = $('#iur_progress_bar');
    const $progressFill = $('#iur_progress_bar_fill');
    const $progressText = $('#iur_progress_text');
    const $ajaxResult = $('#iur_ajax_result');
    const $ajaxOutput = $('#iur_ajax_output');

    // تنظیمات اولیه
    $button.prop('disabled', true);
    $status.html('<span class="spinner is-active"></span> در حال پردازش...');
    $progressBar.show();
    $ajaxResult.hide();
    $ajaxOutput.empty();

    // پارامترهای پردازش
    let processed = 0;
    let total = 0;
    let errors = 0;

    // تابع بازگشتی برای پردازش دسته‌ای
    function processBatch(offset = 0) {
        $.ajax({
            url: iur_vars.ajaxurl,
            type: 'POST',
            dataType: 'json',
            timeout: parseInt(iur_vars.timeout) * 1000,
            data: {
                action: 'iur_bulk_process', // تغییر به این نام
                offset: offset,
                bulk_limit: iur_vars.bulk_limit,
                security: iur_vars.nonce // تغییر به security
            },
            success: function(response) {
                if (response.success) {
                    processed += response.data.processed;
                    total = response.data.total || total;
                    errors += response.data.errors || 0;

                    // به‌روزرسانی پیشرفت
                    const percent = Math.round((processed / total) * 100);
                    $progressFill.css('width', percent + '%');
                    $progressText.text(`${processed} / ${total} (${percent}%)`);

                    // نمایش جزئیات
                    if (response.data.message) {
                        $ajaxOutput.append(response.data.message + '\n');
                        $ajaxResult.show();
                    }

                    if (response.data.completed) {
                        $status.html('<span style="color:#28a745;">✓ پردازش گروهی با موفقیت تکمیل شد!</span>');
                        $button.prop('disabled', false);
                    } else {
                        processBatch(offset + parseInt(iur_vars.bulk_limit));
                    }
                } else {
                    $status.html('<span style="color:#dc3545;">✗ ' + (response.data.message || 'خطا در پردازش') + '</span>');
                    $button.prop('disabled', false);
                }
            },
            error: function(xhr, textStatus) {
                let errorMessage = 'خطای سرور';
                if (textStatus === 'timeout') {
                    errorMessage = 'زمان درخواست به پایان رسید. مقدار timeout را افزایش دهید.';
                }
                $status.html('<span style="color:#dc3545;">✗ ' + errorMessage + '</span>');
                $button.prop('disabled', false);
            }
        });
    }

    // شروع پردازش
    processBatch();
});
});
