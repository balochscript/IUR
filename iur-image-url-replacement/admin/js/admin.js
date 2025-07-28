jQuery(document).ready(function($) {
    // ======= Show/hide API service fields ======= //
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

    // Initial run and change event
    toggleServiceFields();
    $('#iur-upload-method').on('change', toggleServiceFields);

    // ======= Clear errors ======= //
    $('#iur-clear-errors').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(iur_vars.i18n.confirmClearErrors)) return;
        
        const $button = $(this);
        const originalText = $button.text();
        $button.prop('disabled', true).text(iur_vars.i18n.clearing);

        $.post(iur_vars.ajaxurl, {
            action: 'iur_clear_errors',
            security: iur_vars.nonce
        }).done(function(response) {
            if (response?.success) {
                $('#iur-error-log').html('<p class="notice notice-success">' + iur_vars.i18n.errorsCleared + '</p>');
            } else {
                alert(iur_vars.i18n.error + ': ' + (response?.data?.message || iur_vars.i18n.invalidServerResponse));
            }
        }).always(() => {
            $button.prop('disabled', false).text(originalText);
        });
    });

    // ======= Toggle advanced settings ======= //
    $('.iur-toggle-advanced').on('click', function(e) {
        e.preventDefault();
        $('.iur-advanced-settings').stop(true, true).slideToggle();
    });
    
    // ======= Process single post ======= //
    $('.iur-process-post').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const post_id = $button.data('postid');
        const originalText = $button.text();
        
        $button.prop('disabled', true).text(iur_vars.i18n.processing);

        $.post(iur_vars.ajaxurl, {
            action: 'iur_process_single_post',
            security: iur_vars.nonce,
            post_id: post_id
        }).done(function(response) {
            if (response.success) {
                alert(iur_vars.i18n.postProcessed + ' ' + response.data.replaced);
                location.reload();
            } else {
                alert(iur_vars.i18n.error + ': ' + response.data.message);
            }
        }).fail(function() {
            alert(iur_vars.i18n.serverError);
        }).always(() => {
            $button.prop('disabled', false).text(originalText);
        });
    });

    // ======= Bulk process ======= //
    $('#iur_bulk_process').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $status = $('#iur_bulk_process_status');
        const $progressBar = $('#iur_progress_bar');
        const $progressFill = $('#iur_progress_bar_fill');
        const $progressText = $('#iur_progress_text');
        const $ajaxResult = $('#iur_ajax_result');
        const $ajaxOutput = $('#iur_ajax_output');

        // Initial state
        $button.prop('disabled', true);
        $status.html('<span class="spinner is-active"></span> ' + iur_vars.i18n.processing);
        $progressBar.show();
        $ajaxResult.hide();
        $ajaxOutput.empty();

        let processed = 0;
        let total = 0;
        let errors = 0;

        function processBatch(offset = 0) {
            $.ajax({
                url: iur_vars.ajaxurl,
                type: 'POST',
                dataType: 'json',
                timeout: parseInt(iur_vars.timeout) * 1000,
                data: {
                    action: 'iur_bulk_process',
                    offset: offset,
                    bulk_limit: iur_vars.bulk_limit,
                    security: iur_vars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        processed += response.data.processed;
                        total = response.data.total || total;
                        errors += response.data.errors || 0;

                        // Update progress
                        const percent = total > 0 ? Math.round((processed / total) * 100) : 0;
                        $progressFill.css('width', percent + '%');
                        $progressText.text(`${processed} / ${total} (${percent}%)`);

                        // Show details
                        if (response.data.message) {
                            $ajaxOutput.append(response.data.message + '\n');
                            $ajaxResult.show();
                        }

                        if (response.data.completed) {
                            $status.html('<span style="color:#28a745;">✓ ' + iur_vars.i18n.bulkCompleted + '</span>');
                            $button.prop('disabled', false);
                        } else {
                            processBatch(offset + parseInt(iur_vars.bulk_limit));
                        }
                    } else {
                        $status.html('<span style="color:#dc3545;">✗ ' + (response.data.message || iur_vars.i18n.bulkError) + '</span>');
                        $button.prop('disabled', false);
                    }
                },
                error: function(xhr, textStatus) {
                    let errorMessage = iur_vars.i18n.serverError;
                    if (textStatus === 'timeout') {
                        errorMessage = iur_vars.i18n.timeoutError;
                    }
                    $status.html('<span style="color:#dc3545;">✗ ' + errorMessage + '</span>');
                    $button.prop('disabled', false);
                }
            });
        }

        // Start processing
        processBatch();
    });
});
