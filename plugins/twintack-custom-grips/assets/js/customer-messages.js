/**
 * TwinTack Custom Grips — Customer-Facing Messages
 *
 * Injects a message thread into the existing grip design
 * lightbox on the My Account > Grip Designs page.
 *
 * Hooks into the Magnific Popup callbacks to load messages
 * via AJAX when a design detail is opened.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    if (typeof ttcg_customer === 'undefined') return;

    // ------------------------------------------------------------------
    // Inject messages into the lightbox detail view
    // ------------------------------------------------------------------

    $(document).on('click', '.grip-design-card', function () {
        var designId = $(this).data('design-id');

        // Wait for the lightbox to open, then inject messages
        setTimeout(function () {
            var $detail = $('#grip-design-detail-' + designId);
            var $info   = $detail.find('.grip-design-detail-info');

            if (!$info.length) return;

            // Only inject once
            if ($info.find('.ttcg-customer-messages').length) return;

            var html = '<div class="ttcg-customer-messages" data-design-id="' + designId + '">';
            html += '<h3 style="margin: 2rem 0 1rem; font-size: 1.2rem; color: #333; border-top: 2px solid #e9ecef; padding-top: 1.5rem;">Messages</h3>';
            html += '<div class="ttcg-customer-thread" id="ttcg-customer-thread-' + designId + '">';
            html += '<p style="color: #999; text-align: center; padding: 1rem;">Loading messages…</p>';
            html += '</div>';
            html += '<form class="ttcg-customer-compose" id="ttcg-customer-compose-' + designId + '" style="margin-top: 12px;">';
            html += '<textarea class="ttcg-customer-input" id="ttcg-customer-input-' + designId + '" placeholder="Type a message…" rows="3" style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; font-size: 14px; resize: vertical; box-sizing: border-box;"></textarea>';
            html += '<div style="display: flex; justify-content: flex-end; margin-top: 8px;">';
            html += '<button type="submit" class="ttcg-customer-send" style="padding: 8px 20px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;">Send</button>';
            html += '</div>';
            html += '</form>';
            html += '<div class="ttcg-customer-feedback" id="ttcg-customer-feedback-' + designId + '" style="margin-top: 4px; font-size: 13px;"></div>';
            html += '</div>';

            $info.append(html);

            // Load messages
            loadMessages(designId);
        }, 300);
    });

    // ------------------------------------------------------------------
    // Load Messages
    // ------------------------------------------------------------------

    function loadMessages(designId) {
        var $thread = $('#ttcg-customer-thread-' + designId);

        $.ajax({
            url:  ttcg_customer.ajax_url,
            type: 'GET',
            data: {
                action:    'ttcg_customer_get_messages',
                nonce:     ttcg_customer.nonce,
                design_id: designId
            }
        })
        .done(function (res) {
            if (res.success && res.data.messages) {
                renderCustomerMessages($thread, res.data.messages);
            } else {
                $thread.html('<p style="color: #999; text-align: center; padding: 1rem;">No messages yet.</p>');
            }
        })
        .fail(function () {
            $thread.html('<p style="color: #999; text-align: center; padding: 1rem;">Could not load messages.</p>');
        });
    }

    // ------------------------------------------------------------------
    // Render Messages
    // ------------------------------------------------------------------

    function renderCustomerMessages($thread, messages) {
        if (!messages.length) {
            $thread.html('<p style="color: #999; text-align: center; padding: 1rem;">No messages yet. Send a message to our team below.</p>');
            return;
        }

        var html = '';
        var currentUserId = parseInt(ttcg_customer.user_id, 10);

        messages.forEach(function (msg) {
            var isSystem = (msg.sender_role === 'system' || parseInt(msg.sender_id, 10) === 0);
            var isOwn    = (parseInt(msg.sender_id, 10) === currentUserId);

            if (isSystem) {
                html += '<div style="text-align: center; padding: 6px 12px; font-size: 12px; color: #999; font-style: italic;">';
                html += msg.content + ' <span style="font-size: 11px;">— ' + msg.date_human + '</span>';
                html += '</div>';
            } else {
                var bgColor = isOwn ? '#eff6ff' : '#f4f5f7';
                var align   = isOwn ? 'margin-left: auto;' : '';
                var roleLabel = formatRole(msg.sender_role);
                var roleBg    = isOwn ? '#dbeafe' : '#ede9fe';
                var roleColor = isOwn ? '#1e40af' : '#6d28d9';

                html += '<div style="max-width: 85%; padding: 10px 14px; border-radius: 12px; background: ' + bgColor + '; margin-bottom: 8px; ' + align + '">';
                html += '<div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px; flex-wrap: wrap;">';

                if (msg.sender_avatar) {
                    html += '<img src="' + msg.sender_avatar + '" alt="" style="width: 24px; height: 24px; border-radius: 50%;">';
                }

                html += '<span style="font-size: 12px; font-weight: 600;">' + escapeHtml(msg.sender_name) + '</span>';
                html += '<span style="display: inline-block; padding: 1px 8px; border-radius: 100px; font-size: 10px; font-weight: 600; text-transform: uppercase; background: ' + roleBg + '; color: ' + roleColor + ';">' + roleLabel + '</span>';
                html += '<span style="font-size: 11px; color: #9ca3af; margin-left: auto;">' + msg.date_human + '</span>';
                html += '</div>';
                html += '<div style="font-size: 13px; line-height: 1.5;">' + msg.content + '</div>';
                html += '</div>';
            }
        });

        $thread.html(html);

        // Scroll to bottom
        $thread[0].scrollTop = $thread[0].scrollHeight;
    }

    // ------------------------------------------------------------------
    // Send Message
    // ------------------------------------------------------------------

    $(document).on('submit', '.ttcg-customer-compose', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var designId = $form.closest('.ttcg-customer-messages').data('design-id');
        var $input   = $('#ttcg-customer-input-' + designId);
        var $btn     = $form.find('.ttcg-customer-send');
        var $feedback = $('#ttcg-customer-feedback-' + designId);
        var content  = $input.val().trim();

        if (!content) return;

        $btn.prop('disabled', true).text('Sending…');
        $feedback.html('');

        $.post(ttcg_customer.ajax_url, {
            action:    'ttcg_customer_send_message',
            nonce:     ttcg_customer.nonce,
            design_id: designId,
            message:   content
        })
        .done(function (res) {
            if (res.success) {
                $input.val('');
                var $thread = $('#ttcg-customer-thread-' + designId);
                renderCustomerMessages($thread, res.data.messages);
            } else {
                $feedback.html('<span style="color: #dc2626;">' + (res.data.message || 'Error sending message.') + '</span>');
            }
        })
        .fail(function () {
            $feedback.html('<span style="color: #dc2626;">Could not send message. Please try again.</span>');
        })
        .always(function () {
            $btn.prop('disabled', false).text('Send');
        });
    });

    // ------------------------------------------------------------------
    // Utility
    // ------------------------------------------------------------------

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function formatRole(role) {
        return role.replace(/_/g, ' ').replace(/\b\w/g, function (l) {
            return l.toUpperCase();
        });
    }

})(jQuery);
