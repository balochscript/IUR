jQuery(document).ready(function ($) {

    // Filter table rows
    $('#iur2-filter').on('keyup', function () {
        var value = $(this).val().toLowerCase();
        $('#iur2-table tbody tr').filter(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    // Select/Deselect all checkboxes
    $('#iur2-select-all').on('click', function () {
        $('.iur2-select').prop('checked', this.checked);
    });

    $('.iur2-select').on('click', function () {
        if (!this.checked) {
            $('#iur2-select-all').prop('checked', false);
        }
    });

    // Delete single row
    $('.iur2-delete').on('click', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        if (!confirm(iur_vars.i18n.confirmDelete)) return;

        var $row = $(this).closest('tr');
        $.post(iur_vars.ajaxurl, {
            action: 'iur2_delete',
            id: id,
            _wpnonce: iur_vars.nonce
        }, function (response) {
            if (response.success) {
                $row.fadeOut(300, function () {
                    $(this).remove();
                });
            } else {
                alert(iur_vars.i18n.deleteError + ' ' + (response.data || iur_vars.i18n.unknownError));
            }
        });
    });

    // Bulk delete
    $('#iur2-bulk-delete').on('click', function (e) {
        e.preventDefault();
        var ids = $('.iur2-select:checked').map(function () {
            return $(this).val();
        }).get();

        if (ids.length === 0) {
            alert(iur_vars.i18n.nothingSelected);
            return;
        }
        if (!confirm(iur_vars.i18n.confirmBulkDelete)) return;

        $.post(iur_vars.ajaxurl, {
            action: 'iur2_bulk_delete',
            ids: ids,
            _wpnonce: iur_vars.nonce
        }, function (response) {
            if (response.success) {
                ids.forEach(function (id) {
                    $('#iur2-row-' + id).fadeOut(300, function () {
                        $(this).remove();
                    });
                });
            } else {
                alert(iur_vars.i18n.bulkDeleteError + ' ' + (response.data || iur_vars.i18n.unknownError));
            }
        });
    });

    // Bulk process
    $('#iur2_bulk_process').on('click', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var $status = $('#iur2_bulk_process_status');
        $btn.prop('disabled', true);
        $status.text(iur_vars.i18n.processing);

        $.post(iur_vars.ajaxurl, {
            action: 'iur2_bulk_process',
            _wpnonce: iur_vars.nonce
        }, function (response) {
            $btn.prop('disabled', false);
            if (response.success) {
                $status.text(iur_vars.i18n.success);
            } else {
                $status.text(iur_vars.i18n.error + ' ' + (response.data || iur_vars.i18n.unknownError));
            }
        }).fail(function (jqXHR, textStatus) {
            $btn.prop('disabled', false);
            let msg = iur_vars.i18n.error;
            if (textStatus === 'timeout') msg = iur_vars.i18n.timeout;
            $status.text(msg);
        });
    });

    // Modal show
    $('.iur2-modal-open').on('click', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#iur2-modal-' + id).fadeIn(200);
    });

    // Modal close
    $('.iur2-modal-close').on('click', function (e) {
        e.preventDefault();
        $(this).closest('.iur2-modal').fadeOut(200);
    });

    // Single process
    $('.iur2-process').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var id = $btn.data('id');
        $btn.prop('disabled', true).text(iur_vars.i18n.processing);

        $.post(iur_vars.ajaxurl, {
            action: 'iur2_process',
            id: id,
            _wpnonce: iur_vars.nonce
        }, function (response) {
            $btn.prop('disabled', false).text(iur_vars.i18n.process);
            if (response.success) {
                $btn.closest('tr').find('.iur2-status').text(iur_vars.i18n.completed);
            } else {
                alert(iur_vars.i18n.processError + ' ' + (response.data || iur_vars.i18n.unknownError));
            }
        });
    });

    // Bulk select process
    $('#iur2-bulk-process').on('click', function (e) {
        e.preventDefault();
        var ids = $('.iur2-select:checked').map(function () {
            return $(this).val();
        }).get();

        if (ids.length === 0) {
            alert(iur_vars.i18n.nothingSelected);
            return;
        }

        if (!confirm(iur_vars.i18n.confirmBulkProcess)) return;

        var $btn = $(this);
        var $status = $('#iur2-bulk-process-status');
        $btn.prop('disabled', true);
        $status.text(iur_vars.i18n.processing);

        $.post(iur_vars.ajaxurl, {
            action: 'iur2_bulk_process_selected',
            ids: ids,
            _wpnonce: iur_vars.nonce
        }, function (response) {
            $btn.prop('disabled', false);
            if (response.success) {
                $status.text(iur_vars.i18n.success);
                ids.forEach(function (id) {
                    $('#iur2-row-' + id).find('.iur2-status').text(iur_vars.i18n.completed);
                });
            } else {
                $status.text(iur_vars.i18n.error + ' ' + (response.data || iur_vars.i18n.unknownError));
            }
        }).fail(function (jqXHR, textStatus) {
            $btn.prop('disabled', false);
            let msg = iur_vars.i18n.error;
            if (textStatus === 'timeout') msg = iur_vars.i18n.timeout;
            $status.text(msg);
        });
    });

    // Copy url
    $('.iur2-copy-url').on('click', function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        var $temp = $("<input>");
        $("body").append($temp);
        $temp.val(url).select();
        document.execCommand("copy");
        $temp.remove();
        alert(iur_vars.i18n.copied);
    });

    // Reset table filter
    $('#iur2-reset-filter').on('click', function (e) {
        e.preventDefault();
        $('#iur2-filter').val('');
        $('#iur2-table tbody tr').show();
    });
});
