/**
 * Password reset form validation
 */
jQuery(document).ready(function($) {
    console.log('Password reset script loaded');
    
    // Debug URL parameters
    var urlParams = new URLSearchParams(window.location.search);
    console.log('Current URL:', window.location.href);
    console.log('Search params:', window.location.search);
    console.log('Action:', urlParams.get('action'));
    console.log('Key:', urlParams.get('key'));
    console.log('Login:', urlParams.get('login'));
    
    // Add a helper to make the form more visible
    $('.twintack-reset-password-form').css('border', '2px solid blue').prepend(
        '<div style="background: #eef; padding: 10px; margin-bottom: 15px;">' +
        '<p><strong>Password Reset Form</strong></p>' +
        '<p>Key: ' + urlParams.get('key') + '</p>' +
        '<p>Login: ' + urlParams.get('login') + '</p>' +
        '</div>'
    );
    
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
        
        // Update strength indicator
        switch(strength) {
            case 0:
            case 1:
                strengthIndicator.html('Very Weak').css('color', 'red');
                break;
            case 2:
                strengthIndicator.html('Weak').css('color', 'orange');
                break;
            case 3:
                strengthIndicator.html('Medium').css('color', 'yellow');
                break;
            case 4:
                strengthIndicator.html('Strong').css('color', 'green');
                break;
            case 5:
                strengthIndicator.html('Very Strong').css('color', 'darkgreen');
                break;
        }
    });
    
    // Check passwords match
    $('.reset-password').on('keyup', '#password_2', function() {
        var password1 = $('#password_1').val();
        var password2 = $(this).val();
        
        var matchIndicator = $('.password-match');
        if (matchIndicator.length === 0) {
            $(this).after('<div class="password-match"></div>');
            matchIndicator = $('.password-match');
        }
        
        if (password1 === password2) {
            matchIndicator.html('Passwords match').css('color', 'green');
        } else {
            matchIndicator.html('Passwords do not match').css('color', 'red');
        }
    });
    
    // Form submission validation
    $('.reset-password').on('submit', function(e) {
        var password1 = $('#password_1').val();
        var password2 = $('#password_2').val();
        var valid = true;
        
        // Clear previous errors
        $('.form-error').remove();
        
        // Validate password
        if (password1.length < 8) {
            $('#password_1').after('<div class="form-error" style="color: red;">Password must be at least 8 characters</div>');
            valid = false;
        }
        
        // Check passwords match
        if (password1 !== password2) {
            $('#password_2').after('<div class="form-error" style="color: red;">Passwords do not match</div>');
            valid = false;
        }
        
        // If validation fails, prevent form submission
        if (!valid) {
            e.preventDefault();
            return false;
        }
        
        console.log('Password reset form submitted');
        return true;
    });
}); 