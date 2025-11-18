/**
 * Klaviyo Newsletter Integration
 * Handles form submission to Klaviyo email list via their official form handler
 */
(function($) {
  'use strict';

  // When DOM is fully loaded
  $(document).ready(function() {
    // Debug info
    console.log('Klaviyo newsletter script loaded');
    
    // Select the newsletter form
    const newsletterForm = $('#email_signup');
    const messageContainer = $('.klaviyo-form-message');
    
    // If form doesn't exist, exit early
    if (newsletterForm.length === 0) {
      console.error('Newsletter form not found in DOM');
      return;
    }
    
    console.log('Newsletter form found:', newsletterForm);
    
    // Handle form submission
    newsletterForm.on('submit', function(e) {
      e.preventDefault();
      
      console.log('Form submitted, preventing default');
      
      // Clear previous messages
      messageContainer.html('');
      
      // Get form elements
      const emailInput = $('#k_id_email');
      const submitButton = $('#klaviyo_submit');
      
      // Show loading state
      submitButton.prop('disabled', true).text('Submitting...');
      
      // Get the email value
      const email = emailInput.val();
      console.log('Email value:', email);
      
      // Validate email on the client side
      if (!email || !validateEmail(email)) {
        messageContainer.html('<p class="error-message">Please enter a valid email address.</p>');
        submitButton.prop('disabled', false).text('Subscribe');
        return;
      }
      
      // Get the list ID from the form
      const listId = newsletterForm.find('input[name="g"]').val();
      
      // Submit to Klaviyo directly
      $.ajax({
        url: 'https://manage.kmail-lists.com/ajax/subscriptions/subscribe',
        type: 'POST',
        data: {
          g: listId,
          email: email,
          $source: 'TwinTack Website Newsletter'
        },
        dataType: 'json',
        success: function(response) {
          console.log('Klaviyo response:', response);
          if (response.success) {
            messageContainer.html('<p class="success-message">Thank you for subscribing!</p>');
            emailInput.val(''); // Clear the input
          } else {
            messageContainer.html('<p class="error-message">' + (response.message || 'Error subscribing. Please try again.') + '</p>');
          }
          submitButton.prop('disabled', false).text('Subscribe');
        },
        error: function(xhr, status, error) {
          console.error('Klaviyo submission error:', status, error);
          console.error('Response:', xhr.responseText);
          
          // Try to parse the response
          try {
            const response = JSON.parse(xhr.responseText);
            if (response && response.message) {
              messageContainer.html('<p class="error-message">' + response.message + '</p>');
            } else {
              messageContainer.html('<p class="error-message">Sorry, there was an error. Please try again.</p>');
            }
          } catch(e) {
            messageContainer.html('<p class="error-message">Sorry, there was an error. Please try again.</p>');
          }
          
          submitButton.prop('disabled', false).text('Subscribe');
        }
      });
    });
    
    // Helper function to validate email
    function validateEmail(email) {
      const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return regex.test(email);
    }
  });
})(jQuery); 