<template>
  <div id="wrapper" class="chat-container">
    <header class="chat-header">
      <div class="user-info">
        <span class="welcome-text">Welcome, <b>{{ username }}</b>!</span>
        <a href="/auth/logout" class="logout-btn">
          <i class="icon-logout"></i> Logout
        </a>
      </div>
    </header>

    <div class="message-container">
      <div v-if="loading" class="loading-indicator">
        <div class="spinner"></div>
        <span>Loading messages...</span>
      </div>
      <div v-else id="messagewindow" class="messages">
        <div v-for="(message, index) in messages" :key="index" class="message-item">
          <div class="message-header">
            <span class="username">{{ message.user }}</span>
            <span class="timestamp" v-if="message.timestamp">{{ formatTimestamp(message.timestamp) }}</span>
          </div>
          <div class="message-content" v-html="formatMessage(message.msg)"></div>
        </div>
        <div v-if="messages.length === 0" class="no-messages">
          No messages yet. Be the first to send a message!
        </div>
      </div>
    </div>

    <div class="load-more-container" v-if="!loading && hasMoreMessages">
      <button 
        class="load-more-btn" 
        @click="loadMoreMessages"
        :disabled="loadingMore"
      >
        <span v-if="loadingMore" class="spinner-small"></span>
        <span>{{ loadingMore ? 'Loading...' : 'Load More Messages' }}</span>
      </button>
    </div>

    <div class="message-form-container">
      <form @submit.prevent="sendMessage" class="message-form">
        <div class="form-group" v-show="!sending">
          <label for="message-input">Message:</label>
          <textarea 
            id="message-input"
            v-model="message" 
            :class="{ 'error-field': error }" 
            placeholder="Type your message here..."
            rows="2"
            @keydown.enter.exact.prevent="sendMessage"
          ></textarea>
          <div class="formatting-help">
            <button type="button" @click="insertFormatting('**', '**')" title="Bold">B</button>
            <button type="button" @click="insertFormatting('*', '*')" title="Italic">I</button>
            <button type="button" @click="insertFormatting('`', '`')" title="Code">Code</button>
            <button type="button" @click="insertFormatting('\n> ', '')" title="Quote">Quote</button>
            <span class="format-info">Supports Markdown: **bold**, *italic*, `code`, > quote</span>
          </div>
          <div class="error" v-if="error">{{ error }}</div>
        </div>

        <div class="loading-indicator-small" v-show="sending">  
          <div class="spinner"></div>
          <span>Sending message...</span>
        </div>

        <div class="form-actions">
          <button 
            type="submit" 
            class="send-btn" 
            :disabled="sending || !message.trim()"
          >
            Send Message
          </button>
          <button 
            type="button" 
            class="clear-btn" 
            @click="message = ''"
            :disabled="sending || !message.trim()"
          >
            Clear
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      // User information (from global properties set in main.js)
      username: this.$username,
      userId: this.$userId,

      // WebSocket authentication token (generated on login, validated by server)
      wsToken: this.$wsToken,

      // Chat messages
      messages: [],
      message: '',
      error: '',

      // Loading states
      loading: true,
      sending: false,
      loadingMore: false,

      // Pagination
      currentPage: 1,
      hasMoreMessages: false,

      // WebSocket connection state
      webSocket: null,
      webSocketConnected: false,
      reconnectAttempts: 0,
      reconnectInterval: null,
      lastMessageTime: null,

      // Form helpers
      selectionStart: 0,
      selectionEnd: 0
    };
  },
  mounted() {
    this.connectWebSocket();

    // Clean up when component is destroyed
    this.$nextTick(() => {
      window.addEventListener('beforeunload', this.cleanUp);
    });
  },
  beforeUnmount() {
    this.cleanUp();
  },
  methods: {
    connectWebSocket() {
      /**
       * ====================================================================
       * WEBSOCKET CONNECTION WITH TOKEN AUTHENTICATION
       * ====================================================================
       *
       * The WebSocket server requires authentication via URL query parameters.
       * This is because WebSocket connections cannot use cookies or HTTP headers
       * in the same way as regular HTTP requests.
       *
       * Authentication Flow:
       * 1. User logs in via HTTP (Auth::processLogin)
       * 2. Server generates a token and stores it in the session
       * 3. Token is passed to Vue via window.WEBSOCKET_TOKEN
       * 4. We include the token in the WebSocket URL as a query parameter
       * 5. WebSocket server validates the token before accepting the connection
       *
       * Security Notes:
       * - Always use WSS (WebSocket Secure) in production over HTTPS
       * - Tokens expire after 24 hours (configurable in WebSocketTokenHelper)
       * - Tokens are revoked when users log out
       *
       * ====================================================================
       */

      // Close existing connection if any
      if (this.webSocket) {
        this.webSocket.close();
      }

      // Build the WebSocket URL with authentication parameters
      const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
      const host = window.location.hostname;
      const port = 8080; // This should match the port in your WebSocket server

      // Include token and user_id as query parameters for authentication
      // The server will validate these before accepting the connection
      const wsUrl = `${protocol}//${host}:${port}?token=${encodeURIComponent(this.wsToken)}&user_id=${this.userId}`;

      console.log('Connecting to WebSocket with authentication...');
      this.webSocket = new WebSocket(wsUrl);

      // Connection opened
      this.webSocket.addEventListener('open', (event) => {
        console.log('WebSocket connection established');
        this.webSocketConnected = true;
        this.reconnectAttempts = 0;

        if (this.reconnectInterval) {
          clearInterval(this.reconnectInterval);
          this.reconnectInterval = null;
        }

        // Load initial messages
        this.loadMessages();
      });

      // Listen for messages from the WebSocket server
      this.webSocket.addEventListener('message', (event) => {
        const data = JSON.parse(event.data);

        // Handle different message types (actions) from the server
        switch (data.action) {
          case 'messages':
            // Handle messages list response
            this.messages = data.data.messages || [];
            this.hasMoreMessages = data.data.pagination?.hasNext || false;
            this.loading = false;

            // Store timestamp of the newest message for refresh comparison
            if (this.messages.length > 0 && this.messages[0].timestamp) {
              this.lastMessageTime = this.messages[0].timestamp;
            }
            break;

          case 'newMessage':
            // Handle new message broadcast from another user
            if (!this.lastMessageTime || data.data.timestamp > this.lastMessageTime) {
              // Add new message to the beginning of our list
              this.messages.unshift(data.data);
              this.lastMessageTime = data.data.timestamp;
            }
            break;

          case 'error':
            // Handle authentication or other errors from the server
            // The server sends this when token validation fails
            console.error('WebSocket server error:', data.data.message);

            if (data.data.code === 'AUTH_FAILED') {
              // Authentication failed - likely token expired or invalid
              // Redirect to login page to get a new token
              this.error = 'Session expired. Please log in again.';
              console.log('Authentication failed, redirecting to login...');

              // Stop reconnection attempts since we need a new token
              if (this.reconnectInterval) {
                clearInterval(this.reconnectInterval);
                this.reconnectInterval = null;
              }
              this.reconnectAttempts = 5; // Prevent further reconnection attempts

              // Redirect to login after a short delay so user sees the message
              setTimeout(() => {
                window.location.href = '/auth/login';
              }, 2000);
            } else {
              this.error = data.data.message || 'An error occurred';
            }
            break;
        }
      });

      // Connection closed
      this.webSocket.addEventListener('close', (event) => {
        console.log('WebSocket connection closed');
        this.webSocketConnected = false;

        // Attempt to reconnect
        if (!this.reconnectInterval) {
          this.reconnectInterval = setInterval(() => {
            if (this.reconnectAttempts < 5) {
              console.log(`Attempting to reconnect (${this.reconnectAttempts + 1}/5)...`);
              this.reconnectAttempts++;
              this.connectWebSocket();
            } else {
              clearInterval(this.reconnectInterval);
              this.reconnectInterval = null;
              console.error('Failed to reconnect after 5 attempts');
            }
          }, 5000);
        }
      });

      // Connection error
      this.webSocket.addEventListener('error', (event) => {
        console.error('WebSocket error:', event);
        this.webSocketConnected = false;
      });
    },

    cleanUp() {
      if (this.webSocket) {
        this.webSocket.close();
      }

      if (this.reconnectInterval) {
        clearInterval(this.reconnectInterval);
      }

      window.removeEventListener('beforeunload', this.cleanUp);
    },

    loadMessages() {
      if (!this.webSocketConnected) {
        console.log('WebSocket not connected, using HTTP fallback');
        this.loadMessagesHttp();
        return;
      }

      this.loading = true;

      // Request messages via WebSocket
      this.webSocket.send(JSON.stringify({
        action: 'getMessages',
        page: this.currentPage,
        perPage: 10
      }));
    },

    // HTTP fallback for loading messages
    async loadMessagesHttp() {
      try {
        const response = await fetch(`${this.$chatRoutes.api}?page=${this.currentPage}&per_page=10`);
        const data = await response.json();

        this.messages = data.messages || [];
        this.hasMoreMessages = data.pagination?.hasNext || false;
        this.loading = false;

        // Store timestamp of the newest message for refresh comparison
        if (this.messages.length > 0 && this.messages[0].timestamp) {
          this.lastMessageTime = this.messages[0].timestamp;
        }
      } catch (error) {
        console.error('Error loading messages:', error);
        this.loading = false;
      }
    },

    loadMoreMessages() {
      if (this.loadingMore) return;

      this.loadingMore = true;
      this.currentPage++;

      if (!this.webSocketConnected) {
        console.log('WebSocket not connected, using HTTP fallback');
        this.loadMoreMessagesHttp();
        return;
      }

      // Request more messages via WebSocket
      this.webSocket.send(JSON.stringify({
        action: 'getMessages',
        page: this.currentPage,
        perPage: 10
      }));

      // The response will be handled by the message event listener
      // We'll need to update the loadingMore state there
      setTimeout(() => {
        // Fallback to reset loading state if no response
        if (this.loadingMore) {
          this.loadingMore = false;
        }
      }, 5000);
    },

    // HTTP fallback for loading more messages
    async loadMoreMessagesHttp() {
      try {
        const response = await fetch(`${this.$chatRoutes.api}?page=${this.currentPage}&per_page=10`);
        const data = await response.json();

        if (data.messages && data.messages.length > 0) {
          this.messages = [...this.messages, ...data.messages];
        }

        this.hasMoreMessages = data.pagination?.hasNext || false;
      } catch (error) {
        console.error('Error loading more messages:', error);
        this.currentPage--; // Revert page increment on failure
      } finally {
        this.loadingMore = false;
      }
    },

    sendMessage() {
      // Clear previous errors
      this.error = '';

      // Validate message
      if (!this.message.trim()) {
        this.error = 'Message is required';
        return;
      }

      if (this.message.length > 500) {
        this.error = 'Message cannot exceed 500 characters';
        return;
      }

      this.sending = true;

      if (this.webSocketConnected) {
        // Send message via WebSocket
        this.webSocket.send(JSON.stringify({
          action: 'sendMessage',
          username: this.username,
          message: this.message
        }));

        // Clear message field
        this.message = '';
        this.sending = false;
      } else {
        // Fallback to HTTP if WebSocket is not connected
        this.sendMessageHttp();
      }
    },

    // HTTP fallback for sending messages
    async sendMessageHttp() {
      try {
        const formData = new FormData();
        formData.append('message', this.message);
        formData.append('action', 'postmsg');
        formData.append(this.$csrfToken, document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        const response = await fetch(this.$chatRoutes.update, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        const data = await response.json();

        if (data && data.success === false) {
          this.error = data.errors?.message || 'Failed to send message';
        } else {
          // Add message to the beginning of the list
          this.messages.unshift({
            user: this.username,
            msg: this.message,
            timestamp: Math.floor(Date.now() / 1000) // Current timestamp in seconds
          });

          // Clear message field
          this.message = '';
        }
      } catch (error) {
        console.error('Error sending message:', error);
        this.error = 'Failed to send message. Please try again.';
      } finally {
        this.sending = false;
      }
    },

    formatTimestamp(timestamp) {
      if (!timestamp) return '';

      const date = new Date(timestamp * 1000);
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMins / 60);
      const diffDays = Math.floor(diffHours / 24);

      // Format based on how old the message is
      if (diffMins < 1) {
        return 'just now';
      } else if (diffMins < 60) {
        return `${diffMins} minute${diffMins === 1 ? '' : 's'} ago`;
      } else if (diffHours < 24) {
        return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
      } else if (diffDays < 7) {
        return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
      } else {
        // Format as date for older messages
        return date.toLocaleDateString();
      }
    },

    formatMessage(message) {
      if (!message) return '';

      // Escape HTML to prevent XSS
      let formattedMessage = this.escapeHtml(message);

      // Format markdown-like syntax
      // Bold: **text**
      formattedMessage = formattedMessage.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

      // Italic: *text*
      formattedMessage = formattedMessage.replace(/\*(.*?)\*/g, '<em>$1</em>');

      // Code: `code`
      formattedMessage = formattedMessage.replace(/`(.*?)`/g, '<code>$1</code>');

      // Blockquote: > text
      formattedMessage = formattedMessage.replace(/^&gt; (.*)$/gm, '<blockquote>$1</blockquote>');

      // Convert URLs to links
      formattedMessage = formattedMessage.replace(
        /(https?:\/\/[^\s]+)/g, 
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
      );

      // Convert line breaks to <br>
      formattedMessage = formattedMessage.replace(/\n/g, '<br>');

      return formattedMessage;
    },

    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    },

    insertFormatting(prefix, suffix) {
      // Get the textarea element
      const textarea = document.getElementById('message-input');
      if (!textarea) return;

      // Save the current selection
      const selectionStart = textarea.selectionStart;
      const selectionEnd = textarea.selectionEnd;

      // Get the selected text
      const selectedText = this.message.substring(selectionStart, selectionEnd);

      // Insert the formatting
      const beforeText = this.message.substring(0, selectionStart);
      const afterText = this.message.substring(selectionEnd);

      // Update the message
      this.message = beforeText + prefix + selectedText + suffix + afterText;

      // Focus the textarea and restore selection (adjusted for the formatting)
      this.$nextTick(() => {
        textarea.focus();
        textarea.setSelectionRange(
          selectionStart + prefix.length,
          selectionEnd + prefix.length
        );
      });
    }
  }
};
</script>

<style lang="scss">
// Variables
$primary-color: #4a6fa5;
$secondary-color: #6c757d;
$background-color: #f8f9fa;
$border-color: #dee2e6;
$text-color: #343a40;
$light-text-color: #6c757d;
$error-color: #dc3545;
$success-color: #28a745;
$hover-color: #e9ecef;
$box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
$border-radius: 4px;
$transition-speed: 0.2s;

// Global styles
* {
  box-sizing: border-box;
}

// Chat container
.chat-container {
  max-width: 600px;
  margin: 20px auto;
  background-color: white;
  border-radius: 8px;
  box-shadow: $box-shadow;
  overflow: hidden;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
  color: $text-color;

  @media (max-width: 640px) {
    margin: 10px;
    width: auto;
  }
}

// Chat header
.chat-header {
  background-color: $primary-color;
  color: white;
  padding: 15px;
  border-bottom: 1px solid darken($primary-color, 10%);
}

.user-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.welcome-text {
  font-size: 16px;
}

.logout-btn {
  color: white;
  text-decoration: none;
  font-size: 14px;
  padding: 5px 10px;
  border-radius: $border-radius;
  background-color: rgba(255, 255, 255, 0.1);
  transition: background-color $transition-speed;

  &:hover {
    background-color: rgba(255, 255, 255, 0.2);
  }
}

// Message container
.message-container {
  position: relative;
  min-height: 300px;
}

// Messages
.messages {
  height: 350px;
  overflow-y: auto;
  padding: 15px;
  background-color: $background-color;

  @media (max-width: 640px) {
    height: 300px;
  }
}

.message-item {
  margin-bottom: 15px;
  padding: 10px;
  background-color: white;
  border-radius: $border-radius;
  box-shadow: $box-shadow;
  transition: transform $transition-speed;

  &:hover {
    transform: translateY(-2px);
  }
}

.message-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 5px;
  font-size: 14px;
}

.username {
  font-weight: bold;
  color: $primary-color;
}

.timestamp {
  color: $light-text-color;
  font-size: 12px;
}

.message-content {
  line-height: 1.5;
  word-break: break-word;

  a {
    color: $primary-color;
    text-decoration: none;

    &:hover {
      text-decoration: underline;
    }
  }

  code {
    background-color: $background-color;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 0.9em;
  }

  blockquote {
    border-left: 3px solid $border-color;
    margin: 5px 0;
    padding-left: 10px;
    color: $secondary-color;
  }
}

.no-messages {
  text-align: center;
  color: $light-text-color;
  padding: 20px;
}

// Loading indicators
.loading-indicator {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 300px;
  color: $secondary-color;
}

.loading-indicator-small {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 10px;
  color: $secondary-color;
}

.spinner {
  border: 3px solid rgba(0, 0, 0, 0.1);
  border-top: 3px solid $primary-color;
  border-radius: 50%;
  width: 30px;
  height: 30px;
  animation: spin 1s linear infinite;
  margin-bottom: 10px;
}

.spinner-small {
  border: 2px solid rgba(0, 0, 0, 0.1);
  border-top: 2px solid $primary-color;
  border-radius: 50%;
  width: 16px;
  height: 16px;
  animation: spin 1s linear infinite;
  display: inline-block;
  margin-right: 5px;
  vertical-align: middle;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

// Load more button
.load-more-container {
  text-align: center;
  padding: 10px;
  background-color: $background-color;
  border-top: 1px solid $border-color;
}

.load-more-btn {
  padding: 8px 15px;
  background-color: white;
  border: 1px solid $border-color;
  border-radius: $border-radius;
  cursor: pointer;
  font-size: 14px;
  transition: background-color $transition-speed;

  &:hover:not(:disabled) {
    background-color: $hover-color;
  }

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}

// Message form
.message-form-container {
  padding: 15px;
  border-top: 1px solid $border-color;
}

.message-form {
  display: flex;
  flex-direction: column;
}

.form-group {
  margin-bottom: 15px;

  label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
  }

  textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid $border-color;
    border-radius: $border-radius;
    font-family: inherit;
    font-size: 14px;
    resize: vertical;

    &:focus {
      outline: none;
      border-color: $primary-color;
    }

    &.error-field {
      border-color: $error-color;
    }
  }
}

.formatting-help {
  display: flex;
  align-items: center;
  margin-top: 5px;
  flex-wrap: wrap;

  button {
    background-color: $background-color;
    border: 1px solid $border-color;
    border-radius: $border-radius;
    margin-right: 5px;
    padding: 3px 8px;
    font-size: 12px;
    cursor: pointer;
    transition: background-color $transition-speed;

    &:hover {
      background-color: $hover-color;
    }
  }

  .format-info {
    font-size: 12px;
    color: $light-text-color;
    margin-left: 5px;
  }

  @media (max-width: 640px) {
    .format-info {
      display: none;
    }
  }
}

.error {
  color: $error-color;
  font-size: 14px;
  margin-top: 5px;
}

.form-actions {
  display: flex;
  gap: 10px;

  button {
    padding: 10px 15px;
    border-radius: $border-radius;
    font-size: 14px;
    cursor: pointer;
    transition: background-color $transition-speed;

    &:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
  }

  .send-btn {
    background-color: $primary-color;
    color: white;
    border: none;
    flex: 1;

    &:hover:not(:disabled) {
      background-color: darken($primary-color, 10%);
    }
  }

  .clear-btn {
    background-color: $secondary-color;
    color: white;
    border: none;

    &:hover:not(:disabled) {
      background-color: darken($secondary-color, 10%);
    }
  }
}

// Responsive adjustments
@media (max-width: 640px) {
  .chat-container {
    border-radius: 0;
    box-shadow: none;
    margin: 0;
    height: 100vh;
    display: flex;
    flex-direction: column;
  }

  .message-container {
    flex: 1;
    overflow: hidden;
  }

  .messages {
    height: 100%;
  }

  .form-actions {
    button {
      padding: 12px 15px;
    }
  }
}
</style>
