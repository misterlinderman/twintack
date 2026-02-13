/**
 * Customer Grips — JavaScript for the My Custom Grips experience.
 *
 * Handles:
 * - Sending messages via AJAX
 * - Design approval / request changes via AJAX
 * - Auto-scroll message thread
 *
 * @package TwinTack_Custom_Grips
 * @since   1.2.0
 */

(function ($) {
    'use strict';

    if (typeof ttcg_customer === 'undefined') {
        return;
    }

    var Customer = {
        /**
         * Initialize all handlers.
         */
        init: function () {
            this.bindMessageSend();
            this.bindReviewActions();
            this.scrollMessagesToBottom();
        },

        // ----------------------------------------------------------
        // Message Sending
        // ----------------------------------------------------------

        /**
         * Bind the send message button.
         */
        bindMessageSend: function () {
            var self = this;

            $('#ttcg-customer-send-message').on('click', function () {
                var $btn = $(this);
                var gripId = $btn.data('grip-id');
                var $input = $('#ttcg-customer-message-input');
                var content = $.trim($input.val());

                if (!content) {
                    $input.focus();
                    return;
                }

                $btn.prop('disabled', true).text(ttcg_customer.strings.sending);

                $.ajax({
                    url: ttcg_customer.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'ttcg_customer_send_message',
                        nonce: ttcg_customer.nonce,
                        design_id: gripId,
                        content: content
                    },
                    success: function (response) {
                        if (response.success) {
                            // Append the new message to the thread
                            self.appendMessage({
                                content: content,
                                sender_name: 'You',
                                date: 'Just now',
                                is_customer: true
                            });

                            $input.val('');
                            self.scrollMessagesToBottom();
                        } else {
                            alert(response.data || ttcg_customer.strings.error);
                        }
                    },
                    error: function () {
                        alert(ttcg_customer.strings.error);
                    },
                    complete: function () {
                        $btn.prop('disabled', false).text('Send Message');
                    }
                });
            });

            // Allow Enter + Ctrl/Cmd to send
            $('#ttcg-customer-message-input').on('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
                    e.preventDefault();
                    $('#ttcg-customer-send-message').trigger('click');
                }
            });
        },

        /**
         * Append a message to the thread UI.
         *
         * @param {Object} msg Message data.
         */
        appendMessage: function (msg) {
            var $container = $('#ttcg-customer-messages');
            var bubbleClass = msg.is_customer ? 'customer' : 'team';

            // Remove empty state if present
            $container.find('.ttcg-design-detail__messages-empty').remove();

            var html = '<div class="ttcg-message ttcg-message--' + bubbleClass + '">';
            html += '<div class="ttcg-message__header">';
            html += '<span class="ttcg-message__sender">' + this.escapeHtml(msg.sender_name) + '</span>';
            html += '<span class="ttcg-message__time">' + this.escapeHtml(msg.date) + '</span>';
            html += '</div>';
            html += '<div class="ttcg-message__body"><p>' + this.escapeHtml(msg.content) + '</p></div>';
            html += '</div>';

            $container.append(html);
        },

        // ----------------------------------------------------------
        // Design Review (Approve / Request Changes)
        // ----------------------------------------------------------

        /**
         * Bind review action buttons.
         */
        bindReviewActions: function () {
            var self = this;

            $('.ttcg-design-detail__review-buttons .ttcg-btn').on('click', function () {
                var $btn = $(this);
                var action = $btn.data('action');
                var $form = $btn.closest('.ttcg-design-detail__review-form');
                var gripId = $form.data('grip-id');
                var feedback = $.trim($form.find('#ttcg-review-feedback').val());
                var $response = $form.find('.ttcg-design-detail__review-response');

                // Confirm approval
                if (action === 'approve') {
                    if (!confirm(ttcg_customer.strings.approve_confirm)) {
                        return;
                    }
                }

                // Disable buttons
                $form.find('.ttcg-btn').prop('disabled', true);
                $btn.text(ttcg_customer.strings.loading);

                $.ajax({
                    url: ttcg_customer.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'ttcg_customer_review_design',
                        nonce: ttcg_customer.nonce,
                        design_id: gripId,
                        review_action: action,
                        feedback: feedback
                    },
                    success: function (response) {
                        if (response.success) {
                            $response
                                .removeClass('ttcg-design-detail__review-response--error')
                                .addClass('ttcg-design-detail__review-response--success')
                                .html(response.data.message)
                                .show();

                            // Reload page after a delay to show updated status
                            setTimeout(function () {
                                window.location.reload();
                            }, 2000);
                        } else {
                            $response
                                .removeClass('ttcg-design-detail__review-response--success')
                                .addClass('ttcg-design-detail__review-response--error')
                                .html(response.data || ttcg_customer.strings.error)
                                .show();

                            $form.find('.ttcg-btn').prop('disabled', false);
                            self.resetButtonText($form);
                        }
                    },
                    error: function () {
                        $response
                            .removeClass('ttcg-design-detail__review-response--success')
                            .addClass('ttcg-design-detail__review-response--error')
                            .html(ttcg_customer.strings.error)
                            .show();

                        $form.find('.ttcg-btn').prop('disabled', false);
                        self.resetButtonText($form);
                    }
                });
            });
        },

        /**
         * Reset button text after error.
         *
         * @param {jQuery} $form The review form container.
         */
        resetButtonText: function ($form) {
            $form.find('[data-action="approve"]').html(
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Approve Design'
            );
            $form.find('[data-action="request_changes"]').html(
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg> Request Changes'
            );
        },

        // ----------------------------------------------------------
        // Helpers
        // ----------------------------------------------------------

        /**
         * Scroll the message container to the bottom.
         */
        scrollMessagesToBottom: function () {
            var $container = $('#ttcg-customer-messages');
            if ($container.length) {
                $container.scrollTop($container[0].scrollHeight);
            }
        },

        /**
         * Basic HTML escaping.
         *
         * @param {string} str
         * @return {string}
         */
        escapeHtml: function (str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    $(document).ready(function () {
        Customer.init();
    });

})(jQuery);
