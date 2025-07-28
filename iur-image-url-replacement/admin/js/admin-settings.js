jQuery(document).ready(function($) {
    // Show/hide API fields based on selected service
    function toggleApiFields() {
        const method = $('#iur-upload-method').val();
        $('.iur-service-field').hide();

        if (method === 'freeimage') {
            $('[data-service="freeimage"]').show();
        } else if (method === 'imgbb') {
            $('[data-service="imgbb"]').show();
        } else if (method === 'cloudinary') {
            $('[data-service="cloudinary"]').show();
        }
    }

    // Initial execution
    toggleApiFields();
    $('#iur-upload-method').change(toggleApiFields);

    // Handle settings reset
    $('#iur-reset-settings').on('click', function(e) {
        e.preventDefault();

        if (confirm(iurSettings.i18n.confirmReset)) {
            $.post(ajaxurl, {
                action: 'iur_reset_settings',
                _wpnonce: iurSettings.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error.'));
                }
            });
        }
    });
});
