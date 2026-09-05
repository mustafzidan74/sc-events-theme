<?php
/**
 * Chat Management Page - Real-time Conversations
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$page_title = sc_t('dashboard_pages.chat_messages', 'Chat Messages');

// Translations
$t = array(
    'chat_messages' => sc_t('dashboard_pages.chat_messages', 'Chat Messages'),
    'chat' => sc_t('dashboard_pages.chat', 'Chat'),
    'total_conversations' => sc_t('dashboard_pages.total_conversations', 'Total Conversations'),
    'active_conversations' => sc_t('dashboard_pages.active_conversations', 'Active Conversations'),
    'unread_messages' => sc_t('dashboard_pages.unread_messages', 'Unread Messages'),
    'conversations' => sc_t('dashboard_pages.conversations', 'Conversations'),
    'all_status' => sc_t('dashboard_pages.all_status', 'All Status'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'closed' => sc_t('dashboard_pages.closed', 'Closed'),
    'archived' => sc_t('dashboard_pages.archived', 'Archived'),
    'loading' => sc_t('dashboard_pages.loading', 'Loading...'),
    'select_conversation' => sc_t('dashboard_pages.select_conversation', 'Select a conversation'),
    'choose_conversation' => sc_t('dashboard_pages.choose_conversation', 'Choose a conversation from the list to view messages'),
    'archive_conversation' => sc_t('dashboard_pages.archive_conversation', 'Archive Conversation'),
    'restore_conversation' => sc_t('dashboard_pages.restore_conversation', 'Restore Conversation'),
    'close_conversation' => sc_t('dashboard_pages.close_conversation', 'Close Conversation'),
    'reopen_conversation' => sc_t('dashboard_pages.reopen_conversation', 'Reopen Conversation'),
    'attach_file' => sc_t('dashboard_pages.attach_file', 'Attach file'),
    'type_reply' => sc_t('dashboard_pages.type_reply', 'Type your reply...'),
    'visitor' => sc_t('dashboard_pages.visitor', 'Visitor'),
    'no_conversations' => sc_t('dashboard_pages.no_conversations', 'No conversations yet'),
    'sending' => sc_t('dashboard_pages.sending', 'Sending...'),
    'failed_send_message' => sc_t('dashboard_pages.failed_send_message', 'Failed to send message'),
    'confirm_close_conversation' => sc_t('dashboard_pages.confirm_close_conversation', 'Are you sure you want to close this conversation?'),
    'conversation_closed' => sc_t('dashboard_pages.conversation_closed', 'Conversation closed'),
    'confirm_archive_conversation' => sc_t('dashboard_pages.confirm_archive_conversation', 'Are you sure you want to archive this conversation?'),
    'conversation_archived' => sc_t('dashboard_pages.conversation_archived', 'Conversation archived'),
    'conversation_restored' => sc_t('dashboard_pages.conversation_restored', 'Conversation restored'),
    'conversation_reopened' => sc_t('dashboard_pages.conversation_reopened', 'Conversation reopened'),
    'new_message' => sc_t('dashboard_pages.new_message', 'New Message'),
    'file_size_limit' => sc_t('dashboard_pages.file_size_limit', 'File size exceeds 5MB limit'),
    'uploading_image' => sc_t('dashboard_pages.uploading_image', 'Uploading image...'),
    'uploading_file' => sc_t('dashboard_pages.uploading_file', 'Uploading file...'),
    'failed_upload_file' => sc_t('dashboard_pages.failed_upload_file', 'Failed to upload file'),
    'upload_failed' => sc_t('dashboard_pages.upload_failed', 'Upload failed. Please try again.'),
    'download_file' => sc_t('dashboard_pages.download_file', 'Download File'),
);

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

// Get chat statistics
$chat = sc_chat();
$chat_stats = $chat->get_chat_stats();
?>

<div id="main-content">
<div class="container-fluid">
    <!-- Page Header -->
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo $t['chat_messages']; ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item active"><?php echo $t['chat']; ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="d-flex flex-row-reverse">
                    <div class="page_action"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card info-box-2">
                <div class="icon" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color2) 100%);">
                    <i class="fa fa-comments"></i>
                </div>
                <div class="content">
                    <div class="text"><?php echo $t['total_conversations']; ?></div>
                    <div class="number" id="stat-total"><?php echo intval($chat_stats['total']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card info-box-2">
                <div class="icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="fa fa-circle"></i>
                </div>
                <div class="content">
                    <div class="text"><?php echo $t['active_conversations']; ?></div>
                    <div class="number" id="stat-active"><?php echo intval($chat_stats['active']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card info-box-2">
                <div class="icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                    <i class="fa fa-envelope"></i>
                </div>
                <div class="content">
                    <div class="text"><?php echo $t['unread_messages']; ?></div>
                    <div class="number" id="stat-unread"><?php echo intval($chat_stats['unread'] ?? 0); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Chat Interface -->
    <div class="row">
        <!-- Conversations List -->
        <div class="col-md-4">
            <div class="card chat-sidebar-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa fa-inbox mr-2"></i> <?php echo $t['conversations']; ?></h5>
                    <span class="badge badge-primary" id="conversations-count">0</span>
                </div>
                <div class="card-body p-0">
                    <!-- Filters -->
                    <div class="chat-filters p-3 border-bottom">
                        <select class="form-control form-control-sm" id="filter-status">
                            <option value="all"><?php echo $t['all_status']; ?></option>
                            <option value="active"><?php echo $t['active']; ?></option>
                            <option value="closed"><?php echo $t['closed']; ?></option>
                            <option value="archived"><?php echo $t['archived']; ?></option>
                        </select>
                    </div>
                    <!-- Conversations List -->
                    <div class="conversations-list" id="conversations-list">
                        <div class="text-center p-4 text-muted">
                            <i class="fa fa-spinner fa-spin"></i> <?php echo $t['loading']; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="col-md-8">
            <div class="card chat-main-card">
                <!-- Chat Header -->
                <div class="card-header chat-header" id="chat-header" style="display: none;">
                    <div class="chat-user-info">
                        <div class="chat-user-avatar">
                            <i class="fa fa-user"></i>
                        </div>
                        <div class="chat-user-details">
                            <h6 id="chat-user-name">-</h6>
                            <small id="chat-user-email">-</small>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <span class="badge badge-success" id="chat-status"><?php echo $t['active']; ?></span>
                        <button class="btn btn-sm btn-outline-secondary ml-2" id="archive-conversation-btn" title="<?php echo esc_attr($t['archive_conversation']); ?>">
                            <i class="fa fa-archive"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success ml-1" id="restore-conversation-btn" title="<?php echo esc_attr($t['restore_conversation']); ?>" style="display: none;">
                            <i class="fa fa-undo"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger ml-1" id="close-conversation-btn" title="<?php echo esc_attr($t['close_conversation']); ?>">
                            <i class="fa fa-times"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary ml-1" id="reopen-conversation-btn" title="<?php echo esc_attr($t['reopen_conversation']); ?>" style="display: none;">
                            <i class="fa fa-envelope-open"></i>
                        </button>
                    </div>
                </div>

                <!-- Messages Area -->
                <div class="card-body chat-messages-area" id="chat-messages">
                    <div class="chat-placeholder text-center p-5">
                        <div class="chat-placeholder-icon">
                            <i class="fa fa-comments fa-3x text-muted"></i>
                        </div>
                        <h5 class="mt-3 text-muted"><?php echo $t['select_conversation']; ?></h5>
                        <p class="text-muted"><?php echo $t['choose_conversation']; ?></p>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="card-footer chat-input-area" id="chat-input-area" style="display: none;">
                    <input type="file" id="chat-file-input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" style="display: none;">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <button class="btn btn-outline-secondary" id="attach-file-btn" title="<?php echo esc_attr($t['attach_file']); ?>">
                                <i class="fa fa-paperclip"></i>
                            </button>
                        </div>
                        <input type="text" class="form-control" id="message-input" placeholder="<?php echo esc_attr($t['type_reply']); ?>">
                        <div class="input-group-append">
                            <button class="btn btn-primary" id="send-message-btn">
                                <i class="fa fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Chat Dashboard Styles -->
<link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/dashboard/css/chat.css">
<!-- Inline styles moved to chat.css -->


<script>
// Chat Translations
var chatTranslations = {
    visitor: '<?php echo esc_js($t['visitor']); ?>',
    no_conversations: '<?php echo esc_js($t['no_conversations']); ?>',
    sending: '<?php echo esc_js($t['sending']); ?>',
    failed_send_message: '<?php echo esc_js($t['failed_send_message']); ?>',
    confirm_close_conversation: '<?php echo esc_js($t['confirm_close_conversation']); ?>',
    conversation_closed: '<?php echo esc_js($t['conversation_closed']); ?>',
    confirm_archive_conversation: '<?php echo esc_js($t['confirm_archive_conversation']); ?>',
    conversation_archived: '<?php echo esc_js($t['conversation_archived']); ?>',
    select_conversation: '<?php echo esc_js($t['select_conversation']); ?>',
    choose_conversation: '<?php echo esc_js($t['choose_conversation']); ?>',
    conversation_restored: '<?php echo esc_js($t['conversation_restored']); ?>',
    conversation_reopened: '<?php echo esc_js($t['conversation_reopened']); ?>',
    new_message: '<?php echo esc_js($t['new_message']); ?>',
    file_size_limit: '<?php echo esc_js($t['file_size_limit']); ?>',
    uploading_image: '<?php echo esc_js($t['uploading_image']); ?>',
    uploading_file: '<?php echo esc_js($t['uploading_file']); ?>',
    failed_upload_file: '<?php echo esc_js($t['failed_upload_file']); ?>',
    upload_failed: '<?php echo esc_js($t['upload_failed']); ?>',
    download_file: '<?php echo esc_js($t['download_file']); ?>',
    active: '<?php echo esc_js($t['active']); ?>',
    closed: '<?php echo esc_js($t['closed']); ?>',
    archived: '<?php echo esc_js($t['archived']); ?>'
};

jQuery(document).ready(function($) {
    var currentConversationId = null;
    var lastMessageId = 0;
    var pollingInterval = null;
    var chatNonce = '<?php echo wp_create_nonce('sc_chat_nonce'); ?>';

    // Load conversations (global chat - no event filter)
    function loadConversations() {
        var status = $('#filter-status').val();

        $.post(scDashboard.ajaxurl, {
            action: 'sc_chat_get_conversations',
            nonce: chatNonce,
            status: status
        }, function(response) {
            if (response.success) {
                renderConversations(response.data.conversations);
                updateStats(response.data.stats);
            }
        });
    }

    // Render conversations list
    function renderConversations(conversations) {
        var $list = $('#conversations-list');

        if (conversations.length === 0) {
            $list.html(`
                <div class="no-conversations">
                    <i class="fa fa-comments"></i>
                    <p>${chatTranslations.no_conversations}</p>
                </div>
            `);
            $('#conversations-count').text('0');
            return;
        }

        var html = '';
        conversations.forEach(function(conv) {
            var isActive = currentConversationId == conv.id ? 'active' : '';
            var isUnread = conv.unread_organizer > 0 ? 'unread' : '';
            var time = formatTime(conv.last_message_at || conv.created_at);

            html += `
                <div class="conversation-item ${isActive} ${isUnread}" data-id="${conv.id}">
                    <div class="conversation-avatar">
                        <i class="fa fa-user"></i>
                    </div>
                    <div class="conversation-info">
                        <h6>${escapeHtml(conv.visitor_name || chatTranslations.visitor)}</h6>
                        <div class="visitor-email">${escapeHtml(conv.visitor_email || '')}</div>
                        <div class="last-message">${escapeHtml(conv.last_message || '...')}</div>
                    </div>
                    <div class="conversation-meta">
                        <div class="time">${time}</div>
                        ${conv.unread_organizer > 0 ? `<div class="unread-badge">${conv.unread_organizer}</div>` : ''}
                    </div>
                </div>
            `;
        });

        $list.html(html);
        $('#conversations-count').text(conversations.length);
    }

    // Load messages for a conversation
    function loadMessages(conversationId, isPolling) {
        $.post(scDashboard.ajaxurl, {
            action: 'sc_chat_get_conversation_messages',
            nonce: chatNonce,
            conversation_id: conversationId
        }, function(response) {
            if (response.success) {
                if (!isPolling) {
                    renderChatHeader(response.data.conversation);
                }
                renderMessages(response.data.messages, isPolling);

                // Update last message ID
                var messages = response.data.messages;
                if (messages.length > 0) {
                    lastMessageId = messages[messages.length - 1].id;
                }
            }
        });
    }

    // Render chat header
    function renderChatHeader(conversation) {
        $('#chat-header').show();
        $('#chat-user-name').text(conversation.visitor_name || chatTranslations.visitor);
        $('#chat-user-email').text(conversation.visitor_email || '');

        var statusClass = conversation.status === 'active' ? 'badge-success' : (conversation.status === 'archived' ? 'badge-warning' : 'badge-secondary');
        var statusText = conversation.status === 'active' ? chatTranslations.active : (conversation.status === 'archived' ? chatTranslations.archived : chatTranslations.closed);
        $('#chat-status').removeClass('badge-success badge-secondary badge-warning').addClass(statusClass).text(statusText);

        // Show/hide buttons based on status
        if (conversation.status === 'archived') {
            $('#archive-conversation-btn').hide();
            $('#restore-conversation-btn').show();
            $('#close-conversation-btn').hide();
            $('#reopen-conversation-btn').hide();
            $('#chat-input-area').hide();
        } else if (conversation.status === 'closed') {
            $('#archive-conversation-btn').show();
            $('#restore-conversation-btn').hide();
            $('#close-conversation-btn').hide();
            $('#reopen-conversation-btn').show();
            $('#chat-input-area').hide();
        } else {
            // Active
            $('#archive-conversation-btn').show();
            $('#restore-conversation-btn').hide();
            $('#close-conversation-btn').show();
            $('#reopen-conversation-btn').hide();
            $('#chat-input-area').show();
        }

        // Store current conversation status
        currentConversationStatus = conversation.status;
    }

    var currentConversationStatus = null;

    // Render messages
    function renderMessages(messages, isPolling) {
        var $container = $('#chat-messages');

        if (!isPolling) {
            $container.html('');
        }

        messages.forEach(function(msg) {
            // Check if message already exists
            if ($container.find(`[data-id="${msg.id}"]`).length > 0) return;

            var time = formatTime(msg.created_at);
            var contentHtml = '';
            var extraClass = msg.message_type === 'system' ? ' system' : '';

            // Handle different message types
            if (msg.message_type === 'system') {
                contentHtml = `<div class="message-content">${escapeHtml(msg.message)}</div>`;
            } else if (msg.message_type === 'image' && msg.file_url) {
                contentHtml = `
                    <div class="message-content image-message">
                        <a href="${escapeHtml(msg.file_url)}" target="_blank">
                            <img src="${escapeHtml(msg.file_url)}" alt="Image">
                        </a>
                    </div>
                `;
            } else if (msg.message_type === 'file' && msg.file_url) {
                contentHtml = `
                    <div class="message-content file-message">
                        <a href="${escapeHtml(msg.file_url)}" target="_blank" download>
                            <i class="fa fa-file"></i>
                            <span>${escapeHtml(msg.file_name || 'Download File')}</span>
                        </a>
                    </div>
                `;
            } else {
                contentHtml = `<div class="message-content">${escapeHtml(msg.message)}</div>`;
            }

            var html = `
                <div class="chat-message ${msg.sender_type}${extraClass}" data-id="${msg.id}">
                    ${contentHtml}
                    <div class="message-time">${time}</div>
                </div>
            `;
            $container.append(html);
        });

        // Scroll to bottom
        $container.scrollTop($container[0].scrollHeight);

        // Refresh conversation list to update unread counts
        if (isPolling) {
            loadConversations();
        }
    }

    // Send message
    function sendMessage() {
        var message = $('#message-input').val().trim();
        if (!message || !currentConversationId) return;

        $('#message-input').val('');

        // Optimistic UI update
        var tempId = 'temp-' + Date.now();
        var html = `
            <div class="chat-message organizer" data-id="${tempId}">
                <div class="message-content">${escapeHtml(message)}</div>
                <div class="message-time">${chatTranslations.sending}</div>
            </div>
        `;
        $('#chat-messages').append(html);
        $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);

        $.post(scDashboard.ajaxurl, {
            action: 'sc_chat_send',
            nonce: chatNonce,
            conversation_id: currentConversationId,
            message: message,
            sender_type: 'organizer'
        }, function(response) {
            if (response.success) {
                // Replace temp message with real one
                $(`[data-id="${tempId}"]`).attr('data-id', response.data.message.id);
                $(`[data-id="${response.data.message.id}"] .message-time`).text(formatTime(response.data.message.created_at));
                loadConversations();
            } else {
                $(`[data-id="${tempId}"] .message-time`).text('Failed').css('color', '#ef4444');
                toastr.error(response.data?.message || chatTranslations.failed_send_message);
            }
        }).fail(function() {
            $(`[data-id="${tempId}"] .message-time`).text('Failed').css('color', '#ef4444');
            toastr.error(chatTranslations.failed_send_message);
        });
    }

    // Close conversation
    function closeConversation() {
        if (!currentConversationId) return;

        if (!confirm(chatTranslations.confirm_close_conversation)) return;

        $.post(scDashboard.ajaxurl, {
            action: 'sc_chat_close_conversation',
            nonce: chatNonce,
            conversation_id: currentConversationId
        }, function(response) {
            if (response.success) {
                toastr.success(chatTranslations.conversation_closed);
                loadConversations();
                $('#chat-status').removeClass('badge-success').addClass('badge-secondary').text(chatTranslations.closed);
            }
        });
    }

    // Archive conversation
    function archiveConversation() {
        if (!currentConversationId) return;

        if (!confirm(chatTranslations.confirm_archive_conversation)) return;

        $.post(scDashboard.ajaxurl, {
            action: 'sc_chat_archive_conversation',
            nonce: chatNonce,
            conversation_id: currentConversationId
        }, function(response) {
            if (response.success) {
                toastr.success(chatTranslations.conversation_archived);
                loadConversations();
                // Reset chat area
                currentConversationId = null;
                $('#chat-header').hide();
                $('#chat-input-area').hide();
                $('#chat-messages').html(`
                    <div class="chat-placeholder text-center p-5">
                        <div class="chat-placeholder-icon">
                            <i class="fa fa-comments fa-3x text-muted"></i>
                        </div>
                        <h5 class="mt-3 text-muted">${chatTranslations.select_conversation}</h5>
                        <p class="text-muted">${chatTranslations.choose_conversation}</p>
                    </div>
                `);
            }
        });
    }

    // Restore conversation from archive
    function restoreConversation() {
        if (!currentConversationId) return;

        $.post(scDashboard.ajaxurl, {
            action: 'sc_chat_restore_conversation',
            nonce: chatNonce,
            conversation_id: currentConversationId
        }, function(response) {
            if (response.success) {
                toastr.success(chatTranslations.conversation_restored);
                loadConversations();
                loadMessages(currentConversationId, false);
            }
        });
    }

    // Reopen closed conversation
    function reopenConversation() {
        if (!currentConversationId) return;

        $.post(scDashboard.ajaxurl, {
            action: 'sc_chat_reopen_conversation',
            nonce: chatNonce,
            conversation_id: currentConversationId
        }, function(response) {
            if (response.success) {
                toastr.success(chatTranslations.conversation_reopened);
                loadConversations();
                loadMessages(currentConversationId, false);
            }
        });
    }

    // Update stats
    function updateStats(stats) {
        $('#stat-total').text(stats.total || 0);
        $('#stat-active').text(stats.active || 0);
        $('#stat-unread').text(stats.unread || 0);
    }

    // Start polling for new messages
    function startPolling() {
        stopPolling();
        pollingInterval = setInterval(function() {
            if (currentConversationId) {
                pollNewMessages();
            }
            loadConversations();
        }, 5000);
    }

    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }

    function pollNewMessages() {
        $.post(scDashboard.ajaxurl, {
            action: 'sc_chat_load',
            nonce: chatNonce,
            conversation_id: currentConversationId,
            last_id: lastMessageId,
            reader_type: 'organizer'
        }, function(response) {
            if (response.success && response.data.messages.length > 0) {
                renderMessages(response.data.messages, true);
                var messages = response.data.messages;
                lastMessageId = messages[messages.length - 1].id;

                // Play sound and show notification for new visitor messages
                var visitorMessages = messages.filter(function(m) {
                    return m.sender_type === 'visitor';
                });
                if (visitorMessages.length > 0) {
                    playNotificationSound();
                    var lastVisitorMsg = visitorMessages[visitorMessages.length - 1];
                    toastr.info(lastVisitorMsg.message.substring(0, 50) + '...', chatTranslations.new_message);
                }
            }
        });
    }

    // Play notification sound
    function playNotificationSound() {
        try {
            var audioContext = new (window.AudioContext || window.webkitAudioContext)();
            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }
            var oscillator = audioContext.createOscillator();
            var gainNode = audioContext.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            oscillator.frequency.setValueAtTime(880, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(1100, audioContext.currentTime + 0.15);
            oscillator.type = 'sine';
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.4);
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.4);
        } catch (e) {
            console.log('Audio not supported');
        }
    }

    // Format time
    function formatTime(dateString) {
        if (!dateString) return '';
        var date = new Date(dateString);
        var now = new Date();
        var diff = now - date;

        if (diff < 86400000) { // Less than 24 hours
            return date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
        } else if (diff < 604800000) { // Less than 7 days
            return date.toLocaleDateString([], {weekday: 'short'});
        } else {
            return date.toLocaleDateString([], {month: 'short', day: 'numeric'});
        }
    }

    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Event Listeners
    $('#filter-status').on('change', function() {
        loadConversations();
    });

    $(document).on('click', '.conversation-item', function() {
        var id = $(this).data('id');
        if (id === currentConversationId) return;

        currentConversationId = id;
        lastMessageId = 0;

        $('.conversation-item').removeClass('active');
        $(this).addClass('active').removeClass('unread');

        loadMessages(id, false);
    });

    $('#send-message-btn').on('click', sendMessage);

    $('#message-input').on('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    $('#close-conversation-btn').on('click', closeConversation);
    $('#archive-conversation-btn').on('click', archiveConversation);
    $('#restore-conversation-btn').on('click', restoreConversation);
    $('#reopen-conversation-btn').on('click', reopenConversation);

    // File upload
    $('#attach-file-btn').on('click', function() {
        $('#chat-file-input').click();
    });

    $('#chat-file-input').on('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            uploadFile(file);
        }
    });

    // Upload file function
    function uploadFile(file) {
        if (!file || !currentConversationId) return;

        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            toastr.error(chatTranslations.file_size_limit);
            return;
        }

        var isImage = file.type.startsWith('image/');
        var tempId = 'temp-' + Date.now();

        // Show uploading indicator
        var html = `
            <div class="chat-message organizer" data-id="${tempId}">
                <div class="message-content">${isImage ? chatTranslations.uploading_image : chatTranslations.uploading_file}</div>
                <div class="message-time"><i class="fa fa-spinner fa-spin"></i></div>
            </div>
        `;
        $('#chat-messages').append(html);
        $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);

        var formData = new FormData();
        formData.append('action', 'sc_chat_upload_file');
        formData.append('nonce', chatNonce);
        formData.append('conversation_id', currentConversationId);
        formData.append('sender_type', 'organizer');
        formData.append('file', file);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $(`[data-id="${tempId}"]`).remove();
                if (response.success) {
                    renderMessages([response.data.message], true);
                    lastMessageId = Math.max(lastMessageId, parseInt(response.data.message.id));
                } else {
                    toastr.error(response.data?.message || chatTranslations.failed_upload_file);
                }
            },
            error: function() {
                $(`[data-id="${tempId}"]`).remove();
                toastr.error(chatTranslations.upload_failed);
            },
            complete: function() {
                $('#chat-file-input').val('');
            }
        });
    }

    // Initialize
    loadConversations();
    startPolling();

    // Cleanup on page unload
    $(window).on('beforeunload', stopPolling);
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
