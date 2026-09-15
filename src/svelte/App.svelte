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
  import { onDestroy, onMount } from 'svelte';
  import {
    dismissFailedMessages,
    enablePushNotifications,
    promptInstall,
    subscribePwa,
    userScopedMessagesUrl,
  } from '../js/pwa.js';

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
  let pendingSearchAppend = $state(false);
  let pwa = $state({
    online: navigator.onLine,
    installAvailable: false,
    serviceWorkerReady: false,
    queuedMessages: 0,
    failedMessages: 0,
  });
  let unsubscribePwa = null;
  let notificationsEnabled = $state(false);
  let search = $state({
    text: '',
    user: '',
    from: '',
    to: '',
  });
  let searchActive = $state(false);
  let searchLoading = $state(false);
  let searchDebounce = null;
  let searchHasLiveUpdates = $state(false);
  let searchError = $state('');
  let activeSearchKey = $state('');
  let searchRequestId = $state(0);
  let searchAbortController = null;
  let typingUsers = $state([]);
  let lastTypingSentAt = $state(0);
  let typingStopTimeout = null;

  // ============================================================================
  // DERIVED STATE - Computed values
  // ============================================================================

  /**
   * $derived() creates values that automatically update when their
   * dependencies change. Similar to Vue's computed() or useMemo().
   */

  // Check if the send button should be disabled
  let canSend = $derived(!sending && message.trim().length > 0);
  let canEnableNotifications = $derived(
    Boolean(config.pushPublicKey && !notificationsEnabled && 'PushManager' in window),
  );
  let hasSearchFilters = $derived(Object.values(normalizedSearch()).some((value) => value !== ''));
  let visibleTypingUsers = $derived(typingUsers.filter((user) => Number(user.user_id) !== Number(config.userId)));
  let typingIndicatorText = $derived.by(() => {
    const names = visibleTypingUsers.map((user) => user.username);
    if (names.length === 0) return '';
    if (names.length === 1) return `${names[0]} is typing`;
    if (names.length === 2) return `${names[0]} and ${names[1]} are typing`;
    if (names.length === 3) return `${names[0]}, ${names[1]}, and ${names[2]} are typing`;
    return `${names.slice(0, 3).join(', ')}, and ${names.length - 3} others are typing`;
  });

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
    unsubscribePwa = subscribePwa((state) => {
      pwa = state;
    });
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
    unsubscribePwa?.();
  });

  async function installApp() {
    await promptInstall();
  }

  function dismissDeliveryFailures() {
    void dismissFailedMessages();
  }

  async function enableNotifications() {
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      notificationsEnabled = await enablePushNotifications({
        endpoint: config.chatRoutes.pushSubscriptions,
        csrfTokenName: config.csrfTokenName,
        csrfToken,
        publicKey: config.pushPublicKey,
      });
    } catch (err) {
      console.error('Could not enable notifications:', err);
      error = 'Notifications could not be enabled.';
    }
  }

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
      warmMessagesCache();
    });

    // Handle incoming messages from WebSocket server
    webSocket.addEventListener('message', (event) => {
      const data = JSON.parse(event.data);

      if (data.type === 'typing_state') {
        typingUsers = Array.isArray(data.users) ? data.users : [];
        return;
      }

      // Handle different message types (actions) from the server
      switch (data.action) {
        case 'messages':
          if (searchActive) {
            break;
          }

          // Handle messages list response
          messages = data.data.messages || [];
          hasMoreMessages = data.data.pagination?.hasNext || false;
          loading = false;
          loadingMore = false;
          searchLoading = false;

          // Store timestamp of newest message for refresh comparison
          if (messages.length > 0 && messages[0].timestamp) {
            lastMessageTime = messages[0].timestamp;
          }
          break;

        case 'newMessage':
          // Handle new message broadcast from another user
          if (searchActive) {
            searchHasLiveUpdates = true;
            break;
          }

          if (!lastMessageTime || data.data.timestamp > lastMessageTime) {
            // Add new message to the beginning of our list
            messages = [data.data, ...messages];
            lastMessageTime = data.data.timestamp;
          }
          break;

        case 'searchResults': {
          if (data.data.requestId !== searchRequestId || !isCurrentSearchResponse(data.data.filters)) {
            break;
          }

          const searchMessages = data.data.messages || [];
          messages = pendingSearchAppend ? [...messages, ...searchMessages] : searchMessages;
          hasMoreMessages = data.data.pagination?.hasNext || false;
          loading = false;
          loadingMore = false;
          searchLoading = false;
          searchActive = true;
          pendingSearchAppend = false;
          searchHasLiveUpdates = false;
          searchError = '';
          break;
        }

        case 'error':
          // Handle authentication or other errors from the server
          if (data.data.code === 'INVALID_SEARCH' && searchActive && data.data.requestId === searchRequestId) {
            searchError = data.data.message || 'Search failed.';
            loading = false;
            loadingMore = false;
            searchLoading = false;
            pendingSearchAppend = false;
          } else if (data.data.code === 'AUTH_FAILED') {
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
      typingUsers = [];
      lastTypingSentAt = 0;

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
      if (searchActive && activeSearchKey) {
        void searchMessagesHttp(currentPage, pendingSearchAppend, searchRequestId, normalizedSearch(), activeSearchKey);
        return;
      }
      if (loading) loadMessagesHttp();
    });
  }

  /**
   * Clean up WebSocket connection and event listeners.
   * Called on component destroy and page unload.
   */
  function cleanUp() {
    if (searchDebounce) {
      clearTimeout(searchDebounce);
    }
    cancelSearchRequest();
    stopTyping();

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
    searchActive = false;
    searchHasLiveUpdates = false;
    searchLoading = false;
    pendingSearchAppend = false;
    searchError = '';
    activeSearchKey = '';

    if (!webSocketConnected) {
      console.log('WebSocket not connected, using HTTP fallback');
      loadMessagesHttp();
      return;
    }

    loading = true;

    // Request messages via WebSocket
    webSocket.send(
      JSON.stringify({
        action: 'getMessages',
        page: currentPage,
        perPage: 10,
      }),
    );
  }

  /**
   * HTTP fallback for loading messages.
   * Used when WebSocket is not available.
   */
  async function loadMessagesHttp() {
    try {
      const response = await fetch(
        userScopedMessagesUrl(`${config.chatRoutes.api}?page=${currentPage}&per_page=10`, config.userId),
      );
      const data = await response.json();
      if (!response.ok) throw new Error('Message request failed');

      messages = data.messages || [];
      hasMoreMessages = data.pagination?.hasNext || false;
      loading = false;

      // Store timestamp of newest message for refresh comparison
      if (messages.length > 0 && messages[0].timestamp) {
        lastMessageTime = messages[0].timestamp;
      }
      return true;
    } catch (err) {
      console.error('Error loading messages:', err);
      loading = false;
      return false;
    }
  }

  async function warmMessagesCache() {
    try {
      await fetch(userScopedMessagesUrl(`${config.chatRoutes.api}?page=1&per_page=10`, config.userId));
    } catch {
      // The live WebSocket remains authoritative; cache warming is best effort.
    }
  }

  /**
   * Load more messages (pagination).
   */
  function loadMoreMessages() {
    if (loadingMore) return;

    loadingMore = true;
    currentPage++;

    if (searchActive) {
      executeSearch(currentPage, true);
      return;
    }

    if (!webSocketConnected) {
      console.log('WebSocket not connected, using HTTP fallback');
      loadMoreMessagesHttp();
      return;
    }

    // Request more messages via WebSocket
    webSocket.send(
      JSON.stringify({
        action: 'getMessages',
        page: currentPage,
        perPage: 10,
      }),
    );

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
      const response = await fetch(
        userScopedMessagesUrl(`${config.chatRoutes.api}?page=${currentPage}&per_page=10`, config.userId),
      );
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

  function scheduleSearch() {
    if (searchDebounce) {
      clearTimeout(searchDebounce);
    }

    if (!hasSearchFilters) {
      if (searchActive) {
        clearSearch();
      }
      return;
    }

    searchDebounce = setTimeout(() => applySearch(), 400);
  }

  function applySearch() {
    if (searchDebounce) {
      clearTimeout(searchDebounce);
      searchDebounce = null;
    }

    if (!hasSearchFilters) {
      clearSearch();
      return;
    }

    currentPage = 1;
    executeSearch(1, false);
  }

  function clearSearch() {
    if (searchDebounce) {
      clearTimeout(searchDebounce);
      searchDebounce = null;
    }
    cancelSearchRequest();

    search = {
      text: '',
      user: '',
      from: '',
      to: '',
    };
    searchActive = false;
    searchLoading = false;
    searchHasLiveUpdates = false;
    searchError = '';
    activeSearchKey = '';
    currentPage = 1;
    loadMessages();
  }

  function executeSearch(page = 1, append = false) {
    const filters = normalizedSearch();
    if (!Object.values(filters).some((value) => value !== '')) {
      clearSearch();
      return;
    }

    const validationError = searchValidationError(filters);
    if (validationError) {
      searchError = validationError;
      loading = false;
      loadingMore = false;
      searchLoading = false;
      return;
    }

    searchActive = true;
    searchLoading = !append;
    pendingSearchAppend = append;
    searchHasLiveUpdates = false;
    searchError = '';
    activeSearchKey = searchKey(filters);
    searchRequestId++;
    const requestId = searchRequestId;

    if (append) {
      loadingMore = true;
    } else {
      loading = true;
    }

    if (!webSocketConnected) {
      void searchMessagesHttp(page, append, requestId, filters, activeSearchKey);
      return;
    }

    webSocket.send(
      JSON.stringify({
        action: 'getMessages',
        requestId,
        page,
        perPage: 10,
        search: compactSearch(filters),
      }),
    );

    setTimeout(() => {
      if (requestId === searchRequestId && (searchLoading || loadingMore || loading)) {
        loading = false;
        searchLoading = false;
        loadingMore = false;
        pendingSearchAppend = false;
        searchError = 'Search timed out. Please try again.';
      }
    }, 5000);
  }

  function cancelSearchRequest() {
    searchRequestId++;
    searchAbortController?.abort();
    searchAbortController = null;
  }

  async function searchMessagesHttp(page = 1, append = false, requestId, filters, currentSearchKey) {
    searchAbortController?.abort();
    searchAbortController = new AbortController();

    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: '10',
      });

      for (const [key, value] of Object.entries(filters)) {
        if (value !== '') params.set(key, String(value));
      }

      const response = await fetch(userScopedMessagesUrl(`${searchEndpoint()}?${params.toString()}`, config.userId), {
        signal: searchAbortController.signal,
      });
      const data = await response.json();
      if (!response.ok) throw new Error('Message search failed');
      if (requestId !== searchRequestId || currentSearchKey !== activeSearchKey) return false;
      if (!isCurrentSearchResponse(data.filters)) return false;

      const searchMessages = data.messages || [];
      messages = append ? [...messages, ...searchMessages] : searchMessages;
      hasMoreMessages = data.pagination?.hasNext || false;
      searchActive = true;
      searchError = '';
      return true;
    } catch (err) {
      if (err.name === 'AbortError') return false;
      console.error('Error searching messages:', err);
      searchError = 'Search failed. Please adjust your filters and try again.';
      if (append) currentPage--;
      return false;
    } finally {
      if (requestId === searchRequestId) {
        loading = false;
        loadingMore = false;
        searchLoading = false;
        pendingSearchAppend = false;
      }
    }
  }

  function normalizedSearch() {
    return {
      text: search.text.trim(),
      user: search.user.trim(),
      from: dateBoundarySeconds(search.from, false),
      to: dateBoundarySeconds(search.to, true),
    };
  }

  function compactSearch(filters) {
    return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
  }

  function dateBoundarySeconds(dateValue, endOfDay) {
    if (!dateValue) return '';

    const [year, month, day] = dateValue.split('-').map((part) => Number.parseInt(part, 10));
    const date = endOfDay
      ? new Date(year, month - 1, day, 23, 59, 59, 999)
      : new Date(year, month - 1, day, 0, 0, 0, 0);

    return Number.isNaN(date.getTime()) ? Number.NaN : Math.floor(date.getTime() / 1000);
  }

  function searchValidationError(filters) {
    if (filters.text.length > 500) return 'Search text must not exceed 500 characters.';
    if (filters.user.length > 255) return 'Username filter must not exceed 255 characters.';
    const dateValues = [filters.from, filters.to].filter((value) => value !== '');
    if (dateValues.some((value) => Number.isNaN(value))) return 'Search dates are invalid.';
    if (dateValues.some((value) => value < 0)) return 'Search dates must be on or after 1970-01-01.';
    if (filters.from !== '' && filters.to !== '' && filters.from > filters.to) {
      return 'From date must be on or before To date.';
    }
    return '';
  }

  function searchKey(filters) {
    return JSON.stringify(compactSearch(filters));
  }

  function isCurrentSearchResponse(filters) {
    return searchKey(normalizeResponseFilters(filters || {})) === activeSearchKey;
  }

  function normalizeResponseFilters(filters) {
    return {
      text: String(filters.text || '').trim(),
      user: String(filters.user || '').trim(),
      from: numericResponseFilter(filters.from),
      to: numericResponseFilter(filters.to),
    };
  }

  function numericResponseFilter(value) {
    if (value === undefined || value === null || value === '') return '';
    const numberValue = Number(value);
    return Number.isFinite(numberValue) ? numberValue : '';
  }

  function searchEndpoint() {
    return `${config.chatRoutes.api.replace(/\/$/, '')}/search`;
  }

  function handleTypingInput(event) {
    if (!event.target.value.trim()) {
      stopTyping();
      return;
    }

    startTyping();
  }

  function startTyping() {
    if (!webSocketConnected) return;

    const now = Date.now();
    if (now - lastTypingSentAt >= 3000) {
      webSocket.send(
        JSON.stringify({
          type: 'typing_start',
          user_id: Number(config.userId),
          username: config.username,
        }),
      );
      lastTypingSentAt = now;
    }

    if (typingStopTimeout) clearTimeout(typingStopTimeout);
    typingStopTimeout = setTimeout(() => stopTyping(), 5000);
  }

  function stopTyping() {
    if (typingStopTimeout) {
      clearTimeout(typingStopTimeout);
      typingStopTimeout = null;
    }

    if (webSocketConnected && lastTypingSentAt > 0) {
      webSocket.send(
        JSON.stringify({
          type: 'typing_stop',
          user_id: Number(config.userId),
          username: config.username,
        }),
      );
    }

    lastTypingSentAt = 0;
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

    stopTyping();

    sending = true;

    if (webSocketConnected) {
      // Send message via WebSocket
      webSocket.send(
        JSON.stringify({
          action: 'sendMessage',
          username: config.username,
          message: message,
        }),
      );

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
    let fetchCompleted = false;
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
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      fetchCompleted = true;

      const data = await response.json();

      if (data?.error) {
        error = data.error.details?.message || data.error.message || 'Failed to send message';
      } else {
        if (searchActive) {
          searchHasLiveUpdates = true;
        } else {
          // Add message to the beginning of the list
          messages = [
            {
              user: config.username,
              msg: message,
              timestamp: Math.floor(Date.now() / 1000),
            },
            ...messages,
          ];
        }

        // Clear message field
        message = '';
      }
    } catch (err) {
      console.error('Error sending message:', err);
      if (!fetchCompleted && navigator.serviceWorker?.controller) {
        message = '';
        error = 'Message queued. It will be retried when delivery is available.';
      } else {
        error = 'Failed to send message. Please try again.';
      }
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
    if (event.key === 'Escape') {
      event.preventDefault();
      clearMessage();
      return;
    }

    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      sendMessage();
    }
  }

  function clearMessage() {
    stopTyping();
    message = '';
    error = '';
    setTimeout(() => document.getElementById('message-input')?.focus(), 0);
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

  function formatMachineTimestamp(timestamp) {
    return new Date(timestamp * 1000).toISOString();
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

    const urls = [];
    const messageWithUrlTokens = String(messageText).replace(/https?:\/\/[^\s<>"']+/g, (url) => {
      const token = `\u0000CHAT_URL_${urls.length}\u0000`;
      urls.push(url);
      return token;
    });

    // Escape HTML to prevent XSS attacks. URL tokens keep highlighting and
    // markdown formatting out of generated anchor attributes.
    let formatted = escapeHtml(messageWithUrlTokens);
    formatted = highlightSearchTerms(formatted);

    // Bold: **text**
    formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

    // Italic: *text*
    formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');

    // Code: `code`
    formatted = formatted.replace(/`(.*?)`/g, '<code>$1</code>');

    // Blockquote: > text (at start of line)
    formatted = formatted.replace(/^&gt; (.*)$/gm, '<blockquote>$1</blockquote>');

    // Restore sanitized URLs as links.
    urls.forEach((url, index) => {
      const anchor = `<a href="${escapeAttribute(url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(url)}</a>`;
      formatted = formatted.replaceAll(`\u0000CHAT_URL_${index}\u0000`, anchor);
    });

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

  function escapeAttribute(text) {
    return String(text).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function highlightSearchTerms(escapedText) {
    const term = normalizedSearch().text;
    if (!searchActive || !term) return escapedText;

    const escapedTerm = escapeHtml(term);
    const pattern = new RegExp(`(${escapeRegExp(escapedTerm)})`, 'gi');
    return escapedText.replace(pattern, '<mark>$1</mark>');
  }

  function escapeRegExp(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
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
      textarea.setSelectionRange(start + prefix.length, end + prefix.length);
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

<a class="skip-link" href="#messagewindow">Skip to messages</a>
<main id="wrapper" class="chat-container" aria-labelledby="chat-title">
  <!-- Header with user info and logout -->
  <header class="chat-header">
    <h1 id="chat-title" class="sr-only">CodeIgniter Chat</h1>
    <div class="user-info">
      <span class="welcome-text">Welcome, <b>{config.username}</b>!</span>
      {#if pwa.installAvailable}
        <button type="button" class="pwa-action" onclick={installApp}>Install app</button>
      {/if}
      {#if canEnableNotifications}
        <button type="button" class="pwa-action" onclick={enableNotifications}>Enable notifications</button>
      {/if}
      <a href="/auth/logout" class="logout-btn"> <i class="icon-logout" aria-hidden="true"></i> Logout </a>
    </div>
  </header>

  {#if !pwa.online}
    <div class="connection-status" role="status" aria-live="polite" aria-atomic="true">
      You’re offline. New messages will be queued and retried when the connection returns.
    </div>
  {:else if pwa.queuedMessages > 0}
    <div class="connection-status queued" role="status" aria-live="polite" aria-atomic="true">
      {pwa.queuedMessages} {pwa.queuedMessages === 1 ? 'message' : 'messages'} queued for delivery.
    </div>
  {/if}
  {#if pwa.failedMessages > 0}
    <div class="connection-status failed" role="alert">
      {pwa.failedMessages}
      queued {pwa.failedMessages === 1 ? 'message' : 'messages'} could not be delivered. Please send again.
      <button type="button" class="status-dismiss" onclick={dismissDeliveryFailures}>Dismiss</button>
    </div>
  {/if}

  <section class="search-panel" aria-labelledby="message-search-title">
    <div class="search-panel-header">
      <h2 id="message-search-title">Search messages</h2>
      <button type="button" class="clear-btn" onclick={clearSearch} disabled={!hasSearchFilters && !searchActive}>
        Clear search
      </button>
    </div>
    <form
      class="search-form"
      onsubmit={(e) => {
        e.preventDefault();
        applySearch();
      }}
    >
      <div class="search-field search-field-wide">
        <label for="message-search-text">Text</label>
        <input
          id="message-search-text"
          bind:value={search.text}
          type="search"
          maxlength="500"
          placeholder="Search message text"
          oninput={scheduleSearch}
        >
      </div>
      <div class="search-field">
        <label for="message-search-user">User</label>
        <input
          id="message-search-user"
          bind:value={search.user}
          type="search"
          maxlength="255"
          autocomplete="off"
          placeholder="Username"
          oninput={scheduleSearch}
        >
      </div>
      <div class="search-field">
        <label for="message-search-from">From</label>
        <input id="message-search-from" bind:value={search.from} type="date" oninput={scheduleSearch}>
      </div>
      <div class="search-field">
        <label for="message-search-to">To</label>
        <input id="message-search-to" bind:value={search.to} type="date" oninput={scheduleSearch}>
      </div>
      <button type="submit" class="search-btn" disabled={searchLoading || !hasSearchFilters}>
        {searchLoading ? 'Searching...' : 'Search'}
      </button>
    </form>
    <p id="message-search-help" class="search-help">
      Search waits briefly while you type. New live messages stay out of filtered results until you refresh or clear.
    </p>
    {#if searchError}
      <div id="message-search-error" class="error" role="alert">{searchError}</div>
    {/if}
    {#if searchActive}
      <div class="search-status" role="status" aria-live="polite">
        Showing filtered results.
        {#if searchHasLiveUpdates}
          <button type="button" class="status-dismiss" onclick={applySearch}>Refresh search</button>
          <span>New messages arrived outside this filtered view.</span>
        {/if}
      </div>
    {/if}
  </section>

  <!-- Message display area -->
  <div class="message-container">
    <!-- Loading state -->
    {#if loading}
      <div class="loading-indicator" role="status" aria-live="polite">
        <div class="spinner" aria-hidden="true"></div>
        <span>Loading messages...</span>
      </div>
    {:else}
      <!-- Message list -->
      <div
        id="messagewindow"
        class="messages"
        role="log"
        aria-label="Chat messages"
        aria-live="polite"
        aria-relevant="additions text"
        tabindex="-1"
      >
        {#each messages as msg, index (index)}
          <article class="message-item" aria-label={`Message from ${msg.user}`}>
            <div class="message-header">
              <span class="username">{msg.user}</span>
              {#if msg.timestamp}
                <time class="timestamp" datetime={formatMachineTimestamp(msg.timestamp)}>
                  {formatTimestamp(msg.timestamp)}
                </time>
              {/if}
            </div>
            <!-- Using {@html} to render formatted message HTML -->
            <!-- This is safe because we escape user input in formatMessage() -->
            <div class="message-content">{@html formatMessage(msg.msg)}</div>
          </article>
        {/each}

        <!-- Empty state -->
        {#if messages.length === 0}
          <div class="no-messages">
            {searchActive ? 'No messages match your search.' : 'No messages yet. Be the first to send a message!'}
          </div>
        {/if}
      </div>
    {/if}
  </div>

  <!-- Load more button -->
  {#if !loading && hasMoreMessages}
    <div class="load-more-container">
      <button type="button" class="load-more-btn" onclick={loadMoreMessages} disabled={loadingMore}>
        {#if loadingMore}
          <span class="spinner-small" aria-hidden="true"></span>
        {/if}
        <span>{loadingMore ? 'Loading...' : 'Load More Messages'}</span>
      </button>
    </div>
  {/if}

  {#if typingIndicatorText}
    <div class="typing-indicator" role="status" aria-live="polite">
      <span>{typingIndicatorText}</span>
      <span class="typing-dots" aria-hidden="true"><span>.</span><span>.</span><span>.</span></span>
    </div>
  {/if}

  <!-- Message input form -->
  <div class="message-form-container">
    <form onsubmit={(e) => { e.preventDefault(); sendMessage(); }} class="message-form">
      <h2 id="compose-title" class="sr-only">Compose a message</h2>
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
            maxlength="500"
            aria-invalid={Boolean(error)}
            aria-describedby={error ? 'formatting-help message-error' : 'formatting-help'}
            oninput={handleTypingInput}
            onblur={stopTyping}
            onkeydown={handleKeydown}
          ></textarea>

          <!-- Formatting toolbar -->
          <fieldset class="formatting-help">
            <legend class="sr-only">Message formatting</legend>
            <button type="button" onclick={() => insertFormatting('**', '**')} aria-label="Format as bold">B</button>
            <button type="button" onclick={() => insertFormatting('*', '*')} aria-label="Format as italic">I</button>
            <button type="button" onclick={() => insertFormatting('`', '`')} aria-label="Format as code">Code</button>
            <button type="button" onclick={() => insertFormatting('\n> ', '')} aria-label="Format as quote">
              Quote
            </button>
            <span id="formatting-help" class="format-info">
              Supports Markdown: **bold**, *italic*, `code`, &gt; quote. Enter sends; Shift+Enter adds a line; Escape
              clears.
            </span>
          </fieldset>

          <!-- Error display -->
          {#if error}
            <div id="message-error" class="error" role="alert">{error}</div>
          {/if}
        </div>
      {:else}
        <!-- Sending indicator -->
        <div class="loading-indicator-small" role="status" aria-live="polite">
          <div class="spinner" aria-hidden="true"></div>
          <span>Sending message...</span>
        </div>
      {/if}

      <!-- Form action buttons -->
      <div class="form-actions">
        <button type="submit" class="send-btn" disabled={!canSend}>Send Message</button>
        <button type="button" class="clear-btn" onclick={clearMessage} disabled={sending || !message.trim()}>
          Clear
        </button>
      </div>
    </form>
  </div>
</main>

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
  @use "sass:color";

  /* Variables for consistent theming */
  $primary-color: #3f6398;
  $secondary-color: #6c757d;
  $background-color: #f8f9fa;
  $border-color: #dee2e6;
  $text-color: #343a40;
  $light-text-color: #5c6268;
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
    border-bottom: 1px solid color.adjust($primary-color, $lightness: -10%);
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

  .search-panel {
    padding: 15px;
    border-bottom: 1px solid $border-color;
    background-color: white;
  }

  .search-panel-header {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
    margin-bottom: 10px;

    h2 {
      margin: 0;
      font-size: 16px;
    }
  }

  .search-form {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    align-items: end;

    @media (max-width: 640px) {
      grid-template-columns: 1fr;
    }
  }

  .search-field {
    display: flex;
    flex-direction: column;
    gap: 4px;

    label {
      font-weight: 700;
      font-size: 13px;
    }

    input {
      min-width: 0;
      padding: 8px;
      border: 1px solid $border-color;
      border-radius: $border-radius;
      font: inherit;
    }
  }

  .search-field-wide {
    grid-column: 1 / -1;
  }

  .search-btn {
    padding: 9px 14px;
    border: 1px solid color.adjust($primary-color, $lightness: -8%);
    border-radius: $border-radius;
    background-color: $primary-color;
    color: white;
    font-size: 14px;
    cursor: pointer;
    transition: background-color $transition-speed;

    &:hover:not(:disabled) {
      background-color: color.adjust($primary-color, $lightness: -8%);
    }

    &:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
  }

  .search-help,
  .search-status {
    margin: 10px 0 0;
    color: $light-text-color;
    font-size: 13px;
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

    :global(mark) {
      padding: 0 2px;
      border-radius: 2px;
      background-color: #fff0a6;
      color: inherit;
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

  .typing-indicator {
    min-height: 24px;
    padding: 6px 15px;
    color: $light-text-color;
    font-size: 14px;
  }

  .typing-dots span {
    display: inline-block;
    animation: typing-dot 1.2s ease-in-out infinite;

    &:nth-child(2) {
      animation-delay: 0.15s;
    }

    &:nth-child(3) {
      animation-delay: 0.3s;
    }
  }

  @keyframes typing-dot {
    0%, 60%, 100% { opacity: 0.35; }
    30% { opacity: 1; }
  }

  @media (prefers-reduced-motion: reduce) {
    .typing-dots span {
      animation: none;
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
    padding: 0;
    border: 0;
    min-inline-size: 0;
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
        background-color: color.adjust($primary-color, $lightness: -10%);
      }
    }

    .clear-btn {
      background-color: $secondary-color;
      color: white;
      border: none;

      &:hover:not(:disabled) {
        background-color: color.adjust($secondary-color, $lightness: -10%);
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
