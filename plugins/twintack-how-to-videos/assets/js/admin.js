/**
 * TwinTack How-To Videos Admin JavaScript
 * 
 * @package TwinTackHowToVideos
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initVideoAssignmentPage();
        initMetaBoxes();
    });
    
    /**
     * Initialize video assignment page functionality
     */
    function initVideoAssignmentPage() {
        // Select all checkboxes functionality
        $('.twintack-htv-select-all').on('change', function() {
            const isChecked = $(this).prop('checked');
            const columnIndex = $(this).closest('th').index();
            
            $('tbody tr').each(function() {
                $(this).find('td').eq(columnIndex).find('input[type="checkbox"]').prop('checked', isChecked);
            });
        });
        
        // Form submission loading state
        $('form[method="post"]').on('submit', function() {
            $(this).find('input[type="submit"]').prop('disabled', true).val('Saving...');
            $(this).addClass('twintack-htv-loading');
        });
        
        // Highlight changed rows
        $('input[type="checkbox"]').on('change', function() {
            $(this).closest('tr').addClass('changed');
        });
    }
    
    /**
     * Initialize meta box functionality
     */
    function initMetaBoxes() {
        // Vimeo URL validation
        $('#htv_vimeo_url').on('blur', function() {
            const url = $(this).val();
            const vimeoRegex = /^https?:\/\/(www\.)?(vimeo\.com\/\d+)/;
            
            if (url && !vimeoRegex.test(url)) {
                $(this).addClass('error');
                showFieldError($(this), 'Please enter a valid Vimeo URL (e.g., https://vimeo.com/123456789)');
            } else {
                $(this).removeClass('error');
                hideFieldError($(this));
            }
        });
        
        // Duration format validation
        $('#htv_video_duration').on('blur', function() {
            const duration = $(this).val();
            const durationRegex = /^\d{1,2}:\d{2}$/;
            
            if (duration && !durationRegex.test(duration)) {
                $(this).addClass('error');
                showFieldError($(this), 'Please use MM:SS format (e.g., "2:30")');
            } else {
                $(this).removeClass('error');
                hideFieldError($(this));
            }
        });
    }
    
    /**
     * Show field error message
     */
    function showFieldError($field, message) {
        hideFieldError($field); // Remove existing error
        
        const $error = $('<div class="twintack-htv-field-error" style="color: #dc3232; font-size: 12px; margin-top: 5px;">' + message + '</div>');
        $field.after($error);
    }
    
    /**
     * Hide field error message
     */
    function hideFieldError($field) {
        $field.siblings('.twintack-htv-field-error').remove();
    }
    
    /**
     * Auto-save functionality for meta boxes
     */
    function initAutoSave() {
        let saveTimeout;
        
        $('#htv_vimeo_url, #htv_video_duration').on('input', function() {
            clearTimeout(saveTimeout);
            
            saveTimeout = setTimeout(function() {
                // Could implement auto-save functionality here
                console.log('Auto-save triggered');
            }, 2000);
        });
    }
    
})(jQuery); 