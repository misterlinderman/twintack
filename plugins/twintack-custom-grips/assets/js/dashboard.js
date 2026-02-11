/**
 * TwinTack Custom Grips — Dashboard Interactions
 *
 * Handles sidebar toggle, sort selector, row clicking,
 * status changes, and inline detail editing.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    // ------------------------------------------------------------------
    // Sidebar Toggle (mobile)
    // ------------------------------------------------------------------

    var $sidebar = $('#ttcg-sidebar');
    var $toggle  = $('#ttcg-sidebar-toggle');

    $toggle.on('click', function () {
        $sidebar.toggleClass('is-open');
    });

    // Close sidebar when clicking outside on mobile
    $(document).on('click', function (e) {
        if (
            $sidebar.hasClass('is-open') &&
            !$(e.target).closest('#ttcg-sidebar, #ttcg-sidebar-toggle').length
        ) {
            $sidebar.removeClass('is-open');
        }
    });

    // ------------------------------------------------------------------
    // Sort Selector
    // ------------------------------------------------------------------

    $('#ttcg-sort').on('change', function () {
        var val     = $(this).val().split('-');
        var baseUrl = $(this).data('base-url');
        var params  = new URLSearchParams(window.location.search);

        params.set('orderby', val[0]);
        params.set('order', val[1]);
        params.delete('paged'); // Reset to page 1

        window.location.href = baseUrl + '?' + params.toString();
    });

    // ------------------------------------------------------------------
    // Clickable Table Rows
    // ------------------------------------------------------------------

    $(document).on('click', '.ttcg-list__row', function (e) {
        // Don't navigate if clicking a link inside the row
        if ($(e.target).closest('a').length) {
            return;
        }
        var href = $(this).data('href');
        if (href) {
            window.location.href = href;
        }
    });

    // ------------------------------------------------------------------
    // Status Update
    // ------------------------------------------------------------------

    var $statusSelect = $('#ttcg-status-select');
    var $statusSave   = $('#ttcg-status-save');
    var $statusFeedback = $('#ttcg-status-feedback');

    $statusSelect.on('change', function () {
        var val = $(this).val();
        $statusSave.prop('disabled', !val);
    });

    $statusSave.on('click', function () {
        var newStatus = $statusSelect.val();
        if (!newStatus) return;

        if (!confirm(ttcg.strings.confirm_status)) return;

        var designId = $('.ttcg-detail').data('design-id') ||
                       $('.ttcg-status-controls').data('design-id');

        $statusSave.prop('disabled', true).text(ttcg.strings.saving);
        $statusFeedback.html('');

        $.post(ttcg.ajax_url, {
            action:    'ttcg_update_status',
            nonce:     ttcg.nonce,
            design_id: designId,
            status:    newStatus
        })
        .done(function (res) {
            if (res.success) {
                $statusFeedback.html('<span class="ttcg-success-msg">' + res.data.message + '</span>');

                // Update the badge in the header
                $('#ttcg-status-badge')
                    .text(res.data.status_label)
                    .css('background-color', res.data.status_color);

                $statusSave.text(ttcg.strings.saved);

                setTimeout(function () {
                    $statusSave.text('Update Status').prop('disabled', true);
                    $statusSelect.val('');
                    $statusFeedback.html('');
                }, 2000);
            } else {
                $statusFeedback.html('<span class="ttcg-error-msg">' + (res.data.message || ttcg.strings.error) + '</span>');
                $statusSave.prop('disabled', false).text('Update Status');
            }
        })
        .fail(function () {
            $statusFeedback.html('<span class="ttcg-error-msg">' + ttcg.strings.error + '</span>');
            $statusSave.prop('disabled', false).text('Update Status');
        });
    });

    // ------------------------------------------------------------------
    // Inline Edit Toggle
    // ------------------------------------------------------------------

    var $infoView = $('#ttcg-info-view');
    var $editView = $('#ttcg-edit-view');
    var $toggleEdit = $('#ttcg-toggle-edit');
    var $cancelEdit = $('#ttcg-cancel-edit');

    $toggleEdit.on('click', function () {
        $infoView.hide();
        $editView.show();
        $(this).hide();
    });

    $cancelEdit.on('click', function () {
        $editView.hide();
        $infoView.show();
        $toggleEdit.show();
    });

    // ------------------------------------------------------------------
    // Save Design Details
    // ------------------------------------------------------------------

    $('#ttcg-edit-form').on('submit', function (e) {
        e.preventDefault();

        var designId = $(this).data('design-id');
        var $saveBtn = $('#ttcg-save-details');
        var $feedback = $('#ttcg-edit-feedback');
        var formData = $(this).serializeArray();

        var data = {
            action:    'ttcg_update_design_details',
            nonce:     ttcg.nonce,
            design_id: designId
        };

        // Add form fields to data
        formData.forEach(function (item) {
            data[item.name] = item.value;
        });

        $saveBtn.prop('disabled', true).text(ttcg.strings.saving);
        $feedback.html('');

        $.post(ttcg.ajax_url, data)
        .done(function (res) {
            if (res.success) {
                $feedback.html('<span class="ttcg-success-msg">' + res.data.message + '</span>');

                // Update the read-only display values
                if (res.data.updated) {
                    res.data.updated.forEach(function (field) {
                        var val = data[field] || '';
                        var $display = $('#ttcg-display-' + field);
                        if ($display.length) {
                            $display.text(val);
                        }
                    });
                }

                setTimeout(function () {
                    $editView.hide();
                    $infoView.show();
                    $toggleEdit.show();
                    $feedback.html('');
                }, 1500);
            } else {
                $feedback.html('<span class="ttcg-error-msg">' + (res.data.message || ttcg.strings.error) + '</span>');
            }
            $saveBtn.prop('disabled', false).text('Save Changes');
        })
        .fail(function () {
            $feedback.html('<span class="ttcg-error-msg">' + ttcg.strings.error + '</span>');
            $saveBtn.prop('disabled', false).text('Save Changes');
        });
    });

    // ------------------------------------------------------------------
    // Create New Grip Design
    // ------------------------------------------------------------------

    $('#ttcg-new-design-form').on('submit', function (e) {
        e.preventDefault();

        var $form     = $(this);
        var $btn      = $('#ttcg-create-design');
        var $feedback = $('#ttcg-new-design-feedback');

        // Build FormData to support file upload
        var formData = new FormData(this);
        formData.append('action', 'ttcg_create_design');
        formData.append('nonce', ttcg.nonce);

        $btn.prop('disabled', true).text('Creating…');
        $feedback.html('');

        $.ajax({
            url:  ttcg.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false
        })
        .done(function (res) {
            if (res.success) {
                $feedback.html('<span class="ttcg-success-msg">' + res.data.message + '</span>');

                // Redirect to the new design detail page
                setTimeout(function () {
                    window.location.href = res.data.url;
                }, 800);
            } else {
                $feedback.html('<span class="ttcg-error-msg">' + (res.data.message || ttcg.strings.error) + '</span>');
                $btn.prop('disabled', false).html(
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Create Grip Design'
                );
            }
        })
        .fail(function () {
            $feedback.html('<span class="ttcg-error-msg">' + ttcg.strings.error + '</span>');
            $btn.prop('disabled', false).html(
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Create Grip Design'
            );
        });
    });

    // ------------------------------------------------------------------
    // Delete Grip Design
    // ------------------------------------------------------------------

    $('#ttcg-delete-design').on('click', function () {
        var designId    = $(this).data('design-id');
        var designTitle = $(this).data('design-title');
        var $btn        = $(this);

        if (!confirm('Are you sure you want to delete "' + designTitle + '"?\n\nThis will move it to the trash.')) {
            return;
        }

        $btn.prop('disabled', true).text('Deleting…');

        $.post(ttcg.ajax_url, {
            action:    'ttcg_delete_design',
            nonce:     ttcg.nonce,
            design_id: designId
        })
        .done(function (res) {
            if (res.success) {
                // Redirect back to the list
                window.location.href = res.data.redirect_url;
            } else {
                alert(res.data.message || ttcg.strings.error);
                $btn.prop('disabled', false).html(
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg> Delete'
                );
            }
        })
        .fail(function () {
            alert(ttcg.strings.error);
            $btn.prop('disabled', false).html(
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg> Delete'
            );
        });
    });

})(jQuery);
