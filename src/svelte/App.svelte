<!--
  ============================================================================
  SVELTE CHAT APPLICATION - MAIN COMPONENT
  ============================================================================

  This is the main chat component for the Svelte implementation.
  It demonstrates Svelte 5's new "runes" syntax which uses special
  $-prefixed functions for reactivity.

  KEY SVELTE 5 CONCEPTS USED HERE:
  ================================

  1. $props() - Receives props from parent/mount
     Instead of: export let propName;
     We now use: let { propName } = $props();

  2. $state() - Creates reactive state
     Instead of: let count = 0;
     We now use: let count = $state(0);

  3. $effect() - Runs side effects when dependencies change
     Instead of: $: { doSomething(); }
     We now use: $effect(() => { doSomething(); });

  4. $derived() - Computes values from other state
     Instead of: $: doubled = count * 2;
     We now use: let doubled = $derived(count * 2);

  COMPARISON WITH VUE.JS:
  =======================
  - Svelte's $state() is like Vue's ref()
  - Svelte's $effect() is like Vue's watch() or onMounted()
  - Svelte's $derived() is like Vue's computed()
  - Svelte uses {#if} and {#each} directives vs Vue's v-if and v-for

  ============================================================================
-->

<script>
  import { onMount, onDestroy } from 'svelte';

  // ============================================================================
  // PROPS - Data passed from main.js
  // ============================================================================

  /**
   * Receive configuration from the entry point.
   * In Svelte 5, we use $props() to get props passed during mount.
   *
   * The config object contains:
   * - chatRoutes: API endpoints for chat operations
   * - csrfTokenName: CSRF token name for form security
   * - username: Current logged-in user's name
   * - userId: Current user's ID
   * - wsToken: WebSocket authentication token
   */
  let { config } = $props();

  // ============================================================================
  // REACTIVE STATE - Using Svelte 5 runes
  // ============================================================================

  /**
   * $state() creates reactive state variables.
   * When these values change, Svelte automatically updates the DOM.
   *
   * This is similar to Vue's ref() or React's useState().
   */

  // Chat messages array - each message has: user, msg, timestamp
  let messages = $state([]);

  // Current message being typed by the user
  let message = $state('');

  // Error message to display (empty string = no error)
  let error = $state('');

  // Loading states for different operations
  let loading = $state(true);
  let sending = $state(false);
  let loadingMore = $state(false);

  // Pagination state
  let currentPage = $state(1);
  let hasMoreMessages = $state(false);

  // WebSocket connection state
  let webSocket = $state(null);
  let webSocketConnected = $state(false);
  let reconnectAttempts = $state(0);
  let reconnectInterval = $state(null);
  let lastMessageTime = $state(null);

  // ============================================================================
  // DERIVED STATE - Computed values
  // ============================================================================

  /**
   * $derived() creates values that automatically update when their
   * dependencies change. Similar to Vue's computed() or useMemo().
   */

  // Check if the send button should be disabled
  let canSend = $derived(!sending && message.trim().length > 0);

  // ============================================================================
  // LIFECYCLE - Component mount and cleanup
  // ============================================================================

  /**
   * onMount runs after the component is first rendered to the DOM.
   * This is similar to Vue's onMounted() lifecycle hook.
   *
   * We use this to establish the WebSocket connection when the
   * component loads.
   */
  onMount(() => {
    connectWebSocket();

    // Add cleanup listener for page unload
    window.addEventListener('beforeunload', cleanUp);
  });

  /**
   * onDestroy runs when the component is removed from the DOM.
   * This is similar to Vue's onUnmounted() or beforeUnmount().
   *
   * We clean up WebSocket connections and event listeners here
   * to prevent memory leaks.
   */
  onDestroy(() => {
    cleanUp();
  });

  // ============================================================================
  // WEBSOCKET FUNCTIONS
  // ============================================================================

  /**
   * Establish a WebSocket connection with token authentication.
   *
   * WEBSOCKET AUTHENTICATION FLOW:
   * ==============================
   * 1. User logs in via HTTP (Auth::processLogin)
   * 2. Server generates a token and stores it in session
   * 3. Token is passed to Svelte via window.WEBSOCKET_TOKEN
   * 4. We include the token in the WebSocket URL as a query parameter
   * 5. WebSocket server validates the token before accepting connection
   *
   * SECURITY NOTES:
   * - Always use WSS (WebSocket Secure) in production over HTTPS
   * - Tokens expire after 24 hours (configurable in WebSocketTokenHelper)
   * - Tokens are revoked when users log out
   */
  function connectWebSocket() {
    // Close existing connection if any
    if (webSocket) {
      webSocket.close();
    }

    // Build the WebSocket URL with authentication parameters
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const host = window.location.hostname;
    const port = 8080; // This should match your WebSocket server port

    // Include token and user_id as query parameters for authentication
    // The server will validate these before accepting the connection
    const wsUrl = `${protocol}//${host}:${port}?token=${encodeURIComponent(config.wsToken)}&user_id=${config.userId}`;

    console.log('Connecting to WebSocket with authentication...');
    webSocket = new WebSocket(wsUrl);

    // Connection opened successfully
    webSocket.addEventListener('open', (event) => {
      console.log('WebSocket connection established');
      webSocketConnected = true;
      reconnectAttempts = 0;

      if (reconnectInterval) {
        clearInterval(reconnectInterval);
        reconnectInterval = null;
      }

      // Load initial messages once connected
      loadMessages();
    });

    // Handle incoming messages from WebSocket server
    webSocket.addEventListener('message', (event) => {
      const data = JSON.parse(event.data);

      // Handle different message types (actions) from the server
      switch (data.action) {
        case 'messages':
          // Handle messages list response
          messages = data.data.messages || [];
          hasMoreMessages = data.data.pagination?.hasNext || false;
          loading = false;
          loadingMore = false;

          // Store timestamp of newest message for refresh comparison
          if (messages.length > 0 && messages[0].timestamp) {
            lastMessageTime = messages[0].timestamp;
          }
          break;

        case 'newMessage':
          // Handle new message broadcast from another user
          if (!lastMessageTime || data.data.timestamp > lastMessageTime) {
            // Add new message to the beginning of our list
            messages = [data.data, ...messages];
            lastMessageTime = data.data.timestamp;
          }
          break;

        case 'error':
          // Handle authentication or other errors from the server
          console.error('WebSocket server error:', data.data.message);

          if (data.data.code === 'AUTH_FAILED') {
            // Authentication failed - likely token expired or invalid
            error = 'Session expired. Please log in again.';
            console.log('Authentication failed, redirecting to login...');

            // Stop reconnection attempts since we need a new token
            if (reconnectInterval) {
              clearInterval(reconnectInterval);
              reconnectInterval = null;
            }
            reconnectAttempts = 5; // Prevent further reconnection attempts

            // Redirect to login after a short delay so user sees the message
            setTimeout(() => {
              window.location.href = '/auth/login';
            }, 2000);
          } else {
            error = data.data.message || 'An error occurred';
          }
          break;
      }
    });

    // Connection closed
    webSocket.addEventListener('close', (event) => {
      console.log('WebSocket connection closed');
      webSocketConnected = false;

      // Attempt to reconnect with exponential backoff
      if (!reconnectInterval) {
        reconnectInterval = setInterval(() => {
          if (reconnectAttempts < 5) {
            console.log(`Attempting to reconnect (${reconnectAttempts + 1}/5)...`);
            reconnectAttempts++;
            connectWebSocket();
          } else {
            clearInterval(reconnectInterval);
            reconnectInterval = null;
            console.error('Failed to reconnect after 5 attempts');
          }
        }, 5000);
      }
    });

    // Connection error
    webSocket.addEventListener('error', (event) => {
      console.error('WebSocket error:', event);
      webSocketConnected = false;
    });
  }

  /**
   * Clean up WebSocket connection and event listeners.
   * Called on component destroy and page unload.
   */
  function cleanUp() {
    if (webSocket) {
      webSocket.close();
    }

    if (reconnectInterval) {
      clearInterval(reconnectInterval);
    }

    window.removeEventListener('beforeunload', cleanUp);
  }

  // ============================================================================
  // MESSAGE LOADING FUNCTIONS
  // ============================================================================

  /**
   * Load messages - prefers WebSocket, falls back to HTTP.
   */
  function loadMessages() {
    if (!webSocketConnected) {
      console.log('WebSocket not connected, using HTTP fallback');
      loadMessagesHttp();
      return;
    }

    loading = true;

    // Request messages via WebSocket
    webSocket.send(JSON.stringify({
      action: 'getMessages',
      page: currentPage,
      perPage: 10
    }));
  }

  /**
   * HTTP fallback for loading messages.
   * Used when WebSocket is not available.
   */
  async function loadMessagesHttp() {
    try {
      const response = await fetch(`${config.chatRoutes.api}?page=${currentPage}&per_page=10`);
      const data = await response.json();

      messages = data.messages || [];
      hasMoreMessages = data.pagination?.hasNext || false;
      loading = false;

      // Store timestamp of newest message for refresh comparison
      if (messages.length > 0 && messages[0].timestamp) {
        lastMessageTime = messages[0].timestamp;
      }
    } catch (err) {
      console.error('Error loading messages:', err);
      loading = false;
    }
  }

  /**
   * Load more messages (pagination).
   */
  function loadMoreMessages() {
    if (loadingMore) return;

    loadingMore = true;
    currentPage++;

    if (!webSocketConnected) {
      console.log('WebSocket not connected, using HTTP fallback');
      loadMoreMessagesHttp();
      return;
    }

    // Request more messages via WebSocket
    webSocket.send(JSON.stringify({
      action: 'getMessages',
      page: currentPage,
      perPage: 10
    }));

    // Fallback timeout to reset loading state
    setTimeout(() => {
      if (loadingMore) {
        loadingMore = false;
      }
    }, 5000);
  }

  /**
   * HTTP fallback for loading more messages.
   */
  async function loadMoreMessagesHttp() {
    try {
      const response = await fetch(`${config.chatRoutes.api}?page=${currentPage}&per_page=10`);
      const data = await response.json();

      if (data.messages && data.messages.length > 0) {
        messages = [...messages, ...data.messages];
      }

      hasMoreMessages = data.pagination?.hasNext || false;
    } catch (err) {
      console.error('Error loading more messages:', err);
      currentPage--; // Revert page increment on failure
    } finally {
      loadingMore = false;
    }
  }

  // ============================================================================
  // MESSAGE SENDING FUNCTIONS
  // ============================================================================

  /**
   * Send a new chat message.
   * Validates input, then sends via WebSocket or HTTP.
   */
  function sendMessage() {
    // Clear previous errors
    error = '';

    // Validate message
    if (!message.trim()) {
      error = 'Message is required';
      return;
    }

    if (message.length > 500) {
      error = 'Message cannot exceed 500 characters';
      return;
    }

    sending = true;

    if (webSocketConnected) {
      // Send message via WebSocket
      webSocket.send(JSON.stringify({
        action: 'sendMessage',
        username: config.username,
        message: message
      }));

      // Clear message field
      message = '';
      sending = false;
    } else {
      // Fallback to HTTP if WebSocket is not connected
      sendMessageHttp();
    }
  }

  /**
   * HTTP fallback for sending messages.
   */
  async function sendMessageHttp() {
    try {
      const formData = new FormData();
      formData.append('message', message);
      formData.append('action', 'postmsg');

      // Get CSRF token from meta tag
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      if (csrfMeta) {
        formData.append(config.csrfTokenName, csrfMeta.getAttribute('content'));
      }

      const response = await fetch(config.chatRoutes.update, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const data = await response.json();

      if (data && data.success === false) {
        error = data.errors?.message || 'Failed to send message';
      } else {
        // Add message to the beginning of the list
        messages = [{
          user: config.username,
          msg: message,
          timestamp: Math.floor(Date.now() / 1000)
        }, ...messages];

        // Clear message field
        message = '';
      }
    } catch (err) {
      console.error('Error sending message:', err);
      error = 'Failed to send message. Please try again.';
    } finally {
      sending = false;
    }
  }

  /**
   * Handle Enter key press to send message.
   * Shift+Enter allows for new lines.
   *
   * @param {KeyboardEvent} event - The keyboard event
   */
  function handleKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      sendMessage();
    }
  }

  // ============================================================================
  // FORMATTING FUNCTIONS
  // ============================================================================

  /**
   * Format a Unix timestamp into a human-readable relative time.
   *
   * @param {number} timestamp - Unix timestamp in seconds
   * @returns {string} Human-readable time string
   */
  function formatTimestamp(timestamp) {
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
  }

  /**
   * Format message text with markdown-like syntax.
   * Supports: **bold**, *italic*, `code`, > quote, and URLs.
   *
   * @param {string} messageText - Raw message text
   * @returns {string} HTML-formatted message
   */
  function formatMessage(messageText) {
    if (!messageText) return '';

    // Escape HTML to prevent XSS attacks
    let formatted = escapeHtml(messageText);

    // Bold: **text**
    formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

    // Italic: *text*
    formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');

    // Code: `code`
    formatted = formatted.replace(/`(.*?)`/g, '<code>$1</code>');

    // Blockquote: > text (at start of line)
    formatted = formatted.replace(/^&gt; (.*)$/gm, '<blockquote>$1</blockquote>');

    // Convert URLs to clickable links
    formatted = formatted.replace(
      /(https?:\/\/[^\s]+)/g,
      '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
    );

    // Convert line breaks to <br>
    formatted = formatted.replace(/\n/g, '<br>');

    return formatted;
  }

  /**
   * Escape HTML special characters to prevent XSS.
   *
   * @param {string} text - Raw text to escape
   * @returns {string} Escaped text safe for HTML
   */
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Insert formatting markers around selected text in textarea.
   * Used by the formatting toolbar buttons.
   *
   * @param {string} prefix - Text to insert before selection
   * @param {string} suffix - Text to insert after selection
   */
  function insertFormatting(prefix, suffix) {
    const textarea = document.getElementById('message-input');
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = message.substring(start, end);

    // Insert the formatting
    const beforeText = message.substring(0, start);
    const afterText = message.substring(end);

    // Update the message
    message = beforeText + prefix + selectedText + suffix + afterText;

    // Focus and restore selection (after Svelte updates the DOM)
    // Using setTimeout to wait for DOM update
    setTimeout(() => {
      textarea.focus();
      textarea.setSelectionRange(
        start + prefix.length,
        end + prefix.length
      );
    }, 0);
  }
</script>

<!--
  ============================================================================
  TEMPLATE - The component's HTML structure
  ============================================================================

  Svelte templates use special directives:
  - {#if condition} ... {/if} - Conditional rendering
  - {#each array as item} ... {/each} - List rendering
  - {expression} - Output expressions
  - on:event={handler} - Event handlers
  - bind:value={variable} - Two-way binding

  Compare to Vue:
  - Svelte's {#if} is like Vue's v-if
  - Svelte's {#each} is like Vue's v-for
  - Svelte's on:click is like Vue's @click
  - Svelte's bind:value is like Vue's v-model
-->

<div id="wrapper" class="chat-container">
  <!-- Header with user info and logout -->
  <header class="chat-header">
    <div class="user-info">
      <span class="welcome-text">Welcome, <b>{config.username}</b>!</span>
      <a href="/auth/logout" class="logout-btn">
        <i class="icon-logout"></i> Logout
      </a>
    </div>
  </header>

  <!-- Message display area -->
  <div class="message-container">
    <!-- Loading state -->
    {#if loading}
      <div class="loading-indicator">
        <div class="spinner"></div>
        <span>Loading messages...</span>
      </div>
    {:else}
      <!-- Message list -->
      <div id="messagewindow" class="messages">
        {#each messages as msg, index (index)}
          <div class="message-item">
            <div class="message-header">
              <span class="username">{msg.user}</span>
              {#if msg.timestamp}
                <span class="timestamp">{formatTimestamp(msg.timestamp)}</span>
              {/if}
            </div>
            <!-- Using {@html} to render formatted message HTML -->
            <!-- This is safe because we escape user input in formatMessage() -->
            <div class="message-content">{@html formatMessage(msg.msg)}</div>
          </div>
        {/each}

        <!-- Empty state -->
        {#if messages.length === 0}
          <div class="no-messages">
            No messages yet. Be the first to send a message!
          </div>
        {/if}
      </div>
    {/if}
  </div>

  <!-- Load more button -->
  {#if !loading && hasMoreMessages}
    <div class="load-more-container">
      <button
        class="load-more-btn"
        onclick={loadMoreMessages}
        disabled={loadingMore}
      >
        {#if loadingMore}
          <span class="spinner-small"></span>
        {/if}
        <span>{loadingMore ? 'Loading...' : 'Load More Messages'}</span>
      </button>
    </div>
  {/if}

  <!-- Message input form -->
  <div class="message-form-container">
    <form onsubmit={(e) => { e.preventDefault(); sendMessage(); }} class="message-form">
      <!-- Message input -->
      {#if !sending}
        <div class="form-group">
          <label for="message-input">Message:</label>
          <textarea
            id="message-input"
            bind:value={message}
            class:error-field={error}
            placeholder="Type your message here..."
            rows="2"
            onkeydown={handleKeydown}
          ></textarea>

          <!-- Formatting toolbar -->
          <div class="formatting-help">
            <button type="button" onclick={() => insertFormatting('**', '**')} title="Bold">B</button>
            <button type="button" onclick={() => insertFormatting('*', '*')} title="Italic">I</button>
            <button type="button" onclick={() => insertFormatting('`', '`')} title="Code">Code</button>
            <button type="button" onclick={() => insertFormatting('\n> ', '')} title="Quote">Quote</button>
            <span class="format-info">Supports Markdown: **bold**, *italic*, `code`, > quote</span>
          </div>

          <!-- Error display -->
          {#if error}
            <div class="error">{error}</div>
          {/if}
        </div>
      {:else}
        <!-- Sending indicator -->
        <div class="loading-indicator-small">
          <div class="spinner"></div>
          <span>Sending message...</span>
        </div>
      {/if}

      <!-- Form action buttons -->
      <div class="form-actions">
        <button
          type="submit"
          class="send-btn"
          disabled={!canSend}
        >
          Send Message
        </button>
        <button
          type="button"
          class="clear-btn"
          onclick={() => message = ''}
          disabled={sending || !message.trim()}
        >
          Clear
        </button>
      </div>
    </form>
  </div>
</div>

<!--
  ============================================================================
  STYLES - Component-scoped CSS
  ============================================================================

  In Svelte, styles are scoped to the component by default.
  This means these styles only apply to elements in THIS component,
  preventing CSS conflicts with other parts of the application.

  The styles here are similar to the Vue version for consistency.
  We use SCSS for variables and nesting.
-->

<style lang="scss">
  /* Variables for consistent theming */
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

  /* Global box-sizing for this component */
  :global(*) {
    box-sizing: border-box;
  }

  /* Chat container - the main wrapper */
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

  /* Chat header - contains user info and logout */
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

  /* Message container */
  .message-container {
    position: relative;
    min-height: 300px;
  }

  /* Messages list */
  .messages {
    height: 350px;
    overflow-y: auto;
    padding: 15px;
    background-color: $background-color;

    @media (max-width: 640px) {
      height: 300px;
    }
  }

  /* Individual message item */
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

    :global(a) {
      color: $primary-color;
      text-decoration: none;

      &:hover {
        text-decoration: underline;
      }
    }

    :global(code) {
      background-color: $background-color;
      padding: 2px 4px;
      border-radius: 3px;
      font-family: monospace;
      font-size: 0.9em;
    }

    :global(blockquote) {
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

  /* Loading indicators */
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

  /* Spinner animation */
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

  /* Load more button */
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

  /* Message form container */
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

  /* Formatting toolbar */
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

  /* Error message */
  .error {
    color: $error-color;
    font-size: 14px;
    margin-top: 5px;
  }

  /* Form action buttons */
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

  /* Responsive adjustments */
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
