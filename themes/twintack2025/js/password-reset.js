/**
 * Password reset form validation
 */
jQuery(document).ready(function($) {
    // Debug URL parameters (console only)
    var urlParams = new URLSearchParams(window.location.search);
    console.log('Password reset script loaded');
    
    // Password strength meter
    $('.reset-password').on('keyup', '#password_1', function() {
        var password = $(this).val();
        var strength = 0;
        
        // Check password strength
        if (password.length >= 8) {
            strength += 1;
        }
        if (password.match(/[a-z]+/)) {
            strength += 1;
        }
        if (password.match(/[A-Z]+/)) {
            strength += 1;
        }
        if (password.match(/[0-9]+/)) {
            strength += 1;
        }
        if (password.match(/[$@#&!]+/)) {
            strength += 1;
        }
        
        // Show strength indicator
        var strengthIndicator = $('.password-strength');
        if (strengthIndicator.length === 0) {
            $(this).after('<div class="password-strength"></div>');
            strengthIndicator = $('.password-strength');
        }
        
        // Update strength display
        switch(strength) {
            case 0:
            case 1:
                strengthIndicator.html('<span style="color: red;">Weak</span>');
                break;
            case 2:
            case 3:
                strengthIndicator.html('<span style="color: orange;">Medium</span>');
                break;
            case 4:
            case 5:
                strengthIndicator.html('<span style="color: green;">Strong</span>');
                break;
        }
    });
    
    // Password confirmation validation
    $('.reset-password').on('keyup', '#password_2', function() {
        var password1 = $('#password_1').val();
        var password2 = $(this).val();
        
        var confirmationIndicator = $('.password-confirmation');
        if (confirmationIndicator.length === 0) {
            $(this).after('<div class="password-confirmation"></div>');
            confirmationIndicator = $('.password-confirmation');
        }
        
        if (password2.length > 0) {
            if (password1 === password2) {
                confirmationIndicator.html('<span style="color: green;">Passwords match</span>');
            } else {
                confirmationIndicator.html('<span style="color: red;">Passwords do not match</span>');
            }
        } else {
            confirmationIndicator.html('');
        }
    });
    
    // Form submission validation
    $('.reset-password').on('submit', function(e) {
        var password1 = $('#password_1').val();
        var password2 = $('#password_2').val();
        
        if (password1.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters long.');
            return false;
        }
        
        if (password1 !== password2) {
            e.preventDefault();
            alert('Passwords do not match.');
            return false;
        }
    });
    
    // Add some basic styling for password indicators
    $('<style>')
        .prop("type", "text/css")
        .html(`
            .password-strength, .password-confirmation {
                font-size: 12px;
                margin-top: 5px;
                font-weight: bold;
            }
        `)
        .appendTo("head");
});

// Utility function to get URL parameters
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

// Additional debugging for password reset issues
if (window.location.search.indexOf('action=resetpass') !== -1 || 
    window.location.search.indexOf('action=rp') !== -1) {
    console.log('Password reset page detected');
    
    // Check if the form is present
    jQuery(document).ready(function($) {
        if ($('.reset-password').length === 0) {
            console.error('Reset password form not found');
        } else {
            console.log('Reset password form found');
        }
    });
}

// Debug form submission
jQuery(document).ready(function($) {
    $('.reset-password').on('submit', function() {
        console.log('Password reset form submitted');
    });
}); 