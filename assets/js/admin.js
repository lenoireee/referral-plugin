
jQuery(document).ready(function($) {

    // ============================================
    // MODAL HANDLING
    // ============================================

    // Open modal for new category
    $('#srp-add-category').on('click', function(e) {
        e.preventDefault();
        $('#srp-category-form')[0].reset();
        $('#srp-cat-id').val('');
        $('#srp-modal-title').text('Add New Category');
        $('#srp-category-modal').show();
    });

    // Close modal
    $('.srp-modal-close, #srp-category-modal').on('click', function(e) {
        if (e.target === this || $(e.target).hasClass('srp-modal-close')) {
            $('#srp-category-modal').hide();
        }
    });

    // Prevent modal close when clicking inside content
    $('.srp-modal-content').on('click', function(e) {
        e.stopPropagation();
    });

    // ============================================
    // EDIT CATEGORY
    // ============================================

    $(document).on('click', '.srp-edit-category', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var id = $btn.data('id');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'srp_admin_action',
                srp_action: 'get_category',
                id: id,
                nonce: srpAdmin.nonce
            },
            beforeSend: function() {
                $btn.prop('disabled', true).text('Loading...');
            },
            success: function(response) {
                if (response.success) {
                    var cat = response.data;
                    $('#srp-cat-id').val(cat.id);
                    $('#srp-cat-name').val(cat.name);
                    $('#srp-cat-description').val(cat.description);
                    $('#srp-cat-referrer-percentage').val(cat.referrer_percentage);
                    $('#srp-cat-referee-discount').val(cat.referee_discount);
                    $('#srp-cat-min-purchase').val(cat.min_purchase_amount);
                    $('#srp-cat-max-earnings').val(cat.max_earnings || '');
                    $('#srp-cat-withdrawal-method').val(cat.withdrawal_method);
                    $('#srp-cat-min-withdrawal').val(cat.min_withdrawal);
                    $('#srp-cat-status').val(cat.status);

                    $('#srp-modal-title').text('Edit Category');
                    $('#srp-category-modal').show();
                } else {
                    alert(response.data.message || 'Error loading category');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Network error. Check console for details.');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Edit');
            }
        });
    });

    // ============================================
    // DELETE CATEGORY
    // ============================================

    $(document).on('click', '.srp-delete-category', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete this category? This cannot be undone.')) {
            return;
        }

        var id = $(this).data('id');
        var $row = $(this).closest('tr');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'srp_admin_action',
                srp_action: 'delete_category',
                id: id,
                nonce: srpAdmin.nonce
            },
            beforeSend: function() {
                $row.css('opacity', '0.5');
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        if ($('.srp-admin tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(response.data.message || 'Error deleting category');
                    $row.css('opacity', '1');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Network error. Check console for details.');
                $row.css('opacity', '1');
            }
        });
    });

    // ============================================
    // SAVE CATEGORY (CREATE / UPDATE)
    // ============================================

    $('#srp-category-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');

        // Build form data manually to ensure all fields are captured
        var formData = {
            action: 'srp_admin_action',
            srp_action: 'save_category',
            nonce: srpAdmin.nonce,
            id: $('#srp-cat-id').val(),
            name: $('#srp-cat-name').val(),
            description: $('#srp-cat-description').val(),
            referrer_percentage: $('#srp-cat-referrer-percentage').val(),
            referee_discount: $('#srp-cat-referee-discount').val(),
            min_purchase: $('#srp-cat-min-purchase').val(),
            max_earnings: $('#srp-cat-max-earnings').val(),
            withdrawal_method: $('#srp-cat-withdrawal-method').val(),
            min_withdrawal: $('#srp-cat-min-withdrawal').val(),
            status: $('#srp-cat-status').val()
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: formData,
            beforeSend: function() {
                $btn.prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    $('#srp-category-modal').hide();
                    alert(response.data.message || 'Category saved successfully!');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error saving category');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                alert('Network error. Check console for details.');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Save Category');
            }
        });
    });

    // ============================================
    // PROCESS WITHDRAWAL
    // ============================================

    $(document).on('click', '.srp-process-withdrawal', function(e) {
        e.preventDefault();

        var id = $(this).data('id');
        var status = $(this).data('status');
        var actionText = status === 'completed' ? 'approve' : 'reject';

        if (!confirm('Are you sure you want to ' + actionText + ' this withdrawal?')) {
            return;
        }

        var notes = prompt('Add admin notes (optional):', '');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'srp_admin_action',
                srp_action: 'process_withdrawal',
                withdrawal_id: id,
                status: status,
                notes: notes || '',
                nonce: srpAdmin.nonce
            },
            beforeSend: function() {
                $(this).prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || 'Withdrawal ' + status);
                    location.reload();
                } else {
                    alert(response.data.message || 'Error processing withdrawal');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Network error. Check console for details.');
            }
        });
    });

    // ============================================
    // ESCAPE KEY TO CLOSE MODAL
    // ============================================

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#srp-category-modal').hide();
        }
    });

});
