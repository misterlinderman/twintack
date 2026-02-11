/**
 * TwinTack Custom Grips — Mockup Upload (Drag & Drop)
 *
 * Handles drag-and-drop and click-to-upload for mockup files,
 * sends via AJAX FormData, shows progress, and updates the UI.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    var $zone       = $('#ttcg-upload-zone');
    var $dropzone   = $('#ttcg-dropzone');
    var $fileInput  = $('#ttcg-file-input');
    var $progress   = $('#ttcg-upload-progress');
    var $progressFill = $('#ttcg-upload-progress-fill');
    var $progressText = $('#ttcg-upload-progress-text');
    var $result     = $('#ttcg-upload-result');

    if (!$zone.length) return;

    var designId = $zone.data('design-id');

    // ------------------------------------------------------------------
    // Drag & Drop Events
    // ------------------------------------------------------------------

    $dropzone.on('dragenter dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('is-dragover');
    });

    $dropzone.on('dragleave drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('is-dragover');
    });

    $dropzone.on('drop', function (e) {
        var files = e.originalEvent.dataTransfer.files;
        if (files.length) {
            uploadFile(files[0]);
        }
    });

    // ------------------------------------------------------------------
    // Click to Upload
    // ------------------------------------------------------------------

    $dropzone.on('click', function (e) {
        // Don't trigger on the button label click (it already targets the input)
        if (!$(e.target).closest('label').length) {
            $fileInput.trigger('click');
        }
    });

    $fileInput.on('change', function () {
        if (this.files.length) {
            uploadFile(this.files[0]);
        }
    });

    // ------------------------------------------------------------------
    // Upload Handler
    // ------------------------------------------------------------------

    function uploadFile(file) {
        // Validate file type client-side
        var allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'
        ];

        if (allowedTypes.indexOf(file.type) === -1) {
            showResult('error', 'Invalid file type. Allowed: JPG, PNG, GIF, WebP, PDF.');
            return;
        }

        // Validate size (50MB)
        if (file.size > 52428800) {
            showResult('error', 'File is too large. Maximum size is 50 MB.');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'ttcg_upload_mockup');
        formData.append('nonce', ttcg.nonce);
        formData.append('design_id', designId);
        formData.append('mockup_file', file);

        // Show progress
        $dropzone.hide();
        $progress.show();
        $result.hide();
        $progressFill.css('width', '0%');
        $progressText.text(ttcg.strings.uploading);

        $.ajax({
            url:  ttcg.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function () {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        var pct = Math.round((e.loaded / e.total) * 100);
                        $progressFill.css('width', pct + '%');
                        $progressText.text(pct + '%');
                    }
                }, false);
                return xhr;
            }
        })
        .done(function (res) {
            $progress.hide();
            $dropzone.show();

            if (res.success) {
                showResult('success', ttcg.strings.upload_success);

                // Update the mockup display
                var $mockupDisplay = $('#ttcg-mockup-display');
                var $mockupEmpty   = $('#ttcg-mockup-empty');
                var $mockupImg     = $('#ttcg-mockup-img');

                if ($mockupImg.length) {
                    $mockupImg.attr('src', res.data.url);
                } else {
                    $mockupDisplay.html(
                        '<img src="' + res.data.url + '" alt="Mockup" class="ttcg-detail__mockup-img" id="ttcg-mockup-img">'
                    );
                }

                // Update status badge if it changed
                if (res.data.status_label) {
                    $('#ttcg-status-badge')
                        .text(res.data.status_label)
                        .css('background-color', res.data.status_color || '#5bc0de');
                }
            } else {
                showResult('error', res.data.message || ttcg.strings.error);
            }
        })
        .fail(function () {
            $progress.hide();
            $dropzone.show();
            showResult('error', ttcg.strings.error);
        });

        // Reset file input
        $fileInput.val('');
    }

    // ------------------------------------------------------------------
    // Result Display
    // ------------------------------------------------------------------

    function showResult(type, message) {
        var cls = type === 'success' ? 'ttcg-success-msg' : 'ttcg-error-msg';
        $result.html('<span class="' + cls + '">' + message + '</span>').show();

        setTimeout(function () {
            $result.fadeOut(300);
        }, 4000);
    }

})(jQuery);
