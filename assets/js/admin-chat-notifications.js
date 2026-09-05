/**
 * SC Events Admin Chat Notifications
 * Real-time notifications for dashboard and all admin pages
 *
 * @version 1.0.0
 */

(function() {
    'use strict';

    // Give up after this many consecutive failures. Without it an expired
    // session keeps firing a request every 5 seconds for the life of the tab.
    const MAX_CONSECUTIVE_FAILURES = 3;

    class SCAdminChatNotifications {
        constructor() {
            this.ajaxUrl = scAdminChatConfig.ajaxUrl;
            this.nonce = scAdminChatConfig.nonce;
            this.lastCheck = 0;
            this.lastUnreadCount = 0;
            this.pollingInterval = null;
            this.consecutiveFailures = 0;
            this.pollingDisabled = false;
            this.isOnChatPage = window.location.pathname.endsWith('/chat') || window.location.pathname.endsWith('/chat/') || window.location.href.includes('page=chat');
            this.notificationPermissionRequested = false;

            this.init();
        }

        init() {
            this.createNotificationWidget();
            this.requestNotificationPermission();
            this.checkUnreadCount();
            this.startPolling();

            // Only poll while the tab is actually on screen.
            document.addEventListener('visibilitychange', () => {
                if (this.pollingDisabled) {
                    return;
                }

                if (document.visibilityState === 'visible') {
                    this.checkUnreadCount();
                    this.startPolling();
                } else {
                    this.stopPolling();
                }
            });
        }

        createNotificationWidget() {
            // Create floating notification badge for non-chat pages
            if (!this.isOnChatPage) {
                const widget = document.createElement('div');
                widget.id = 'sc-admin-chat-widget';
                widget.innerHTML = `
                    <a href="${scAdminChatConfig.chatPageUrl}" class="sc-admin-chat-btn" title="${scAdminChatConfig.chatLabel || 'Chat Messages'}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                        </svg>
                        <span class="sc-admin-chat-badge" style="display: none;">0</span>
                    </a>
                `;
                document.body.appendChild(widget);

                // Add styles
                this.addStyles();
            }

            // Also update admin bar if exists
            this.updateAdminBarBadge(0);
        }

        addStyles() {
            const styles = document.createElement('style');
            styles.textContent = `
                #sc-admin-chat-widget {
                    position: fixed;
                    bottom: 24px;
                    left: 24px;
                    z-index: 999999;
                }

                .sc-admin-chat-btn {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 50px;
                    height: 50px;
                    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color2) 100%);
                    border-radius: 50%;
                    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                    transition: all 0.3s ease;
                    text-decoration: none;
                    position: relative;
                }

                .sc-admin-chat-btn:hover {
                    transform: scale(1.1);
                    box-shadow: 0 6px 25px rgba(102, 126, 234, 0.5);
                }

                .sc-admin-chat-btn svg {
                    width: 24px;
                    height: 24px;
                    color: white;
                }

                .sc-admin-chat-badge {
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    min-width: 20px;
                    height: 20px;
                    background: #ef4444;
                    color: white;
                    border-radius: 50%;
                    font-size: 11px;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0 4px;
                    animation: sc-admin-pulse 2s infinite;
                }

                @keyframes sc-admin-pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.15); }
                }

                .sc-admin-chat-badge.new-message {
                    animation: sc-admin-bounce 0.5s ease;
                }

                @keyframes sc-admin-bounce {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.4); }
                }

                /* Toast notification */
                .sc-admin-toast {
                    position: fixed;
                    bottom: 90px;
                    left: 24px;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
                    padding: 16px 20px;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    z-index: 999999;
                    transform: translateX(-120%);
                    transition: transform 0.3s ease;
                    max-width: 350px;
                    cursor: pointer;
                }

                .sc-admin-toast.show {
                    transform: translateX(0);
                }

                .sc-admin-toast-icon {
                    width: 40px;
                    height: 40px;
                    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color2) 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                }

                .sc-admin-toast-icon svg {
                    width: 20px;
                    height: 20px;
                    color: white;
                }

                .sc-admin-toast-content {
                    flex: 1;
                }

                .sc-admin-toast-title {
                    font-weight: 600;
                    font-size: 14px;
                    color: #1f2937;
                    margin-bottom: 2px;
                }

                .sc-admin-toast-message {
                    font-size: 13px;
                    color: #6b7280;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    max-width: 250px;
                }

                /* RTL Support */
                [dir="rtl"] #sc-admin-chat-widget,
                .rtl #sc-admin-chat-widget {
                    left: auto;
                    right: 24px;
                }

                [dir="rtl"] .sc-admin-toast,
                .rtl .sc-admin-toast {
                    left: auto;
                    right: 24px;
                    transform: translateX(120%);
                }

                [dir="rtl"] .sc-admin-toast.show,
                .rtl .sc-admin-toast.show {
                    transform: translateX(0);
                }
            `;
            document.head.appendChild(styles);
        }

        async checkUnreadCount() {
            try {
                const response = await this.ajax('sc_chat_get_unread_count', {});
                this.consecutiveFailures = 0;

                if (response.success) {
                    const newCount = parseInt(response.data.unread) || 0;

                    // Check if we have new messages
                    if (newCount > this.lastUnreadCount && this.lastUnreadCount !== 0) {
                        // New message arrived!
                        this.onNewMessage(newCount - this.lastUnreadCount);
                    }

                    this.lastUnreadCount = newCount;
                    this.updateBadge(newCount);
                }
            } catch (error) {
                this.consecutiveFailures++;

                if (this.consecutiveFailures >= MAX_CONSECUTIVE_FAILURES) {
                    this.pollingDisabled = true;
                    this.stopPolling();
                    console.warn('SC chat notifications: polling stopped after ' + this.consecutiveFailures + ' failed checks.', error);
                }
            }
        }

        async checkNewConversations() {
            try {
                const response = await this.ajax('sc_chat_get_conversations', {
                    status: 'active'
                });

                if (response.success && response.data.conversations) {
                    const unreadCount = response.data.stats.unread || 0;

                    if (unreadCount > this.lastUnreadCount && this.lastUnreadCount !== 0) {
                        // Find the newest conversation with unread messages
                        const unreadConv = response.data.conversations.find(c =>
                            parseInt(c.unread_organizer) > 0
                        );

                        if (unreadConv) {
                            this.showToastNotification(
                                unreadConv.visitor_name || 'Visitor',
                                unreadConv.last_message || 'New message'
                            );
                        }
                    }

                    this.lastUnreadCount = unreadCount;
                    this.updateBadge(unreadCount);
                }
            } catch (error) {
                console.error('Check conversations error:', error);
            }
        }

        onNewMessage(count) {
            // Play notification sound
            this.playNotificationSound();

            // Show browser notification
            this.showBrowserNotification(count);

            // Show toast notification
            this.showToastNotification('New Chat Message', `You have ${count} new message${count > 1 ? 's' : ''}`);

            // Animate badge
            const badge = document.querySelector('.sc-admin-chat-badge');
            if (badge) {
                badge.classList.add('new-message');
                setTimeout(() => badge.classList.remove('new-message'), 500);
            }
        }

        updateBadge(count) {
            // Update floating widget badge
            const badge = document.querySelector('.sc-admin-chat-badge');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            }

            // Update admin bar badge
            this.updateAdminBarBadge(count);

            // Update page title
            if (count > 0) {
                document.title = `(${count}) ` + document.title.replace(/^\(\d+\)\s*/, '');
            } else {
                document.title = document.title.replace(/^\(\d+\)\s*/, '');
            }
        }

        updateAdminBarBadge(count) {
            // Check if we have a chat link in admin bar
            let chatLink = document.querySelector('#wp-admin-bar-sc-chat a');
            if (!chatLink) {
                // Try to add to admin bar
                const adminBar = document.querySelector('#wp-admin-bar-root-default');
                if (adminBar && !document.querySelector('#wp-admin-bar-sc-chat')) {
                    const li = document.createElement('li');
                    li.id = 'wp-admin-bar-sc-chat';
                    li.innerHTML = `<a class="ab-item" href="${scAdminChatConfig.chatPageUrl}">
                        <span class="ab-icon dashicons dashicons-format-chat"></span>
                        <span class="ab-label">Chat</span>
                        <span class="sc-admin-bar-badge" style="display:none;background:#ef4444;color:#fff;border-radius:50%;padding:0 6px;margin-left:5px;font-size:11px;"></span>
                    </a>`;
                    adminBar.appendChild(li);
                }
            }

            // Update badge
            const adminBadge = document.querySelector('#wp-admin-bar-sc-chat .sc-admin-bar-badge');
            if (adminBadge) {
                if (count > 0) {
                    adminBadge.textContent = count;
                    adminBadge.style.display = 'inline';
                } else {
                    adminBadge.style.display = 'none';
                }
            }
        }

        showToastNotification(title, message) {
            // Remove existing toast
            const existingToast = document.querySelector('.sc-admin-toast');
            if (existingToast) {
                existingToast.remove();
            }

            const toast = document.createElement('div');
            toast.className = 'sc-admin-toast';
            toast.innerHTML = `
                <div class="sc-admin-toast-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                    </svg>
                </div>
                <div class="sc-admin-toast-content">
                    <div class="sc-admin-toast-title">${this.escapeHtml(title)}</div>
                    <div class="sc-admin-toast-message">${this.escapeHtml(message)}</div>
                </div>
            `;

            toast.addEventListener('click', () => {
                window.location.href = scAdminChatConfig.chatPageUrl;
            });

            document.body.appendChild(toast);

            // Show animation
            setTimeout(() => toast.classList.add('show'), 10);

            // Auto hide after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        showBrowserNotification(count) {
            if ('Notification' in window && Notification.permission === 'granted') {
                try {
                    const notification = new Notification('New Chat Message', {
                        body: `You have ${count} new message${count > 1 ? 's' : ''} in chat`,
                        icon: scAdminChatConfig.iconUrl,
                        tag: 'sc-admin-chat',
                        requireInteraction: false
                    });

                    notification.onclick = () => {
                        window.focus();
                        window.location.href = scAdminChatConfig.chatPageUrl;
                        notification.close();
                    };

                    // Auto close after 5 seconds
                    setTimeout(() => notification.close(), 5000);
                } catch (e) {
                    console.log('Browser notification error:', e);
                }
            }
        }

        playNotificationSound() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();

                if (audioContext.state === 'suspended') {
                    audioContext.resume();
                }

                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                // Pleasant two-tone notification
                oscillator.frequency.setValueAtTime(880, audioContext.currentTime);
                oscillator.frequency.setValueAtTime(1100, audioContext.currentTime + 0.15);
                oscillator.type = 'sine';

                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.4);

                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.4);
            } catch (e) {
                // Fallback
                try {
                    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleR4VX6Da2YJYBCFrsdPNfF0TK3ay0MR2VxgxeLLMvnJUGzh7sMa4b1IeO3yuxrRtURs9fbHFs21QHT9+ssW0blIePn2wxLNsUR4/frLFs25SHj59scSzbVEeP36yxbNuUh4+fbHEs21RHj9+ssWzblIePn2xxLNtUR4/frLFs25SHj59scSzbVEeP36yxbNuUh4+fbHEs21RHj9+ssWzblIePn2xxLNtUR4/frLFs25S');
                    audio.volume = 0.3;
                    audio.play().catch(() => {});
                } catch (e2) {}
            }
        }

        requestNotificationPermission() {
            if ('Notification' in window && Notification.permission === 'default') {
                document.addEventListener('click', function requestPerm() {
                    Notification.requestPermission();
                    document.removeEventListener('click', requestPerm);
                }, { once: true });
            }
        }

        startPolling() {
            if (this.pollingInterval || this.pollingDisabled) {
                return;
            }

            // Poll every 5 seconds
            this.pollingInterval = setInterval(() => {
                this.checkUnreadCount();
            }, 5000);
        }

        stopPolling() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
            }
        }

        async ajax(action, data) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', this.nonce);

            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });

            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            // admin-ajax.php answers an unauthenticated or unregistered action
            // with a bare "0" and a 4xx status, which parses as valid JSON and
            // would otherwise be mistaken for an empty-but-successful reply.
            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ' for ' + action);
            }

            return response.json();
        }

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof scAdminChatConfig !== 'undefined') {
            window.scAdminChatNotifications = new SCAdminChatNotifications();
        }
    });
})();
