jQuery(document).ready(function($) {
    'use strict';
    
    // Debug function
    function debugLog(message) {
        if (window.console && console.log) {
            console.log('Grip Feedback:', message);
        }
    }
    
    debugLog('Customer feedback script loaded');
    
    // Check if we have the necessary data
    if (typeof gripFeedback === 'undefined') {
        debugLog('Error: gripFeedback object not found');
        return;
    }
    
    // Function to get grip ID from multiple sources
    function getGripId() {
        var gripId = null;
        
        // Method 1: Check data attribute on container
        var container = $('.grip-design-container');
        if (container.length && container.data('grip-id')) {
            gripId = container.data('grip-id');
            debugLog('Found grip ID from data attribute: ' + gripId);
            return gripId;
        }
        
        // Method 2: Check URL parameters
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('grip_id')) {
            gripId = urlParams.get('grip_id');
            debugLog('Found grip ID from URL params: ' + gripId);
            return gripId;
        }
        
        // Method 3: Parse URL path (for pretty permalinks)
        var pathname = window.location.pathname;
        var pathParts = pathname.split('/');
        
        // Look for grip_id pattern in URL
        for (var i = 0; i < pathParts.length; i++) {
            if ((pathParts[i] === 'grip-designs' || pathParts[i] === 'my-custom-grips') && pathParts[i + 1]) {
                // Check if next part contains grip_id
                var nextPart = pathParts[i + 1];
                if (nextPart.includes('grip_id=')) {
                    gripId = nextPart.split('grip_id=')[1].split('&')[0];
                    debugLog('Found grip ID from URL path: ' + gripId);
                    return gripId;
                }
                // Check if it's just a numeric ID
                if (/^\d+$/.test(nextPart)) {
                    gripId = nextPart;
                    debugLog('Found numeric grip ID from URL path: ' + gripId);
                    return gripId;
                }
            }
        }
        
        debugLog('Could not find grip ID');
        return null;
    }
    
    // Function to reset button state
    function resetButtonState(button) {
        button.prop('disabled', false);
        button.find('.btn-text').show();
        button.find('.btn-loading').hide();
    }
    
    // Function to show loading state
    function showLoadingState(button) {
        button.prop('disabled', true);
        button.find('.btn-text').hide();
        button.find('.btn-loading').show();
    }
    
    // Function to show response message
    function showResponse(message, isSuccess) {
        var responseDiv = $('#feedback-response');
        var cssClass = isSuccess ? 'notice-success' : 'notice-error';
        var html = '<div class="notice ' + cssClass + '"><p>' + message + '</p></div>';
        
        responseDiv.html(html).show();
        
        // Scroll to response
        $('html, body').animate({
            scrollTop: responseDiv.offset().top - 100
        }, 300);
    }
    
    // Main click handler for feedback buttons
    $('#approve-design, #request-changes').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var action = button.attr('id') === 'approve-design' ? 'approve' : 'request_changes';
        var feedback = $('#customer-feedback').val() || '';
        var gripId = getGripId();
        
        debugLog('Button clicked: ' + action);
        debugLog('Feedback: ' + feedback);
        debugLog('Grip ID: ' + gripId);
        
        // Validate grip ID
        if (!gripId) {
            alert(gripFeedback.messages.noGripId);
            debugLog('Error: No grip ID found');
            return;
        }
        
        // Show loading state
        showLoadingState(button);
        
        // Make AJAX request
        $.ajax({
            url: gripFeedback.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'grip_customer_feedback',
                grip_id: gripId,
                feedback_action: action,
                feedback: feedback,
                security: gripFeedback.nonce
            },
            success: function(response) {
                debugLog('AJAX Success:', response);
                
                if (response.success) {
                    showResponse(response.data.message, true);
                    
                    // Reload page after delay to show updated status
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    var errorMessage = response.data || 'Unknown error occurred';
                    showResponse('Error: ' + errorMessage, false);
                    resetButtonState(button);
                }
            },
            error: function(xhr, status, error) {
                debugLog('AJAX Error:', {xhr: xhr, status: status, error: error});
                
                var errorMessage = gripFeedback.messages.connectionError;
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = 'Error: ' + xhr.responseJSON.data;
                }
                
                showResponse(errorMessage, false);
                resetButtonState(button);
            }
        });
    });
    
    // Initialize feedback form if it exists
    if ($('#customer-feedback-form').length) {
        debugLog('Feedback form found and initialized');
        
        // Show grip ID for debugging (remove in production)
        var gripId = getGripId();
        if (gripId) {
            debugLog('Current grip ID: ' + gripId);
        }
    }
}); 