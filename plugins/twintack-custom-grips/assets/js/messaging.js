/**
 * TwinTack Custom Grips — Messaging (Chat Thread)
 *
 * Handles sending messages via AJAX, refreshing the thread,
 * and auto-scrolling to the latest message.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    var $thread     = $('#ttcg-message-thread');
    var $form       = $('#ttcg-message-form');
    var $input      = $('#ttcg-message-input');
    var $sendBtn    = $('#ttcg-send-message');
    var $feedback   = $('#ttcg-message-feedback');
    var $messages   = $('#ttcg-messages');

    if (!$messages.length) return;

    var designId = $messages.data('design-id');

    // ------------------------------------------------------------------
    // Auto-scroll to bottom on load
    // ------------------------------------------------------------------

    function scrollToBottom() {
        if ($thread.length) {
            $thread.scrollTop($thread[0].scrollHeight);
        }
    }

    scrollToBottom();

    // ------------------------------------------------------------------
    // Send Message
    // ------------------------------------------------------------------

    $form.on('submit', function (e) {
        e.preventDefault();

        var content = $input.val().trim();
        if (!content) return;

        $sendBtn.prop('disabled', true).text(ttcg.strings.sending);
        $feedback.html('');

        $.post(ttcg.ajax_url, {
            action:    'ttcg_send_message',
            nonce:     ttcg.nonce,
            design_id: designId,
            message:   content
        })
        .done(function (res) {
            if (res.success) {
                $input.val('');
                renderMessages(res.data.messages);
                scrollToBottom();
            } else {
                $feedback.html('<span class="ttcg-error-msg">' + (res.data.message || ttcg.strings.error) + '</span>');
            }
        })
        .fail(function () {
            $feedback.html('<span class="ttcg-error-msg">' + ttcg.strings.error + '</span>');
        })
        .always(function () {
            $sendBtn.prop('disabled', false).html(
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send'
            );
        });
    });

    // ------------------------------------------------------------------
    // Render Messages from JSON
    // ------------------------------------------------------------------

    function renderMessages(messages) {
        if (!messages || !messages.length) {
            $thread.html('<div class="ttcg-messages__empty"><p>No messages yet. Start the conversation below.</p></div>');
            return;
        }

        var html = '';
        var currentUserId = parseInt(ttcg.user_id, 10);

        messages.forEach(function (msg) {
            var isSystem = (msg.sender_role === 'system' || parseInt(msg.sender_id, 10) === 0);
            var isOwn    = (parseInt(msg.sender_id, 10) === currentUserId);
            var roleClass = 'ttcg-msg--' + msg.sender_role;

            if (isSystem) roleClass = 'ttcg-msg--system';
            if (isOwn) roleClass += ' ttcg-msg--own';

            html += '<div class="ttcg-msg ' + roleClass + '" data-message-id="' + msg.id + '">';

            if (isSystem) {
                html += '<div class="ttcg-msg__system">';
                html += '<span class="ttcg-msg__system-icon">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
                html += '</span>';
                html += '<span class="ttcg-msg__system-text">' + msg.content + '</span>';
                html += '<span class="ttcg-msg__time">' + msg.date_human + '</span>';
                html += '</div>';
            } else {
                html += '<div class="ttcg-msg__bubble">';
                html += '<div class="ttcg-msg__header">';
                if (msg.sender_avatar) {
                    html += '<img src="' + msg.sender_avatar + '" alt="" class="ttcg-msg__avatar">';
                }
                html += '<span class="ttcg-msg__sender">' + escapeHtml(msg.sender_name) + '</span>';
                html += '<span class="ttcg-msg__role-badge ttcg-role--' + msg.sender_role + '">' + formatRole(msg.sender_role) + '</span>';
                html += '<span class="ttcg-msg__time">' + msg.date_human + '</span>';
                html += '</div>';
                html += '<div class="ttcg-msg__content">' + msg.content + '</div>';
                html += '</div>';
            }

            html += '</div>';
        });

        $thread.html(html);
    }

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

    // ------------------------------------------------------------------
    // Send with Ctrl+Enter / Cmd+Enter
    // ------------------------------------------------------------------

    $input.on('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            $form.trigger('submit');
        }
    });

})(jQuery);
