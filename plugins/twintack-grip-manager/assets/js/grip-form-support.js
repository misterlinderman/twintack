/**
 * TwinTack Grip Form Support
 * Safe JavaScript for Gravity Forms 2.9 Image Choice field conditional logic
 * Avoids output buffering conflicts by using proper WordPress script enqueuing
 */
(function($) {
    'use strict';
    
    // Wait for DOM and ensure jQuery is available
    $(document).ready(function() {
        
        // Configuration from WordPress
        var config = window.twintackGripConfig || {};
        var formId = config.formId || 9;
        var debug = config.debug || false;
        
        function log(message) {
            if (debug && console && console.log) {
                console.log('TwinTack Grip Form: ' + message);
            }
        }
        
        log('Initializing grip form support for form ID: ' + formId);
        
        // First, check if any Gravity Forms are present on the page
        setTimeout(function() {
            var allForms = $('.gform_wrapper');
            var gravityForms = $('form[id^="gform_"]');
            
            log('Page scan results:');
            log('  .gform_wrapper elements: ' + allForms.length);
            log('  form[id^="gform_"] elements: ' + gravityForms.length);
            
            if (allForms.length === 0 && gravityForms.length === 0) {
                log('ERROR: No Gravity Forms detected on page!');
                log('This suggests the [gravityform] shortcode is not rendering.');
                
                // Check if shortcode exists in page content
                var pageContent = $('body').html();
                if (pageContent.indexOf('[gravityform') !== -1) {
                    log('Found [gravityform shortcode in page content - shortcode not processed');
                } else if (pageContent.indexOf('gravityform') !== -1) {
                    log('Found "gravityform" text in page - investigating...');
                } else {
                    log('No gravityform references found in page content');
                }
            }
            
            allForms.each(function(index) {
                log('  Gravity Form wrapper ' + index + ': ' + $(this).attr('class'));
                var form = $(this).find('form');
                if (form.length) {
                    log('    Form ID: ' + form.attr('id'));
                } else {
                    log('    No form element found inside wrapper');
                }
            });
        }, 500);
        
        // Create robust gform object if needed (GF 2.9.18+ compatibility)
        if (typeof window.gform === 'undefined') {
            window.gform = {};
        }
        
        // Ensure all required methods exist (fixes applyFilters errors)
        var requiredMethods = {
            addAction: function() {},
            addFilter: function() {},
            applyFilters: function(filter, value) { 
                log('gform.applyFilters called: ' + filter);
                return value; 
            },
            doAction: function() {},
            hooks: {},
            addHook: function() {},
            removeHook: function() {},
            doHook: function() {},
            initializeOnLoaded: function(callback) {
                if (typeof callback === 'function') {
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', callback);
                    } else {
                        callback();
                    }
                }
                log('gform.initializeOnLoaded called');
            }
        };
        
        // Add missing methods to existing gform object
        for (var method in requiredMethods) {
            if (typeof window.gform[method] !== 'function') {
                window.gform[method] = requiredMethods[method];
            }
        }
        
        log('Ensured gform object has all required methods');
        
        // Check if user is logged in before applying form fixes
        var isLoggedIn = 
            document.body.classList.contains('logged-in') || 
            document.querySelector('.admin-bar') !== null ||
            document.querySelector('#wpadminbar') !== null ||
            document.querySelector('[href*="wp-login.php?action=logout"]') !== null ||
            document.querySelector('[href*="logout"]') !== null;
        
        if (isLoggedIn) {
            log('User is logged in - applying form visibility fixes');
            
            // ULTRA-TARGETED: Only prevent the main wrapper from being hidden - nothing else!
            var proactiveStyleSheet = document.createElement('style');
            proactiveStyleSheet.id = 'twintack-grip-form-fix';
            proactiveStyleSheet.textContent = `
                /* ONLY prevent the main wrapper from being hidden - no child elements! */
                .gform_wrapper#gform_wrapper_9 {
                    display: block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                }
                /* DO NOT touch .gform_body or any child elements - let GF control everything inside */
            `;
            document.head.appendChild(proactiveStyleSheet);
            log('Applied ultra-targeted CSS - wrapper only, no child elements');
        } else {
            log('User not logged in - skipping form visibility fixes (form should be hidden)');
        }
        
        // Add MutationObserver to watch for any attempts to hide the form (only for logged-in users)
        if (isLoggedIn) {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                        var target = mutation.target;
                        // ONLY protect the main wrapper - never touch child elements!
                        if (target.id === 'gform_wrapper_9') {
                            var display = window.getComputedStyle(target).display;
                            if (display === 'none') {
                                log('BLOCKED: Something tried to hide main form wrapper');
                                target.style.display = 'block';
                            }
                        }
                        // DO NOT protect .gform_body, fields, or any other child elements
                        // Let Gravity Forms control conditional logic completely
                    }
                });
            });
            
            // Start observing once the form loads
            setTimeout(function() {
                var formWrapper = document.getElementById('gform_wrapper_9');
                if (formWrapper) {
                    observer.observe(formWrapper, {
                        attributes: true,
                        attributeFilter: ['style'],
                        subtree: true
                    });
                    log('Started monitoring form for hiding attempts');
                }
            }, 1000);
        }
        
        // Wait for Gravity Form to actually render before initializing
        function waitForForm() {
            var attempts = 0;
            var maxAttempts = 20; // Wait up to 10 seconds (20 * 500ms)
            
            function checkForForm() {
                attempts++;
                var formExists = $('#gform_' + formId).length > 0;
                
                log('Attempt ' + attempts + ': Checking for form #gform_' + formId + ' - Found: ' + formExists);
                
                if (formExists) {
                    log('Form found! Initializing conditional logic...');
                    initializeConditionalLogic();
                } else if (attempts < maxAttempts) {
                    log('Form not found yet, waiting 500ms before retry...');
                    setTimeout(checkForForm, 500);
                } else {
                    log('ERROR: Form not found after ' + (maxAttempts * 500) + 'ms. Giving up.');
                }
            }
            
            checkForForm();
        }
        
        // Start waiting for form
        waitForForm();
        
        // Use GF 2.9 post_init event if available (as backup)
        var formInitialized = false;
        $(document).on('gform/post_init', function(event, gformId) {
            if (gformId == formId && !formInitialized) {
                log('GF 2.9 post_init event fired for form ' + formId + ' - initializing via event');
                formInitialized = true;
                initializeConditionalLogic();
            }
        });
        
        function initializeConditionalLogic() {
            if (formInitialized) {
                log('Form already initialized, skipping...');
                return;
            }
            formInitialized = true;
            log('Initializing conditional logic for Image Choice fields');
            
            // Debug: Log the form structure to understand what we're working with
            setTimeout(function() {
                var form = $('.gform_wrapper form#gform_' + formId);
                if (form.length) {
                    log('Found form with ID: gform_' + formId);
                    
                    // Look for Image Choice fields
                    var imageChoiceFields = form.find('.gfield--type-image_choice');
                    log('Found ' + imageChoiceFields.length + ' Image Choice fields');
                    
                    imageChoiceFields.each(function(index) {
                        var fieldId = $(this).attr('id');
                        var inputs = $(this).find('input[type="radio"]');
                        log('Image Choice field ' + index + ': ' + fieldId + ' with ' + inputs.length + ' inputs');
                        
                        // Log input names and values for debugging
                        inputs.each(function() {
                            log('  Input name: ' + this.name + ', value: ' + this.value + ', checked: ' + this.checked);
                        });
                    });
                } else {
                    log('ERROR: Could not find form with ID gform_' + formId);
                    
                    // Fallback: look for any gravity forms
                    var anyForms = $('.gform_wrapper form');
                    log('Found ' + anyForms.length + ' Gravity Forms on page');
                    anyForms.each(function() {
                        log('  Form ID: ' + this.id);
                    });
                }
            }, 1000);
            
            // Handle Image Choice field changes - ONLY for pattern field (input_12)
            $(document).off('change.twintack', 'input[name="input_12"]')
                      .on('change.twintack', 'input[name="input_12"]', function() {
                if (this.checked) {
                    var selectedValue = this.value;
                    var fieldName = this.name;
                    log('Pattern selection changed - Field: ' + fieldName + ', Value: "' + selectedValue + '"');
                    
                    // Log what should happen based on the form export
                    switch(selectedValue) {
                        case 'Solid Color':
                            log('Should show: Product Color (field 41) only');
                            break;
                        case '2-Color Fade':
                            log('Should show: Product Color (field 41) + Second Color (field 42)');
                            break;
                        case '3-Color Fade':
                            log('Should show: Product Color (field 41) + Second Color (field 42) + Third Color (field 43)');
                            break;
                        case 'Splatter':
                            log('Should show: Product Color (field 41) + Second Color (field 42)');
                            break;
                        default:
                            log('WARNING: Unexpected pattern value: "' + selectedValue + '" - This might be the issue!');
                    }
                    
                    // Apply conditional logic after a brief delay - use CSS-based fallback since GF is broken
                    setTimeout(function() {
                        // Apply CSS-based conditional logic since Gravity Forms is not working
                        log('Pattern changed - applying CSS-based conditional logic fallback');
                        applyConditionalLogicCSS(selectedValue);
                        
                        // Log field states for debugging
                        logFieldStates();
                        
                        // Check field visibility after applying rules
                        setTimeout(function() {
                            var field41 = $('#field_9_41');
                            var field42 = $('#field_9_42');
                            var field43 = $('#field_9_43');
                            
                            log('After conditional logic - Field 41 (Product Color) visible: ' + field41.is(':visible'));
                            log('After conditional logic - Field 42 (Second Color) visible: ' + field42.is(':visible'));
                            log('After conditional logic - Field 43 (Third Color) visible: ' + field43.is(':visible'));
                        }, 200);
                    }, 100);
                }
            });
            
            // Also handle clicks on Pattern field (input_12) labels/images directly
            $(document).off('click.twintack', '#field_9_12 label, #field_9_12 .gfield-choice-image')
                      .on('click.twintack', '#field_9_12 label, #field_9_12 .gfield-choice-image', function(e) {
                var input = $(this).closest('.gchoice').find('input[type="radio"]');
                if (input.length && !input.prop('checked')) {
                    log('Pattern field clicked - triggering input: ' + input.attr('name') + ' = ' + input.val());
                    input.prop('checked', true).trigger('change');
                }
            });
            
            // Check for pre-selected patterns on page load and ensure form is visible
            setTimeout(function() {
                var checkedInputs = $('.gfield--type-image_choice input[type="radio"]:checked');
                if (checkedInputs.length) {
                    log('Found ' + checkedInputs.length + ' pre-selected Image Choice inputs');
                    checkedInputs.each(function() {
                        log('  Pre-selected: ' + this.name + ' = ' + this.value);
                    });
                } else {
                    log('No pre-selected patterns found - this may be why form appears blank');
                    
                // Let Gravity Forms handle ALL field visibility - don't force anything
                log('Letting Gravity Forms handle all field visibility naturally');
                }
                
                // Gravity Forms conditional logic appears to be broken - apply CSS-based conditional logic as fallback
                log('Gravity Forms conditional logic is not working - applying CSS-based fallback');
                
                // Apply conditional logic via CSS based on current selection
                var checkedPattern = $('input[name="input_12"]:checked').val();
                log('Current pattern selection: "' + checkedPattern + '"');
                
                if (checkedPattern) {
                    applyConditionalLogicCSS(checkedPattern);
                } else {
                    // No pattern selected - hide all color fields
                    log('No pattern selected - hiding all color fields');
                    $('#field_9_41, #field_9_42, #field_9_43').hide();
                }
                
                logFieldStates();
                
                // Also log visibility of key fields and investigate parent containers
                setTimeout(function() {
                    var keyFields = ['field_9_6', 'field_9_12', 'field_9_41', 'field_9_42', 'field_9_43'];
                    keyFields.forEach(function(fieldId) {
                        var field = $('#' + fieldId);
                        if (field.length) {
                            var isVisible = field.is(':visible');
                            var display = field.css('display');
                            var visibility = field.css('visibility');
                            var opacity = field.css('opacity');
                            log('Field ' + fieldId + ' visibility check: visible=' + isVisible + ', display=' + display + ', visibility=' + visibility + ', opacity=' + opacity);
                            
                            // Check parent containers to find what's hiding the field
                            if (!isVisible && display !== 'none') {
                                log('Investigating why ' + fieldId + ' is not visible despite display=' + display);
                                var parents = field.parents();
                                parents.each(function(index) {
                                    var $parent = $(this);
                                    var parentDisplay = $parent.css('display');
                                    var parentVisibility = $parent.css('visibility');
                                    var parentOpacity = $parent.css('opacity');
                                    var parentHeight = $parent.height();
                                    var parentWidth = $parent.width();
                                    var parentClass = $parent.attr('class') || 'no-class';
                                    var parentId = $parent.attr('id') || 'no-id';
                                    
                                    if (parentDisplay === 'none' || parentVisibility === 'hidden' || parentOpacity == '0' || parentHeight === 0 || parentWidth === 0) {
                                        log('  HIDING PARENT ' + index + ': ' + parentClass + ' (id: ' + parentId + ') - display=' + parentDisplay + ', visibility=' + parentVisibility + ', opacity=' + parentOpacity + ', height=' + parentHeight + ', width=' + parentWidth);
                                    }
                                });
                            }
                        } else {
                            log('Field ' + fieldId + ' not found in DOM');
                        }
                    });
                    
                    // Also check the main form container
                    var formContainer = $('.gform_wrapper');
                    if (formContainer.length) {
                        var containerVisible = formContainer.is(':visible');
                        var containerDisplay = formContainer.css('display');
                        var containerHeight = formContainer.height();
                        var containerWidth = formContainer.width();
                        log('Form container (.gform_wrapper) - visible=' + containerVisible + ', display=' + containerDisplay + ', height=' + containerHeight + ', width=' + containerWidth);
                        
                        if (!containerVisible || containerHeight === 0 || containerWidth === 0) {
                            log('PROBLEM: Form container is not properly visible!');
                            
                            // Check if user is logged in before applying aggressive fixes
                            var userLoggedIn = 
                                document.body.classList.contains('logged-in') || 
                                document.querySelector('.admin-bar') !== null ||
                                document.querySelector('#wpadminbar') !== null;
                            
                            if (userLoggedIn) {
                                log('User is logged in - forcing form container to be visible');
                                
                                // Add ultra-targeted CSS - wrapper only, no child elements
                                var styleSheet = document.createElement('style');
                                styleSheet.textContent = `
                                /* ONLY force main wrapper to be visible - no child elements! */
                                .gform_wrapper#gform_wrapper_9 {
                                    display: block !important;
                                    visibility: visible !important;
                                    opacity: 1 !important;
                                    height: auto !important;
                                    width: auto !important;
                                    min-height: 100px !important;
                                }
                                /* DO NOT touch .gform_body or fields - let GF control conditional logic */
                            `;
                            document.head.appendChild(styleSheet);
                            
                                log('Applied aggressive CSS to prevent external hiding of form elements');
                                
                                // Also apply direct styles as backup
                                formContainer.css({
                                    'display': 'block',
                                    'visibility': 'visible',
                                    'opacity': '1',
                                    'height': 'auto',
                                    'width': 'auto',
                                    'min-height': '100px'
                                }).show();
                                
                                // Fix all parent containers with height/width 0
                                formContainer.parents().each(function() {
                                    var $parent = $(this);
                                    if ($parent.height() === 0 || $parent.width() === 0) {
                                        $parent.css({
                                            'height': 'auto',
                                            'min-height': '1px',
                                            'display': 'block'
                                        });
                                    }
                                });
                                
                                log('Applied permanent CSS fixes and corrected parent containers');
                            } else {
                                log('User not logged in - form should remain hidden');
                            }
                        }
                    }
                }, 500);
                
            }, 1500);
        }
        
        function logFieldStates() {
            if (!debug) return;
            
            // Debug function to see conditional field states
            ['field_9_41', 'field_9_42', 'field_9_43'].forEach(function(fieldId) {
                var field = $('#' + fieldId);
                if (field.length) {
                    var isVisible = field.is(':visible');
                    var hasHiddenClass = field.hasClass('gfield_hidden');
                    var display = field.css('display');
                    log('Field ' + fieldId + ' - Visible: ' + isVisible + ', Hidden class: ' + hasHiddenClass + ', Display: ' + display);
                }
            });
        }
        
        function applyConditionalLogicCSS(pattern) {
            log('Applying CSS-based conditional logic for pattern: "' + pattern + '"');
            
            var field41 = $('#field_9_41'); // Product Color
            var field42 = $('#field_9_42'); // Second Color  
            var field43 = $('#field_9_43'); // Third Color
            
            // Reset all fields to hidden first
            field41.hide();
            field42.hide();
            field43.hide();
            
            switch(pattern) {
                case 'Solid Color':
                    field41.show();
                    log('CSS Logic: Showing Product Color only for Solid Color');
                    break;
                    
                case '2-Color Fade':
                    field41.show();
                    field42.show();
                    log('CSS Logic: Showing Product Color + Second Color for 2-Color Fade');
                    break;
                    
                case '3-Color Fade':
                    field41.show();
                    field42.show();
                    field43.show();
                    log('CSS Logic: Showing all 3 color fields for 3-Color Fade');
                    break;
                    
                case 'Splatter':
                    field41.show();
                    field42.show();
                    log('CSS Logic: Showing Product Color + Second Color for Splatter');
                    break;
                    
                default:
                    log('CSS Logic: Unknown pattern "' + pattern + '" - hiding all color fields');
                    break;
            }
        }
        
    });
    
})(jQuery);
