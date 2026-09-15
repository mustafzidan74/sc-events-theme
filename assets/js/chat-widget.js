/**
 * SC Events Chat Widget
 * Real-time chat system for event inquiries
 *
 * @version 1.1.0
 */

class SCChatWidget {
    constructor(config) {
        this.eventId = config.eventId;
        this.ajaxUrl = config.ajaxUrl;
        this.nonce = config.nonce;
        this.eventTitle = config.eventTitle || 'Event';
        this.primaryColor = config.primaryColor || '#667eea';
        this.secondaryColor = config.secondaryColor || '#764ba2';

        // User info (if logged in)
        this.isLoggedIn = config.isLoggedIn || false;
        this.userName = config.userName || '';
        this.userEmail = config.userEmail || '';
        this.whatsapp = !!config.whatsapp;

        this.conversationId = null;
        this.visitorToken = localStorage.getItem('sc_chat_visitor_token') || null;
        this.lastMessageId = 0;
        this.isOpen = false;
        this.isMinimized = false;
        this.isClosed = false; // Conversation closed status
        this.pollingInterval = null;
        this.unreadCount = 0;
        this.isLoading = false;
        this.pendingMessages = new Set(); // Track messages being sent
        this.initialized = false;

        this.init();
    }

    init() {
        try {
            this.createWidget();
            this.bindEvents();
            this.checkExistingConversation();
            this.requestNotificationPermission();
            this.initialized = true;

            // Pre-fill form if user is logged in
            if (this.isLoggedIn) {
                this.prefillUserInfo();
            }

            // Start background polling for notifications (slower rate)
            this.startBackgroundPolling();
        } catch (error) {
            console.error('Chat widget initialization error:', error);
        }
    }

    prefillUserInfo() {
        const nameInput = document.getElementById('sc-chat-name');
        const emailInput = document.getElementById('sc-chat-email');

        if (nameInput && this.userName) {
            nameInput.value = this.userName;
            nameInput.readOnly = true;
            nameInput.style.backgroundColor = '#f3f4f6';
        }
        if (emailInput && this.userEmail) {
            emailInput.value = this.userEmail;
            emailInput.readOnly = true;
            emailInput.style.backgroundColor = '#f3f4f6';
        }
    }

    createWidget() {
        // Check if widget already exists
        if (document.getElementById('sc-chat-container')) {
            return;
        }

        const widgetHTML = `
            <!-- Chat Toggle Button -->
            <button id="sc-chat-toggle" class="sc-chat-toggle" aria-label="Open chat">
                <svg class="sc-chat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                </svg>
                <span class="sc-chat-badge" style="display: none;">0</span>
            </button>

            <!-- Chat Window -->
            <div id="sc-chat-window" class="sc-chat-window" style="display: none;">
                <!-- Header -->
                <div class="sc-chat-header">
                    <div class="sc-chat-header-info">
                        <span class="sc-chat-title">${this.escapeHtml(this.eventTitle)}</span>
                        <span class="sc-chat-status">
                            <span class="sc-chat-status-dot"></span>
                            Online
                        </span>
                    </div>
                    <button class="sc-chat-minimize" aria-label="Minimize chat">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </button>
                </div>

                <!-- Start Form -->
                <div id="sc-chat-start-form" class="sc-chat-start-form">
                    <div class="sc-chat-start-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                        </svg>
                    </div>
                    <h4>Start a Conversation</h4>
                    <p>Have questions about this event? We're here to help!</p>
                    <div class="sc-chat-form-group">
                        <input type="text" id="sc-chat-name" placeholder="Your Name *" required>
                    </div>
                    <div class="sc-chat-form-group">
                        <input type="email" id="sc-chat-email" placeholder="Your Email *" required>
                    </div>
                    <div class="sc-chat-form-group">
                        <input type="tel" id="sc-chat-phone" placeholder="${this.whatsapp ? 'Your WhatsApp number' : 'Your Phone (Optional)'}" autocomplete="tel" dir="ltr">
                    </div>
                    ${this.whatsapp ? `<label class="sc-chat-wa">
                        <input type="checkbox" id="sc-chat-wa">
                        <span>Send the reply to my WhatsApp too</span>
                    </label>` : ''}
                    <div class="sc-chat-form-group">
                        <textarea id="sc-chat-initial-message" placeholder="How can we help you? *" rows="3" required></textarea>
                    </div>
                    <!-- Honeypot field - hidden from humans, bots will fill it -->
                    <div class="sc-chat-hp" style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true" tabindex="-1">
                        <input type="text" id="sc-chat-website" name="website" autocomplete="off" tabindex="-1">
                    </div>
                    <button id="sc-chat-start-btn" class="sc-chat-btn">
                        <span>Start Chat</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>

                <!-- Messages Area -->
                <div id="sc-chat-messages" class="sc-chat-messages" style="display: none;">
                    <!-- Messages will be added here -->
                </div>

                <!-- Typing Indicator -->
                <div id="sc-chat-typing" class="sc-chat-typing" style="display: none;">
                    <span></span><span></span><span></span>
                </div>

                <!-- Closed Notice -->
                <div id="sc-chat-closed-notice" class="sc-chat-closed-notice" style="display: none;">
                    <div class="sc-chat-closed-info">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                        <span>This conversation has been closed</span>
                    </div>
                    <button id="sc-chat-reopen-btn" class="sc-chat-reopen-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9c-1.657 0-3-4.03-3-9s1.343-9 3-9m0 18c1.657 0 3-4.03 3-9s-1.343-9-3-9"></path>
                        </svg>
                        <span>Reopen Conversation</span>
                    </button>
                </div>

                <!-- Input Area -->
                <div id="sc-chat-input-area" class="sc-chat-input-area" style="display: none;">
                    <input type="file" id="sc-chat-file-input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" style="display: none;">
                    <button id="sc-chat-attach-btn" class="sc-chat-attach-btn" aria-label="Attach file" title="Attach file">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                        </svg>
                    </button>
                    <input type="text" id="sc-chat-input" placeholder="Type your message...">
                    <button id="sc-chat-send-btn" aria-label="Send message">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>

                <!-- Powered By -->
                <div class="sc-chat-powered">
                    <span>Powered by SC Events</span>
                </div>
            </div>
        `;

        // Add CSS variables
        document.documentElement.style.setProperty('--sc-chat-primary', this.primaryColor);
        document.documentElement.style.setProperty('--sc-chat-secondary', this.secondaryColor);

        // Append to body
        const container = document.createElement('div');
        container.id = 'sc-chat-container';
        container.innerHTML = widgetHTML;
        document.body.appendChild(container);
    }

    bindEvents() {
        // Toggle button
        const toggleBtn = document.getElementById('sc-chat-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggleChat());
        }

        // Minimize button
        const minimizeBtn = document.querySelector('.sc-chat-minimize');
        if (minimizeBtn) {
            minimizeBtn.addEventListener('click', () => this.minimizeChat());
        }

        // Start conversation
        const startBtn = document.getElementById('sc-chat-start-btn');
        if (startBtn) {
            startBtn.addEventListener('click', () => this.startConversation());
        }

        // WhatsApp replies: ticked once a phone number is typed, unless the visitor chose otherwise.
        const waBox = document.getElementById('sc-chat-wa');
        const phoneField = document.getElementById('sc-chat-phone');
        if (waBox && phoneField) {
            let waTouched = false;
            waBox.addEventListener('change', () => { waTouched = true; });
            phoneField.addEventListener('input', () => {
                if (!waTouched) {
                    waBox.checked = phoneField.value.replace(/\D/g, '').length >= 8;
                }
            });
        }

        // Send message
        const sendBtn = document.getElementById('sc-chat-send-btn');
        if (sendBtn) {
            sendBtn.addEventListener('click', () => this.sendMessage());
        }

        // Enter to send
        const chatInput = document.getElementById('sc-chat-input');
        if (chatInput) {
            chatInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }

        // Form validation
        const startForm = document.getElementById('sc-chat-start-form');
        if (startForm) {
            startForm.querySelectorAll('input, textarea').forEach(input => {
                input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                        e.preventDefault();
                        this.startConversation();
                    }
                });
            });
        }

        // File attachment
        const attachBtn = document.getElementById('sc-chat-attach-btn');
        const fileInput = document.getElementById('sc-chat-file-input');
        if (attachBtn && fileInput) {
            attachBtn.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', (e) => this.uploadFile(e.target.files[0]));
        }

        // Reopen conversation
        const reopenBtn = document.getElementById('sc-chat-reopen-btn');
        if (reopenBtn) {
            reopenBtn.addEventListener('click', () => this.reopenConversation());
        }
    }

    toggleChat() {
        const chatWindow = document.getElementById('sc-chat-window');
        const toggleBtn = document.getElementById('sc-chat-toggle');

        if (!chatWindow || !toggleBtn) return;

        this.isOpen = !this.isOpen;

        if (this.isOpen) {
            chatWindow.style.display = 'flex';
            toggleBtn.classList.add('active');
            this.isMinimized = false;
            this.startPolling();
            this.clearUnreadBadge();
            this.markMessagesAsRead();

            // Focus input
            setTimeout(() => {
                const input = this.conversationId
                    ? document.getElementById('sc-chat-input')
                    : document.getElementById('sc-chat-name');
                if (input) input.focus();
            }, 300);
        } else {
            chatWindow.style.display = 'none';
            toggleBtn.classList.remove('active');
            // Switch to background polling when closed
            this.startBackgroundPolling();
        }
    }

    minimizeChat() {
        const chatWindow = document.getElementById('sc-chat-window');
        const toggleBtn = document.getElementById('sc-chat-toggle');

        if (!chatWindow || !toggleBtn) return;

        this.isOpen = false;
        this.isMinimized = true;
        chatWindow.style.display = 'none';
        toggleBtn.classList.remove('active');

        // Background polling when minimized
        this.startBackgroundPolling();
    }

    startBackgroundPolling() {
        // Only poll in background if we have a conversation
        if (!this.conversationId) return;

        this.stopPolling();
        this.pollingInterval = setInterval(() => {
            this.loadMessages();
        }, 10000); // Poll every 10 seconds in background
    }

    async checkExistingConversation() {
        // Check for logged-in users (by user_id) or visitors (by token)
        if (!this.visitorToken && !this.isLoggedIn) return;

        try {
            const response = await this.ajax('sc_chat_check', {
                event_id: this.eventId,
                visitor_token: this.visitorToken || ''
            });

            if (response.success && response.data.conversation_id) {
                this.conversationId = response.data.conversation_id;
                this.showChatInterface();
                this.renderMessages(response.data.messages);

                // Check if conversation is closed
                if (response.data.status === 'closed') {
                    this.isClosed = true;
                    this.showClosedNotice();
                }

                // Check for unread messages
                const unreadMessages = response.data.messages.filter(m =>
                    m.sender_type === 'organizer' && !parseInt(m.is_read)
                );
                if (unreadMessages.length > 0) {
                    this.unreadCount = unreadMessages.length;
                    this.updateUnreadBadge();
                    // Play notification sound for unread messages
                    this.playNotificationSound();
                }

                // Start background polling
                this.startBackgroundPolling();
            }
        } catch (error) {
            console.error('Check conversation error:', error);
        }
    }

    async startConversation() {
        const nameInput = document.getElementById('sc-chat-name');
        const emailInput = document.getElementById('sc-chat-email');
        const phoneInput = document.getElementById('sc-chat-phone');
        const messageInput = document.getElementById('sc-chat-initial-message');
        const honeypotInput = document.getElementById('sc-chat-website');

        if (!nameInput || !emailInput || !messageInput) return;

        const name = nameInput.value.trim();
        const email = emailInput.value.trim();
        const phone = phoneInput ? phoneInput.value.trim() : '';
        const message = messageInput.value.trim();
        const honeypot = honeypotInput ? honeypotInput.value : '';
        const waInput = document.getElementById('sc-chat-wa');
        const waOptIn = !!(waInput && waInput.checked);

        // Validation
        if (!name || !email || !message) {
            this.showError('Please fill in all required fields');
            return;
        }

        if (!this.isValidEmail(email)) {
            this.showError('Please enter a valid email address');
            return;
        }

        if (waOptIn && phone.replace(/\D/g, '').length < 8) {
            this.showError('Add your WhatsApp number, or untick the WhatsApp box');
            if (phoneInput) phoneInput.focus();
            return;
        }

        const btn = document.getElementById('sc-chat-start-btn');
        if (!btn) return;

        btn.disabled = true;
        btn.innerHTML = '<span>Starting...</span>';

        try {
            const response = await this.ajax('sc_chat_start', {
                event_id: this.eventId,
                name: name,
                email: email,
                phone: phone,
                message: message,
                wa_opt_in: waOptIn ? 1 : 0,
                website: honeypot  // Honeypot field
            });

            if (response.success) {
                this.conversationId = response.data.conversation_id;
                this.visitorToken = response.data.visitor_token;
                localStorage.setItem('sc_chat_visitor_token', this.visitorToken);

                this.showChatInterface();
                this.renderMessages(response.data.messages);
                this.startPolling();
            } else {
                this.showError(response.data?.message || 'Failed to start conversation');
            }
        } catch (error) {
            console.error('Start conversation error:', error);
            this.showError('Connection error. Please try again.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = `
                <span>Start Chat</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            `;
        }
    }

    showChatInterface() {
        const startForm = document.getElementById('sc-chat-start-form');
        const messagesArea = document.getElementById('sc-chat-messages');
        const inputArea = document.getElementById('sc-chat-input-area');

        if (startForm) startForm.style.display = 'none';
        if (messagesArea) messagesArea.style.display = 'flex';
        if (inputArea) inputArea.style.display = 'flex';
    }

    async sendMessage() {
        const input = document.getElementById('sc-chat-input');
        if (!input) return;

        const message = input.value.trim();

        if (!message || !this.conversationId || this.isLoading) return;

        // Generate temporary ID for optimistic update
        const tempId = 'temp_' + Date.now();

        input.value = '';
        this.isLoading = true;

        // Add message immediately (optimistic UI) with temp ID
        this.addMessage({
            id: tempId,
            sender_type: 'visitor',
            message: message,
            created_at: new Date().toISOString(),
            isPending: true
        });

        this.pendingMessages.add(tempId);

        try {
            const response = await this.ajax('sc_chat_send', {
                conversation_id: this.conversationId,
                message: message,
                sender_type: 'visitor'
            });

            if (response.success && response.data.message) {
                // Remove temp message and add real one
                this.removeTempMessage(tempId);
                this.pendingMessages.delete(tempId);

                const realMsg = response.data.message;
                this.addMessage(realMsg);
                this.lastMessageId = Math.max(this.lastMessageId, parseInt(realMsg.id));
            } else {
                // Server returned error — mark as failed
                console.error('Send message failed:', response.data?.message || 'Unknown error');
                this.markMessageFailed(tempId);
            }
        } catch (error) {
            console.error('Send message error:', error);
            // Mark temp message as failed
            this.markMessageFailed(tempId);
        } finally {
            this.isLoading = false;
        }
    }

    removeTempMessage(tempId) {
        const container = document.getElementById('sc-chat-messages');
        if (!container) return;

        const tempMsg = container.querySelector(`[data-message-id="${tempId}"]`);
        if (tempMsg) {
            tempMsg.remove();
        }
    }

    markMessageFailed(tempId) {
        const container = document.getElementById('sc-chat-messages');
        if (!container) return;

        const tempMsg = container.querySelector(`[data-message-id="${tempId}"]`);
        if (tempMsg) {
            tempMsg.classList.add('failed');
            tempMsg.querySelector('.sc-chat-message-time').textContent = 'Failed - tap to retry';
        }
    }

    async loadMessages() {
        if (!this.conversationId) return;

        try {
            const response = await this.ajax('sc_chat_load', {
                conversation_id: this.conversationId,
                last_id: this.lastMessageId,
                reader_type: 'visitor'
            });

            if (response.success && response.data.messages && response.data.messages.length > 0) {
                // Filter out messages we sent (avoid duplicates)
                const newMessages = response.data.messages.filter(msg => {
                    const msgId = parseInt(msg.id);
                    return msgId > this.lastMessageId;
                });

                if (newMessages.length > 0) {
                    this.renderMessages(newMessages);
                    this.showNotification(newMessages);
                }
            }
        } catch (error) {
            console.error('Load messages error:', error);
        }
    }

    renderMessages(messages) {
        if (!messages || !Array.isArray(messages)) return;

        messages.forEach(msg => {
            if (!msg || !msg.id) return;

            // Avoid duplicates
            const msgId = parseInt(msg.id);
            if (msgId <= this.lastMessageId) return;

            // Skip if already in container
            const container = document.getElementById('sc-chat-messages');
            if (container && container.querySelector(`[data-message-id="${msg.id}"]`)) {
                return;
            }

            this.addMessage(msg);
            this.lastMessageId = Math.max(this.lastMessageId, msgId);
        });
    }

    addMessage(msg) {
        const container = document.getElementById('sc-chat-messages');
        if (!container) return;

        // Check if message already exists
        if (msg.id && container.querySelector(`[data-message-id="${msg.id}"]`)) {
            return;
        }

        const div = document.createElement('div');
        div.className = `sc-chat-message ${msg.sender_type}`;
        if (msg.isPending) div.classList.add('pending');
        if (msg.message_type === 'system') div.classList.add('system');
        if (msg.id) div.dataset.messageId = msg.id;

        const time = this.formatTime(msg.created_at);
        let contentHtml = '';

        // Handle different message types
        if (msg.message_type === 'system') {
            contentHtml = `<div class="sc-chat-message-content system-message">${this.escapeHtml(msg.message)}</div>`;
            // Check if this is a close message and show closed notice
            if (msg.message.includes('closed')) {
                this.isClosed = true;
                this.showClosedNotice();
            }
        } else if (msg.message_type === 'image' && msg.file_url) {
            contentHtml = `
                <div class="sc-chat-message-content sc-chat-image-message">
                    <a href="${this.escapeHtml(msg.file_url)}" target="_blank">
                        <img src="${this.escapeHtml(msg.file_url)}" alt="Image" loading="lazy">
                    </a>
                </div>
            `;
        } else if (msg.message_type === 'file' && msg.file_url) {
            contentHtml = `
                <div class="sc-chat-message-content sc-chat-file-message">
                    <a href="${this.escapeHtml(msg.file_url)}" target="_blank" download>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <span>${this.escapeHtml(msg.file_name || 'Download File')}</span>
                    </a>
                </div>
            `;
        } else {
            contentHtml = `<div class="sc-chat-message-content">${this.escapeHtml(msg.message)}</div>`;
        }

        div.innerHTML = `
            ${contentHtml}
            <span class="sc-chat-message-time">${msg.isPending ? 'Sending...' : time}</span>
        `;

        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    startPolling(interval = 3000) {
        this.stopPolling();
        this.pollingInterval = setInterval(() => {
            this.loadMessages();
        }, interval);
    }

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    showNotification(messages) {
        if (!messages || !Array.isArray(messages)) return;

        // Only for incoming messages from organizer
        const incoming = messages.filter(m => m.sender_type === 'organizer');
        if (incoming.length === 0) return;

        // Update badge if chat is not open
        if (!this.isOpen || this.isMinimized) {
            this.unreadCount += incoming.length;
            this.updateUnreadBadge();
        }

        // Always play sound for new messages from organizer
        this.playNotificationSound();

        // Browser notification
        if ((!this.isOpen || this.isMinimized) && 'Notification' in window && Notification.permission === 'granted') {
            const lastMsg = incoming[incoming.length - 1];
            try {
                new Notification(this.eventTitle, {
                    body: lastMsg.message.substring(0, 100),
                    icon: '/wp-content/themes/sc_events/assets/images/chat-icon.png',
                    tag: 'sc-chat-' + this.conversationId,
                    requireInteraction: false
                });
            } catch (e) {
                console.log('Notification error:', e);
            }
        }
    }

    updateUnreadBadge() {
        const badge = document.querySelector('.sc-chat-badge');
        if (!badge) return;

        if (this.unreadCount > 0) {
            badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            badge.style.display = 'flex';

            // Add animation
            badge.classList.add('pulse');
            setTimeout(() => badge.classList.remove('pulse'), 500);
        } else {
            badge.style.display = 'none';
        }
    }

    clearUnreadBadge() {
        this.unreadCount = 0;
        this.updateUnreadBadge();
    }

    async markMessagesAsRead() {
        if (!this.conversationId) return;

        try {
            await this.ajax('sc_chat_mark_read', {
                conversation_id: this.conversationId,
                reader_type: 'visitor'
            });
        } catch (error) {
            console.error('Mark read error:', error);
        }
    }

    playNotificationSound() {
        try {
            // Use Web Audio API for better compatibility
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();

            // Resume audio context if suspended (required for some browsers)
            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }

            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            // Create a pleasant notification sound
            oscillator.frequency.setValueAtTime(880, audioContext.currentTime); // A5
            oscillator.frequency.setValueAtTime(1100, audioContext.currentTime + 0.1); // C#6
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        } catch (e) {
            // Fallback: try HTML5 Audio
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleR4VX6Da2YJYBCFrsdPNfF0TK3ay0MR2VxgxeLLMvnJUGzh7sMa4b1IeO3yuxrRtURs9fbHFs21QHT9+ssW0blIePn2wxLNsUR4/frLFs25SHj59scSzbVEeP36yxbNuUh4+fbHEs21RHj9+ssWzblIePn2xxLNtUR4/frLFs25SHj59scSzbVEeP36yxbNuUh4+fbHEs21RHj9+ssWzblIePn2xxLNtUR4/frLFs25S');
                audio.volume = 0.3;
                audio.play().catch(() => {});
            } catch (e2) {
                console.log('Audio not supported');
            }
        }
    }

    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            // Request permission after user interaction
            document.addEventListener('click', function requestPermission() {
                Notification.requestPermission();
                document.removeEventListener('click', requestPermission);
            }, { once: true });
        }
    }

    showError(message) {
        this.showToast(message, 'error');
    }

    showToast(message, type = 'error') {
        const chatWindow = document.getElementById('sc-chat-window');
        if (!chatWindow) return;

        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `sc-chat-toast ${type}`;
        toast.textContent = message;
        chatWindow.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    async uploadFile(file) {
        if (!file || !this.conversationId || this.isLoading || this.isClosed) return;

        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            this.showError('File size exceeds 5MB limit');
            return;
        }

        this.isLoading = true;

        // Show uploading indicator
        const tempId = 'temp_' + Date.now();
        const isImage = file.type.startsWith('image/');
        this.addMessage({
            id: tempId,
            sender_type: 'visitor',
            message: isImage ? 'Uploading image...' : 'Uploading file...',
            message_type: 'text',
            created_at: new Date().toISOString(),
            isPending: true
        });

        try {
            const formData = new FormData();
            formData.append('action', 'sc_chat_upload_file');
            formData.append('nonce', this.nonce);
            formData.append('conversation_id', this.conversationId);
            formData.append('sender_type', 'visitor');
            formData.append('file', file);

            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const result = await response.json();

            if (result.success) {
                this.removeTempMessage(tempId);
                this.addMessage(result.data.message);
                this.lastMessageId = Math.max(this.lastMessageId, parseInt(result.data.message.id));
            } else {
                this.markMessageFailed(tempId);
                this.showError(result.data?.message || 'Failed to upload file');
            }
        } catch (error) {
            console.error('Upload file error:', error);
            this.markMessageFailed(tempId);
            this.showError('Upload failed. Please try again.');
        } finally {
            this.isLoading = false;
            // Clear file input
            const fileInput = document.getElementById('sc-chat-file-input');
            if (fileInput) fileInput.value = '';
        }
    }

    async closeConversation() {
        if (!this.conversationId || this.isClosed) return;

        if (!confirm('Are you sure you want to close this conversation?')) return;

        try {
            const response = await this.ajax('sc_chat_visitor_close', {
                conversation_id: this.conversationId
            });

            if (response.success) {
                this.isClosed = true;
                this.showClosedNotice();
            } else {
                this.showError(response.data?.message || 'Failed to close conversation');
            }
        } catch (error) {
            console.error('Close conversation error:', error);
            this.showError('Failed to close conversation');
        }
    }

    showClosedNotice() {
        const inputArea = document.getElementById('sc-chat-input-area');
        const closedNotice = document.getElementById('sc-chat-closed-notice');

        if (inputArea) inputArea.style.display = 'none';
        if (closedNotice) closedNotice.style.display = 'flex';
    }

    hideClosedNotice() {
        const inputArea = document.getElementById('sc-chat-input-area');
        const closedNotice = document.getElementById('sc-chat-closed-notice');

        if (inputArea) inputArea.style.display = 'flex';
        if (closedNotice) closedNotice.style.display = 'none';
    }

    async reopenConversation() {
        if (!this.conversationId || !this.isClosed) return;

        try {
            const response = await this.ajax('sc_chat_visitor_reopen', {
                conversation_id: this.conversationId
            });

            if (response.success) {
                this.isClosed = false;
                this.hideClosedNotice();
                // Reload messages to show the reopen system message
                this.pollMessages();
                this.showToast('Conversation reopened', 'success');
            } else {
                this.showError(response.data?.message || 'Failed to reopen conversation');
            }
        } catch (error) {
            console.error('Reopen conversation error:', error);
            this.showError('Failed to reopen conversation');
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

        return response.json();
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/\n/g, '<br>');
    }

    formatTime(dateString) {
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            return date.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return '';
        }
    }

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if config exists (eventId can be 0 for global chat)
    if (typeof scChatConfig !== 'undefined' && scChatConfig.eventId !== undefined) {
        try {
            window.scChatWidget = new SCChatWidget(scChatConfig);
        } catch (error) {
            console.error('Failed to initialize chat widget:', error);
        }
    }
});
