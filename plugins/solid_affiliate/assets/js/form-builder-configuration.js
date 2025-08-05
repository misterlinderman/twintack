var shouldSubmitUpdateSettings = false;

jQuery(function ($) {
    $(document).ready(function () {

        var currentFields = jQuery('#form-builder-data').data('controls_json');
        var restoreDefaultFields = jQuery('#form-builder-data').data('restore_default_controls_json');
        var lockedOptionalFields = jQuery('#form-builder-data').data('locked_optional_fields');

        // using plain javascript filter restoreDefaultFields to find objects with name === 'affiliate_id'
        var lockedOptionalFieldsData = restoreDefaultFields.filter(function (obj) {
            return lockedOptionalFields.includes(obj.name);
        });

        var lockedOptionalInputSet = lockedOptionalFieldsData.map(function (obj) {
            return {
                label: obj.label,
                name: obj.name, // optional - one will be generated from the label if name not supplied
                showHeader: false, // optional - Use the label as the header for this set of inputs
                icon: '<img src="https://solidaffiliate.com/wp-content/uploads/2022/12/favicon.png" style="vertical-align: middle" />', // optional - icon to show in the header
                fields: [obj]
            };
        });

        const setting_input_selector = '#settings-edit input[name="custom_registration_form_schema"]';

        jQuery(setting_input_selector).val(JSON.stringify(currentFields));

        var handleLockedFields = function () {
            var lockedRequiredFields = jQuery('#form-builder-data').data('locked_required_fields');
            var lockedOptionalFields = jQuery('#form-builder-data').data('locked_optional_fields');
            for (var i = 0; i < lockedRequiredFields.length; i++) {
                var field = lockedRequiredFields[i];
                jQuery('#' + field + '-preview' + ',' + '#' + field + '-preview-0').each(function(i) {
                    jQuery(this).closest('li').addClass('locked-required-field');
                    jQuery(this).closest('li').on('dblclick', function(e) {
                        e.preventDefault();
                        return false;
                    });
                })
            }

            for (var i = 0; i < lockedOptionalFields.length; i++) {
                var field = lockedOptionalFields[i];
                jQuery('#' + field + '-preview' + ',' + '#' + field + '-preview-0').each(function(i) {
                    jQuery(this).closest('li').addClass('locked-optional-field');
                    jQuery(this).closest('li').find('.form-elements .form-group.subtype-wrap .input-wrap select').attr('disabled', true);
                    jQuery(this).closest('li').find('.form-elements .form-group.name-wrap .input-wrap input').attr('disabled', true);
                    jQuery(this).closest('li').on('dblclick', function(e) {
                        e.preventDefault();
                        return false;
                    });
                });
            }
        }

        var sharedCustomAttrs = {
            label: {
                required: true,
                value: '',
                tupe: 'text'
            },
            name: {
                required: true,
                value: '',
                tupe: 'text'
            },
            description: {
                label: 'Tooltip Text',
                value: '',
                type: 'text'
            },
            descriptionText: {
                label: 'Description Text',
                value: '',
                type: 'textarea'
            },
            editable: {
                label: '<small>Affiliates can Edit</small>',
                value: false,
                type: 'checkbox'
            }
        };

        var options = {
            disableFields: [
                'autocomplete',
                'date',
                'file',
                'hidden',
                'button',
                'paragraph',
                'header'
            ],
            disabledActionButtons: [
                'data',
                'save',
                'clear'
            ],
            actionButtons: [{
                id: 'reset-default',
                className: 'button-reset',
                label: 'Reset to default',
                type: 'button',
                events: {
                    click: function (e) {
                        if (confirm('Are you sure you want to restore the default form? This will overwrite your current form, and restore it to the original version when Solid Affiliate was first installed.')) {
                            window.solid_affiliate_form_builder.actions.setData(restoreDefaultFields);
                        }
                    }
                }
            }],
            disabledSubtypes: {
                text: [
                    'color',
                    'tel'
                ],
                textarea: [
                    'quill',
                    'tinymce'
                ]
            },
            subtypes: {
                text: [
                    'url'
                ]
            },
            disabledAttrs: [
                'access',
                'toggle',
                'value',
                'inline',
                'other',
                'multiple',
                'className',
                'step'
            ],
            typeUserAttrs: {
                'checkbox-group': sharedCustomAttrs,
                number: sharedCustomAttrs,
                'radio-group': sharedCustomAttrs,
                select: sharedCustomAttrs,
                text: sharedCustomAttrs,
                textarea: sharedCustomAttrs
            },
            layoutTemplates: {
                default: function (field, _label, _help, data, x) {
                    desc = jQuery('<div/>')
                        .addClass('form-builder-description')
                        .attr('id', 'desc-' + data.id)
                        .append(data.descriptionText);

                    return jQuery('<div/>').append(field, desc);
                },
            },
            replaceFields: [
                {
                    type: "text",
                    label: "Text Field<br />(text, email, password, URL)"
                }
            ],
            inputSets: lockedOptionalInputSet,
            onSave: function (_evt, formData) {
                jQuery(setting_input_selector).val(formData);
            },
            onClearAll: function (formData) {
                jQuery(setting_input_selector).val(restoreDefaultFields);
            },
            onAddFieldAfter: function (fieldId) {
                handleLockedFields();
            },
            scrollToFieldOnAdd: false,
            defaultFields: currentFields,
        };

        jQuery(function ($) {
            // Initialize the Form Builder
            var formBuilder = $(document.getElementById('fb-editor')).formBuilder(options);

            formBuilder.promise.then(function (fb) {
                window.solid_affiliate_form_builder = fb;
                jQuery('.fb-editor-loading').hide();
            });

            // on settings submit
            $('form#settings-edit').submit(function (e) {
                var formData = formBuilder.actions.getData('json');
                jQuery(setting_input_selector).val(formData);

                if (shouldSubmitUpdateSettings) {
                    return true;
                } else {
                    var validatePost = {
                        action: 'sld_affiliate_validate_setting',
                        setting_key: 'custom_registration_form_schema',
                        setting_value: formData
                    };

                    preValidateSetting(validatePost, sld_affiliate_js_variables.ajaxurl);
                    e.preventDefault();
                }
            });
        });
    })
});

function preValidateSetting(data, url) {
    jQuery.ajax({
        url: url,
        type: 'POST',
        data: data,
        success: function (response) {
            if (response["success"] && response["data"]["valid"]) {
                shouldSubmitUpdateSettings = true;
                jQuery('#submit_admin_settings').click();
            } else if (response["success"] && response["data"]["valid"] == false) {
                var msg = response["data"]["error"];
                removeOldNotice();
                addErrorNotice(msg);
                window.scrollTo(0, 0);
            }
        },
        error: function (response) {
            var msg = response["data"]["error"];
            removeOldNotice();
            addErrorNotice(msg);
            window.scrollTo(0, 0);
        }
    });
}

function addErrorNotice(msg) {
    var header = jQuery('#form-builder-data').data('error_msg_header');
    var notice = `<div class="notice notice-error custom-registration-form-error">
                    <p class="error-header">${header}</p>
                    <p class="error-msg">${msg}</p>
                </div>`;
    jQuery(notice).insertBefore('#fb-editor');
}

function removeOldNotice() {
    jQuery('div.custom-registration-form-error').remove();
}