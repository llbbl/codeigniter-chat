<template>
  <a class="skip-link" href="#messagewindow">Skip to messages</a>
  <main id="wrapper" class="chat-container" aria-labelledby="chat-title">
    <header class="chat-header">
      <h1 id="chat-title" class="sr-only">CodeIgniter Chat</h1>
      <div class="user-info">
        <span class="welcome-text">Welcome, <b>{{ displayName }}</b>!</span>
        <button v-if="pwa.installAvailable" type="button" class="pwa-action" @click="installApp">Install app</button>
        <button v-if="canEnableNotifications" type="button" class="pwa-action" @click="enableNotifications">
          Enable notifications
        </button>
        <ProfilePanel @updated="handleOwnProfile" />
        <WebhookPanel />
        <a href="/auth/logout" class="logout-btn"> <i class="icon-logout" aria-hidden="true"></i> Logout </a>
      </div>
    </header>

    <div v-if="!pwa.online" class="connection-status" role="status" aria-live="polite" aria-atomic="true">
      You’re offline. New messages will be queued and retried when the connection returns.
    </div>
    <div
      v-else-if="pwa.queuedMessages > 0"
      class="connection-status queued"
      role="status"
      aria-live="polite"
      aria-atomic="true"
    >
      {{ pwa.queuedMessages }} message{{ pwa.queuedMessages === 1 ? '' : 's' }} queued for delivery.
    </div>
    <div v-if="pwa.failedMessages > 0" class="connection-status failed" role="alert">
      {{ pwa.failedMessages }} queued message{{ pwa.failedMessages === 1 ? '' : 's' }} could not be delivered. Please
      send again.
      <button type="button" class="status-dismiss" @click="dismissDeliveryFailures">Dismiss</button>
    </div>

    <nav class="channel-sidebar" aria-label="Channels and direct messages">
      <h2>Conversations</h2>
      <button
        v-for="channel in channels"
        :key="channel.id"
        type="button"
        class="channel-link"
        :class="{ active: Number(channel.id) === Number(activeChannelId) }"
        :aria-current="Number(channel.id) === Number(activeChannelId) ? 'page' : undefined"
        @click="selectChannel(channel)"
      >
        <span>{{ channel.channel_type === 'dm' ? `@ ${dmLabel(channel)}` : `# ${channel.name}` }}</span>
        <span v-if="channel.unread_count" class="channel-unread">
          {{ channel.unread_count }}<span class="sr-only"> unread</span>
        </span>
      </button>
      <form class="dm-form" @submit.prevent="createDm">
        <label for="vue-dm-username">Start a direct message</label>
        <div>
          <input
            id="vue-dm-username"
            v-model="dmUsername"
            type="text"
            maxlength="50"
            autocomplete="off"
            placeholder="Username"
          >
          <button type="submit" :disabled="!dmUsername.trim()">Open</button>
        </div>
      </form>
    </nav>

    <section class="search-panel" aria-labelledby="message-search-title">
      <div class="search-panel-header">
        <h2 id="message-search-title">Search messages</h2>
        <button type="button" class="clear-btn" @click="clearSearch" :disabled="!hasSearchFilters && !searchActive">
          Clear search
        </button>
      </div>
      <form class="search-form" @submit.prevent="applySearch">
        <div class="search-field search-field-wide">
          <label for="message-search-text">Text</label>
          <input
            id="message-search-text"
            v-model="search.text"
            type="search"
            maxlength="500"
            placeholder="Search message text"
            @input="scheduleSearch"
          >
        </div>
        <div class="search-field">
          <label for="message-search-user">User</label>
          <input
            id="message-search-user"
            v-model="search.user"
            type="search"
            maxlength="255"
            autocomplete="off"
            placeholder="Username"
            @input="scheduleSearch"
          >
        </div>
        <div class="search-field">
          <label for="message-search-from">From</label>
          <input id="message-search-from" v-model="search.from" type="date" @input="scheduleSearch">
        </div>
        <div class="search-field">
          <label for="message-search-to">To</label>
          <input id="message-search-to" v-model="search.to" type="date" @input="scheduleSearch">
        </div>
        <button type="submit" class="search-btn" :disabled="searchLoading || !hasSearchFilters">
          {{ searchLoading ? 'Searching...' : 'Search' }}
        </button>
      </form>
      <p id="message-search-help" class="search-help">
        Search waits briefly while you type. New live messages stay out of filtered results until you refresh or clear.
      </p>
      <div v-if="searchError" id="message-search-error" class="error" role="alert">{{ searchError }}</div>
      <div v-if="searchActive" class="search-status" role="status" aria-live="polite">
        Showing filtered results.
        <button v-if="searchHasLiveUpdates" type="button" class="status-dismiss" @click="applySearch">
          Refresh search
        </button>
        <span v-if="searchHasLiveUpdates">New messages arrived outside this filtered view.</span>
      </div>
    </section>

    <div class="message-container">
      <div v-if="loading" class="loading-indicator" role="status" aria-live="polite">
        <div class="spinner" aria-hidden="true"></div>
        <span>Loading messages...</span>
      </div>
      <div
        v-else
        id="messagewindow"
        ref="messageWindow"
        class="messages"
        role="log"
        aria-label="Chat messages"
        aria-live="polite"
        aria-relevant="additions text"
        tabindex="-1"
      >
        <a class="skip-link" href="#message-input">Skip to message composer</a>
        <div v-if="!searchActive && !hasMoreMessages && messages.length" class="history-boundary" role="status">
          Beginning of channel
        </div>
        <div
          v-if="!searchActive && hasMoreMessages"
          ref="historySentinel"
          class="history-sentinel"
          aria-hidden="true"
        ></div>
        <div v-if="!searchActive && loadingMore" class="history-loading" role="status" aria-live="polite">
          <span class="spinner-small" aria-hidden="true"></span>
          Loading older messages…
        </div>
        <article
          v-for="(message, index) in messages"
          :key="message.id || index"
          class="message-item"
          :aria-label="`Message from ${message.user}`"
        >
          <div class="message-avatar" aria-hidden="true">
            <img v-if="profileFor(message.user).avatar_url" :src="profileFor(message.user).avatar_url" alt="">
            <span v-else>{{ initials(profileFor(message.user).display_name) }}</span>
            <i :class="`presence-dot presence-${profileFor(message.user).presence}`"></i>
          </div>
          <div class="message-body">
            <div class="message-header">
              <span class="username">{{ profileFor(message.user).display_name }}</span>
              <span class="sr-only">({{ profileFor(message.user).presence }})</span>
              <time v-if="message.timestamp" class="timestamp" :datetime="formatMachineTimestamp(message.timestamp)">
                {{ formatTimestamp(message.timestamp) }}
              </time>
            </div>
            <div class="message-content" v-html="formatMessage(message.msg)"></div>
            <div v-if="message.id && !Number(message.archived)" class="message-reactions">
              <button
                v-for="reaction in reactionsFor(message.id)"
                :key="reaction.emoji"
                type="button"
                class="reaction-badge"
                :class="{ active: reaction.users.includes(username) }"
                :aria-pressed="reaction.users.includes(username)"
                :aria-label="`${reaction.emoji} reaction from ${reaction.users.join(', ')}. ${reaction.count} total.`"
                :title="reaction.users.join(', ')"
                @click="toggleReaction(message.id, reaction.emoji)"
              >
                <span aria-hidden="true">{{ reaction.emoji }}</span> {{ reaction.count }}
              </button>
              <button
                type="button"
                class="reaction-picker-toggle"
                :aria-expanded="reactionPickerMessageId === Number(message.id)"
                :aria-label="`React to message from ${message.user}`"
                @click="toggleReactionPicker(message.id)"
              >
                +
              </button>
              <fieldset v-if="reactionPickerMessageId === Number(message.id)" class="reaction-picker">
                <legend class="sr-only">Choose a reaction</legend>
                <button
                  v-for="emoji in reactionEmojis"
                  :key="emoji"
                  type="button"
                  :aria-label="`React with ${emoji}`"
                  @click="toggleReaction(message.id, emoji)"
                >
                  {{ emoji }}
                </button>
              </fieldset>
            </div>
          </div>
        </article>
        <div v-if="messages.length === 0" class="no-messages">
          {{ searchActive ? 'No messages match your search.' : 'No messages yet. Be the first to send a message!' }}
        </div>
      </div>
    </div>

    <div class="load-more-container" v-if="!loading && searchActive && hasMoreMessages">
      <button type="button" class="load-more-btn" @click="loadMoreMessages" :disabled="loadingMore">
        <span v-if="loadingMore" class="spinner-small" aria-hidden="true"></span>
        <span>{{ loadingMore ? 'Loading...' : 'Load More Messages' }}</span>
      </button>
    </div>

    <div v-if="typingIndicatorText" class="typing-indicator" role="status" aria-live="polite">
      <span>{{ typingIndicatorText }}</span>
      <span class="typing-dots" aria-hidden="true"><span>.</span><span>.</span><span>.</span></span>
    </div>

    <div class="message-form-container">
      <form @submit.prevent="sendMessage" class="message-form">
        <h2 id="compose-title" class="sr-only">Compose a message</h2>
        <div class="form-group" v-show="!sending">
          <label for="message-input">Message:</label>
          <textarea
            id="message-input"
            v-model="message"
            :class="{ 'error-field': error }"
            placeholder="Type your message here..."
            rows="2"
            maxlength="500"
            :aria-invalid="Boolean(error)"
            :aria-describedby="error ? 'formatting-help message-error' : 'formatting-help'"
            @focus="ensureComposerVisible"
            @input="handleTypingInput"
            @blur="stopTyping"
            @keydown.enter.exact.prevent="sendMessage"
            @keydown.esc.prevent="clearMessage"
          ></textarea>
          <fieldset class="formatting-help">
            <legend class="sr-only">Message formatting</legend>
            <button type="button" @click="insertFormatting('**', '**')" aria-label="Format as bold">B</button>
            <button type="button" @click="insertFormatting('*', '*')" aria-label="Format as italic">I</button>
            <button type="button" @click="insertFormatting('`', '`')" aria-label="Format as code">Code</button>
            <button type="button" @click="insertFormatting('\n> ', '')" aria-label="Format as quote">Quote</button>
            <span id="formatting-help" class="format-info">
              Supports Markdown: **bold**, *italic*, `code`, &gt; quote. Enter sends; Shift+Enter adds a line; Escape
              clears.
            </span>
          </fieldset>
          <div id="message-error" class="error" v-if="error" role="alert">{{ error }}</div>
        </div>

        <div class="loading-indicator-small" v-show="sending" role="status" aria-live="polite">
          <div class="spinner" aria-hidden="true"></div>
          <span>Sending message...</span>
        </div>

        <div class="form-actions">
          <button type="submit" class="send-btn" :disabled="sending || !message.trim()">Send Message</button>
          <button type="button" class="clear-btn" @click="clearMessage" :disabled="sending || !message.trim()">
            Clear
          </button>
        </div>
      </form>
    </div>
  </main>
</template>

<script>
  import { reactionEmojis } from '../config/reactions.ts';
  import {
    dismissFailedMessages,
    enablePushNotifications,
    promptInstall,
    subscribePwa,
    userScopedMessagesUrl,
  } from '../js/pwa.js';
  import ProfilePanel from './ProfilePanel.vue';
  import WebhookPanel from './WebhookPanel.vue';

  export default {
    components: { ProfilePanel, WebhookPanel },
    data() {
      return {
        // User information (from global properties set in main.js)
        username: this.$username,
        displayName: this.$username,
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
        historyBefore: null,
        historyObserver: null,

        // WebSocket connection state
        webSocket: null,
        webSocketConnected: false,
        reconnectAttempts: 0,
        reconnectInterval: null,
        lastMessageTime: null,
        pendingSearchAppend: false,

        // Form helpers
        selectionStart: 0,
        selectionEnd: 0,
        pwa: {
          online: navigator.onLine,
          installAvailable: false,
          serviceWorkerReady: false,
          queuedMessages: 0,
          failedMessages: 0,
        },
        unsubscribePwa: null,
        notificationsEnabled: false,
        search: {
          text: '',
          user: '',
          from: '',
          to: '',
        },
        searchActive: false,
        searchLoading: false,
        searchDebounce: null,
        searchHasLiveUpdates: false,
        searchError: '',
        activeSearchKey: '',
        searchRequestId: 0,
        searchAbortController: null,
        typingUsers: [],
        lastTypingSentAt: 0,
        typingStopTimeout: null,
        profiles: {},
        requestedProfiles: {},
        reactions: {},
        reactionPickerMessageId: null,
        reactionEmojis,
        channels: [],
        activeChannelId: null,
        dmUsername: '',
      };
    },
    watch: {
      messages() {
        void this.loadProfiles();
        void this.loadReactions();
      },
    },
    computed: {
      canEnableNotifications() {
        return Boolean(this.$pushPublicKey && !this.notificationsEnabled && 'PushManager' in window);
      },
      hasSearchFilters() {
        return Object.values(this.normalizedSearch()).some((value) => value !== '');
      },
      visibleTypingUsers() {
        return this.typingUsers.filter((user) => Number(user.user_id) !== Number(this.userId));
      },
      typingIndicatorText() {
        const names = this.visibleTypingUsers.map((user) => user.username);
        if (names.length === 0) return '';
        if (names.length === 1) return `${names[0]} is typing`;
        if (names.length === 2) return `${names[0]} and ${names[1]} are typing`;
        if (names.length === 3) return `${names[0]}, ${names[1]}, and ${names[2]} are typing`;
        return `${names.slice(0, 3).join(', ')}, and ${names.length - 3} others are typing`;
      },
    },
    mounted() {
      this.unsubscribePwa = subscribePwa((state) => {
        this.pwa = state;
      });
      void this.initializeChat();

      // Clean up when component is destroyed
      this.$nextTick(() => {
        window.addEventListener('beforeunload', this.cleanUp);
      });
    },
    beforeUnmount() {
      this.cleanUp();
      this.unsubscribePwa?.();
    },
    methods: {
      async initializeChat() {
        await this.loadChannels();
        if (this.activeChannelId === null) {
          return;
        }
        this.connectWebSocket();
      },
      async loadChannels() {
        try {
          const response = await fetch(this.$chatRoutes.channels);
          if (!response.ok) throw new Error('Channel request failed');
          this.channels = (await response.json()).channels || [];
          const requestedSlug = new URL(window.location.href).searchParams.get('channel');
          const requested = this.channels.find((channel) => channel.slug === requestedSlug);
          const selected =
            (requested?.is_member ? requested : null) ||
            this.channels.find((channel) => channel.slug === 'general') ||
            this.channels[0];
          if (selected && this.activeChannelId === null) {
            this.activeChannelId = Number(selected.id);
            const url = new URL(window.location.href);
            url.searchParams.set('channel', selected.slug);
            window.history.replaceState({}, '', url);
          }
        } catch {
          this.error = 'Channels could not be loaded.';
        }
      },
      dmLabel(channel) {
        return (channel.members || []).find((member) => member !== this.username) || 'Direct message';
      },
      async selectChannel(channel) {
        if (Number(channel.id) === Number(this.activeChannelId)) return;
        if (!channel.is_member) {
          try {
            const response = await fetch(`${this.$chatRoutes.channels}/${channel.id}/join`, {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
              },
            });
            this.refreshCsrfToken(response);
            if (!response.ok) throw new Error('Channel join failed');
            channel.is_member = true;
          } catch {
            this.error = 'The channel could not be joined.';
            return;
          }
        }
        this.stopTyping();
        this.activeChannelId = Number(channel.id);
        channel.unread_count = 0;
        this.messages = [];
        this.currentPage = 1;
        this.historyBefore = null;
        this.historyObserver?.disconnect();
        this.loadingMore = false;
        this.loading = true;
        this.clearSearchState();
        const url = new URL(window.location.href);
        url.searchParams.set('channel', channel.slug);
        window.history.replaceState({}, '', url);
        if (this.webSocketConnected) {
          this.webSocket.send(JSON.stringify({ type: 'channel_subscribe', channel_id: this.activeChannelId }));
        }
        this.loadMessages();
      },
      clearSearchState() {
        this.cancelSearchRequest();
        this.search = { text: '', user: '', from: '', to: '' };
        this.searchActive = false;
        this.searchLoading = false;
        this.searchHasLiveUpdates = false;
        this.searchError = '';
        this.activeSearchKey = '';
      },
      async createDm() {
        const username = this.dmUsername.trim();
        if (!username) return;
        try {
          const response = await fetch(this.$chatRoutes.dms, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ username }),
          });
          this.refreshCsrfToken(response);
          const payload = await response.json();
          if (!response.ok) throw new Error(payload.error?.message || 'Direct message failed');
          this.dmUsername = '';
          await this.loadChannels();
          const channel = this.channels.find((item) => Number(item.id) === Number(payload.channel.id));
          if (channel) this.selectChannel(channel);
        } catch (error) {
          this.error = error.message || 'The direct message could not be opened.';
        }
      },
      handleOwnProfile(profile) {
        this.displayName = profile.display_name || this.username;
        this.mergeProfiles([profile]);
        if (this.webSocketConnected) {
          this.webSocket.send(JSON.stringify({ type: 'presence_update', presence: profile.presence }));
        }
      },
      profileFor(username) {
        return (
          this.profiles[username] || {
            username,
            display_name: username,
            avatar_url: null,
            presence: 'offline',
          }
        );
      },
      initials(name) {
        return String(name || '?')
          .trim()
          .split(/\s+/)
          .slice(0, 2)
          .map((part) => part[0])
          .join('')
          .toUpperCase();
      },
      mergeProfiles(profiles) {
        this.profiles = Object.fromEntries([
          ...Object.entries(this.profiles),
          ...profiles.map((profile) => [profile.username, profile]),
        ]);
      },
      async loadProfiles() {
        const usernames = [
          ...new Set(this.messages.map((message) => String(message.user || '').trim()).filter(Boolean)),
        ].filter((username) => this.requestedProfiles[username] !== true);
        if (usernames.length === 0) return;
        this.requestedProfiles = Object.fromEntries([
          ...Object.entries(this.requestedProfiles),
          ...usernames.map((username) => [username, true]),
        ]);
        try {
          const params = new URLSearchParams({ usernames: usernames.join(',') });
          const response = await fetch(`${this.$chatRoutes.profiles}?${params}`);
          if (!response.ok) return;
          this.mergeProfiles((await response.json()).profiles || []);
        } catch {
          // Fallback initials and usernames remain usable when profiles are unavailable.
        }
      },
      clearMessage() {
        this.stopTyping();
        this.message = '';
        this.error = '';
        this.$nextTick(() => document.getElementById('message-input')?.focus());
      },
      dismissDeliveryFailures() {
        void dismissFailedMessages();
      },
      async installApp() {
        await promptInstall();
      },
      async enableNotifications() {
        try {
          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
          this.notificationsEnabled = await enablePushNotifications({
            endpoint: this.$chatRoutes.pushSubscriptions,
            csrfTokenName: this.$csrfToken,
            csrfToken,
            publicKey: this.$pushPublicKey,
          });
        } catch (error) {
          console.error('Could not enable notifications:', error);
          this.error = 'Notifications could not be enabled.';
        }
      },
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
        this.webSocket.addEventListener('open', () => {
          console.log('WebSocket connection established');
          this.webSocketConnected = true;
          this.reconnectAttempts = 0;

          if (this.reconnectInterval) {
            clearInterval(this.reconnectInterval);
            this.reconnectInterval = null;
          }

          if (this.activeChannelId !== null) {
            this.webSocket.send(JSON.stringify({ type: 'channel_subscribe', channel_id: this.activeChannelId }));
          }

          // Load initial messages
          this.loadMessages();
          this.warmMessagesCache();
        });

        // Listen for messages from the WebSocket server
        this.webSocket.addEventListener('message', (event) => {
          const data = JSON.parse(event.data);

          if (data.type === 'typing_state') {
            this.typingUsers = Array.isArray(data.users) ? data.users : [];
            return;
          }
          if (data.type === 'presence_state') {
            this.mergeProfiles(data.users || []);
            return;
          }
          if (data.type === 'reaction') {
            this.applyReactionUpdate(data);
            return;
          }
          if (data.type === 'channel_unread') {
            const channel = this.channels.find((item) => Number(item.id) === Number(data.channel_id));
            if (channel) channel.unread_count = Number(channel.unread_count || 0) + 1;
            return;
          }

          // Handle different message types (actions) from the server
          switch (data.action) {
            case 'messages':
              if (Number(data.data.channel_id) !== Number(this.activeChannelId)) break;
              if (this.searchActive) {
                break;
              }

              // Handle messages list response
              this.messages = data.data.messages || [];
              this.hasMoreMessages = data.data.pagination?.hasNext || false;
              this.loading = false;
              this.loadingMore = false;
              this.searchLoading = false;

              // Store timestamp of the newest message for refresh comparison
              if (this.messages.length > 0 && this.messages[0].timestamp) {
                this.lastMessageTime = this.messages[0].timestamp;
              }
              break;

            case 'newMessage':
              if (Number(data.data.channel_id) !== Number(this.activeChannelId)) break;
              // Handle new message broadcast from another user
              if (this.searchActive) {
                this.searchHasLiveUpdates = true;
                break;
              }

              if (!this.lastMessageTime || data.data.timestamp > this.lastMessageTime) {
                this.messages.push(data.data);
                this.lastMessageTime = data.data.timestamp;
              }
              break;

            case 'searchResults': {
              if (data.data.requestId !== this.searchRequestId || !this.isCurrentSearchResponse(data.data.filters)) {
                break;
              }

              const searchMessages = data.data.messages || [];
              this.messages = this.pendingSearchAppend ? [...this.messages, ...searchMessages] : searchMessages;
              this.hasMoreMessages = data.data.pagination?.hasNext || false;
              this.loading = false;
              this.loadingMore = false;
              this.searchLoading = false;
              this.searchActive = true;
              this.pendingSearchAppend = false;
              this.searchHasLiveUpdates = false;
              this.searchError = '';
              break;
            }

            case 'error':
              // Handle authentication or other errors from the server
              // The server sends this when token validation fails
              if (
                data.data.code === 'INVALID_SEARCH' &&
                this.searchActive &&
                data.data.requestId === this.searchRequestId
              ) {
                this.searchError = data.data.message || 'Search failed.';
                this.loading = false;
                this.loadingMore = false;
                this.searchLoading = false;
                this.pendingSearchAppend = false;
              } else if (data.data.code === 'AUTH_FAILED') {
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
        this.webSocket.addEventListener('close', () => {
          console.log('WebSocket connection closed');
          this.webSocketConnected = false;
          this.typingUsers = [];
          this.lastTypingSentAt = 0;

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
          if (this.searchActive && this.activeSearchKey) {
            void this.searchMessagesHttp(
              this.currentPage,
              this.pendingSearchAppend,
              this.searchRequestId,
              this.normalizedSearch(),
              this.activeSearchKey,
            );
            return;
          }
          if (this.loading) this.loadMessagesHttp();
        });
      },

      cleanUp() {
        if (this.searchDebounce) {
          clearTimeout(this.searchDebounce);
        }
        this.cancelSearchRequest();
        this.stopTyping();

        if (this.webSocket) {
          this.webSocket.close();
        }

        if (this.reconnectInterval) {
          clearInterval(this.reconnectInterval);
        }

        this.historyObserver?.disconnect();

        window.removeEventListener('beforeunload', this.cleanUp);
      },

      loadMessages() {
        if (this.activeChannelId === null) {
          this.loading = false;
          return;
        }

        this.searchActive = false;
        this.searchHasLiveUpdates = false;
        this.searchLoading = false;
        this.pendingSearchAppend = false;
        this.searchError = '';
        this.activeSearchKey = '';

        this.loading = true;
        this.loadMessagesHttp();
      },

      // HTTP fallback for loading messages
      async loadMessagesHttp() {
        if (this.activeChannelId === null) {
          this.loading = false;
          return false;
        }

        const channelId = this.activeChannelId;
        try {
          const response = await fetch(
            userScopedMessagesUrl(`${this.$chatRoutes.channels}/${channelId}/messages?limit=25`, this.userId),
          );
          const data = await response.json();
          if (!response.ok) throw new Error('Message request failed');
          if (Number(channelId) !== Number(this.activeChannelId) || this.searchActive) return false;

          this.messages = [...(data.messages || [])].reverse().map(this.normalizeMessage);
          this.hasMoreMessages = Boolean(data.pagination?.hasMore);
          this.historyBefore = data.pagination?.nextBefore || null;
          this.loading = false;

          // Store timestamp of the newest message for refresh comparison
          const newestMessage = this.messages[this.messages.length - 1];
          if (newestMessage?.timestamp) {
            this.lastMessageTime = newestMessage.timestamp;
          }
          await this.$nextTick();
          const messageWindow = this.$refs.messageWindow;
          if (messageWindow) messageWindow.scrollTop = messageWindow.scrollHeight;
          this.observeHistorySentinel();
          return true;
        } catch (error) {
          console.error('Error loading messages:', error);
          this.loading = false;
          return false;
        }
      },
      async warmMessagesCache() {
        if (this.activeChannelId === null) return;

        try {
          await fetch(
            userScopedMessagesUrl(
              `${this.$chatRoutes.channels}/${this.activeChannelId}/messages?limit=25`,
              this.userId,
            ),
          );
        } catch {
          // The live WebSocket remains authoritative; cache warming is best effort.
        }
      },
      reactionsFor(messageId) {
        return this.reactions[messageId] || [];
      },
      toggleReactionPicker(messageId) {
        const id = Number(messageId);
        this.reactionPickerMessageId = this.reactionPickerMessageId === id ? null : id;
      },
      applyReactionUpdate(update) {
        const messageId = Number(update.message_id);
        const existing = this.reactionsFor(messageId).filter((reaction) => reaction.emoji !== update.emoji);
        const next =
          Number(update.count) > 0
            ? [...existing, { emoji: update.emoji, count: Number(update.count), users: update.users || [] }]
            : existing;
        this.reactions = { ...this.reactions, [messageId]: next };
      },
      async loadReactions() {
        const messageIds = [...new Set(this.messages.map((item) => Number(item.id)).filter((id) => id > 0))];
        await Promise.all(
          messageIds.map(async (messageId) => {
            try {
              const response = await fetch(`${this.$chatRoutes.api}/${messageId}/reactions`);
              if (!response.ok) return;
              this.reactions = { ...this.reactions, [messageId]: await response.json() };
            } catch {
              // Reactions are additive; message reading remains available if this request fails.
            }
          }),
        );
      },
      async toggleReaction(messageId, emoji) {
        const active = this.reactionsFor(messageId).some(
          (reaction) => reaction.emoji === emoji && reaction.users.includes(this.username),
        );
        this.reactionPickerMessageId = null;
        if (this.webSocketConnected) {
          this.webSocket.send(
            JSON.stringify({
              type: active ? 'reaction_remove' : 'reaction_add',
              message_id: Number(messageId),
              emoji,
            }),
          );
          return;
        }

        try {
          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
          const endpoint = `${this.$chatRoutes.api}/${messageId}/reactions${active ? `/${encodeURIComponent(emoji)}` : ''}`;
          const response = await fetch(endpoint, {
            method: active ? 'DELETE' : 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
            },
            body: active ? undefined : JSON.stringify({ emoji }),
          });
          this.refreshCsrfToken(response);
          if (!response.ok) throw new Error('Reaction request failed');
          if (active) {
            const refreshed = await fetch(`${this.$chatRoutes.api}/${messageId}/reactions`);
            this.reactions = { ...this.reactions, [messageId]: await refreshed.json() };
          } else {
            const payload = await response.json();
            this.applyReactionUpdate({ message_id: messageId, ...payload.reaction });
          }
        } catch {
          this.error = 'The reaction could not be updated.';
        }
      },
      refreshCsrfToken(response) {
        const token = response.headers.get('X-CSRF-TOKEN');
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (token && meta) meta.setAttribute('content', token);
      },

      loadMoreMessages() {
        if (this.activeChannelId === null) return;
        if (this.loadingMore) return;

        if (this.searchActive) {
          this.loadingMore = true;
          this.currentPage++;
          this.executeSearch(this.currentPage, true);
          return;
        }

        void this.loadOlderMessages();
      },

      async loadOlderMessages() {
        if (
          this.loadingMore ||
          this.searchActive ||
          this.activeChannelId === null ||
          !this.hasMoreMessages ||
          this.historyBefore === null
        )
          return;

        this.loadingMore = true;
        const channelId = this.activeChannelId;
        const cursor = this.historyBefore;
        const messageWindow = this.$refs.messageWindow;
        const previousHeight = messageWindow?.scrollHeight || 0;
        const previousTop = messageWindow?.scrollTop || 0;
        try {
          const response = await fetch(
            userScopedMessagesUrl(
              `${this.$chatRoutes.channels}/${channelId}/messages?before=${cursor}&limit=25`,
              this.userId,
            ),
          );
          const data = await response.json();
          if (!response.ok) {
            throw new Error('Message history request failed');
          }
          if (
            Number(channelId) !== Number(this.activeChannelId) ||
            Number(cursor) !== Number(this.historyBefore) ||
            this.searchActive
          )
            return;

          if (data.messages && data.messages.length > 0) {
            this.messages = [...data.messages].reverse().map(this.normalizeMessage).concat(this.messages);
          }
          this.hasMoreMessages = Boolean(data.pagination?.hasMore);
          this.historyBefore = data.pagination?.nextBefore || null;
          await this.$nextTick();
          if (messageWindow) {
            messageWindow.scrollTop = previousTop + (messageWindow.scrollHeight - previousHeight);
          }
        } catch (error) {
          console.error('Error loading older messages:', error);
        } finally {
          this.loadingMore = false;
        }
      },

      observeHistorySentinel() {
        this.historyObserver?.disconnect();
        const root = this.$refs.messageWindow;
        const sentinel = this.$refs.historySentinel;
        if (!root || !sentinel || !this.hasMoreMessages || this.searchActive) return;

        this.historyObserver = new IntersectionObserver(
          (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) void this.loadOlderMessages();
          },
          { root, threshold: 0.1 },
        );
        this.historyObserver.observe(sentinel);
      },

      normalizeMessage(message) {
        return { ...message, timestamp: message.timestamp ?? message.time };
      },

      scheduleSearch() {
        if (this.searchDebounce) {
          clearTimeout(this.searchDebounce);
        }

        if (!this.hasSearchFilters) {
          if (this.searchActive) {
            this.clearSearch();
          }
          return;
        }

        this.searchDebounce = setTimeout(() => this.applySearch(), 400);
      },

      applySearch() {
        if (this.searchDebounce) {
          clearTimeout(this.searchDebounce);
          this.searchDebounce = null;
        }

        if (!this.hasSearchFilters) {
          this.clearSearch();
          return;
        }

        this.currentPage = 1;
        this.executeSearch(1, false);
      },

      clearSearch() {
        if (this.searchDebounce) {
          clearTimeout(this.searchDebounce);
          this.searchDebounce = null;
        }
        this.cancelSearchRequest();

        this.search = {
          text: '',
          user: '',
          from: '',
          to: '',
        };
        this.searchActive = false;
        this.searchLoading = false;
        this.searchHasLiveUpdates = false;
        this.searchError = '';
        this.activeSearchKey = '';
        this.currentPage = 1;
        this.loadMessages();
      },

      executeSearch(page = 1, append = false) {
        if (this.activeChannelId === null) return;

        const filters = this.normalizedSearch();
        if (!Object.values(filters).some((value) => value !== '')) {
          this.clearSearch();
          return;
        }

        const validationError = this.searchValidationError(filters);
        if (validationError) {
          this.searchError = validationError;
          this.loading = false;
          this.loadingMore = false;
          this.searchLoading = false;
          return;
        }

        this.searchActive = true;
        this.searchLoading = !append;
        this.pendingSearchAppend = append;
        this.searchHasLiveUpdates = false;
        this.searchError = '';
        this.activeSearchKey = this.searchKey(filters);
        this.searchRequestId++;
        const requestId = this.searchRequestId;

        if (append) {
          this.loadingMore = true;
        } else {
          this.loading = true;
        }

        if (!this.webSocketConnected) {
          void this.searchMessagesHttp(page, append, requestId, filters, this.activeSearchKey);
          return;
        }

        this.webSocket.send(
          JSON.stringify({
            action: 'getMessages',
            requestId,
            page,
            perPage: 10,
            search: this.compactSearch(filters),
            channel_id: this.activeChannelId,
          }),
        );

        setTimeout(() => {
          if (requestId === this.searchRequestId && (this.searchLoading || this.loadingMore || this.loading)) {
            this.loading = false;
            this.searchLoading = false;
            this.loadingMore = false;
            this.pendingSearchAppend = false;
            this.searchError = 'Search timed out. Please try again.';
          }
        }, 5000);
      },

      cancelSearchRequest() {
        this.searchRequestId++;
        this.searchAbortController?.abort();
        this.searchAbortController = null;
      },

      async searchMessagesHttp(page = 1, append = false, requestId, filters, searchKey) {
        this.searchAbortController?.abort();
        this.searchAbortController = new AbortController();

        try {
          const params = new URLSearchParams({
            page: String(page),
            per_page: '10',
          });

          for (const [key, value] of Object.entries(filters)) {
            if (value !== '') params.set(key, String(value));
          }

          const response = await fetch(
            userScopedMessagesUrl(`${this.searchEndpoint()}?${params.toString()}`, this.userId),
            { signal: this.searchAbortController.signal },
          );
          const data = await response.json();
          if (!response.ok) throw new Error('Message search failed');
          if (requestId !== this.searchRequestId || searchKey !== this.activeSearchKey) return false;
          if (!this.isCurrentSearchResponse(data.filters)) return false;

          const searchMessages = data.messages || [];
          this.messages = append ? [...this.messages, ...searchMessages] : searchMessages;
          this.hasMoreMessages = data.pagination?.hasNext || false;
          this.searchActive = true;
          this.searchError = '';
          return true;
        } catch (error) {
          if (error.name === 'AbortError') return false;
          console.error('Error searching messages:', error);
          this.searchError = 'Search failed. Please adjust your filters and try again.';
          if (append) this.currentPage--;
          return false;
        } finally {
          if (requestId === this.searchRequestId) {
            this.loading = false;
            this.loadingMore = false;
            this.searchLoading = false;
            this.pendingSearchAppend = false;
          }
        }
      },

      normalizedSearch() {
        return {
          text: this.search.text.trim(),
          user: this.search.user.trim(),
          from: this.dateBoundarySeconds(this.search.from, false),
          to: this.dateBoundarySeconds(this.search.to, true),
        };
      },

      compactSearch(filters) {
        return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
      },

      dateBoundarySeconds(dateValue, endOfDay) {
        if (!dateValue) return '';

        const [year, month, day] = dateValue.split('-').map((part) => Number.parseInt(part, 10));
        const date = endOfDay
          ? new Date(year, month - 1, day, 23, 59, 59, 999)
          : new Date(year, month - 1, day, 0, 0, 0, 0);

        return Number.isNaN(date.getTime()) ? Number.NaN : Math.floor(date.getTime() / 1000);
      },

      searchValidationError(filters) {
        if (filters.text.length > 500) return 'Search text must not exceed 500 characters.';
        if (filters.user.length > 255) return 'Username filter must not exceed 255 characters.';
        const dateValues = [filters.from, filters.to].filter((value) => value !== '');
        if (dateValues.some((value) => Number.isNaN(value))) return 'Search dates are invalid.';
        if (dateValues.some((value) => value < 0)) return 'Search dates must be on or after 1970-01-01.';
        if (filters.from !== '' && filters.to !== '' && filters.from > filters.to) {
          return 'From date must be on or before To date.';
        }
        return '';
      },

      searchKey(filters) {
        return JSON.stringify(this.compactSearch(filters));
      },

      isCurrentSearchResponse(filters) {
        return this.searchKey(this.normalizeResponseFilters(filters || {})) === this.activeSearchKey;
      },

      normalizeResponseFilters(filters) {
        return {
          text: String(filters.text || '').trim(),
          user: String(filters.user || '').trim(),
          from: this.numericResponseFilter(filters.from),
          to: this.numericResponseFilter(filters.to),
        };
      },

      numericResponseFilter(value) {
        if (value === undefined || value === null || value === '') return '';
        const numberValue = Number(value);
        return Number.isFinite(numberValue) ? numberValue : '';
      },

      searchEndpoint() {
        return `${this.$chatRoutes.channels}/${this.activeChannelId}/messages/search`;
      },

      handleTypingInput(event) {
        if (!event.target.value.trim()) {
          this.stopTyping();
          return;
        }

        this.startTyping();
      },

      ensureComposerVisible(event) {
        const composer = event.currentTarget.closest('.message-form-container');
        requestAnimationFrame(() => composer?.scrollIntoView({ block: 'end' }));
      },

      startTyping() {
        if (!this.webSocketConnected) return;

        const now = Date.now();
        if (now - this.lastTypingSentAt >= 3000) {
          this.webSocket.send(
            JSON.stringify({
              type: 'typing_start',
              user_id: Number(this.userId),
              username: this.username,
            }),
          );
          this.lastTypingSentAt = now;
        }

        if (this.typingStopTimeout) clearTimeout(this.typingStopTimeout);
        this.typingStopTimeout = setTimeout(() => this.stopTyping(), 5000);
      },

      stopTyping() {
        if (this.typingStopTimeout) {
          clearTimeout(this.typingStopTimeout);
          this.typingStopTimeout = null;
        }

        if (this.webSocketConnected && this.lastTypingSentAt > 0) {
          this.webSocket.send(
            JSON.stringify({
              type: 'typing_stop',
              user_id: Number(this.userId),
              username: this.username,
            }),
          );
        }

        this.lastTypingSentAt = 0;
      },

      sendMessage() {
        // Clear previous errors
        this.error = '';

        if (this.activeChannelId === null) {
          this.error = 'Choose a conversation before sending a message.';
          return;
        }

        // Validate message
        if (!this.message.trim()) {
          this.error = 'Message is required';
          return;
        }

        if (this.message.length > 500) {
          this.error = 'Message cannot exceed 500 characters';
          return;
        }

        this.stopTyping();

        this.sending = true;

        if (this.webSocketConnected) {
          // Send message via WebSocket
          this.webSocket.send(
            JSON.stringify({
              action: 'sendMessage',
              username: this.username,
              message: this.message,
              channel_id: this.activeChannelId,
            }),
          );

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
        if (this.activeChannelId === null) {
          this.sending = false;
          return;
        }

        let fetchCompleted = false;
        try {
          const response = await fetch(`${this.$chatRoutes.channels}/${this.activeChannelId}/messages`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ message: this.message }),
          });
          this.refreshCsrfToken(response);
          fetchCompleted = true;

          const data = await response.json();

          if (data?.error) {
            this.error = data.error.details?.message || data.error.message || 'Failed to send message';
          } else {
            if (this.searchActive) {
              this.searchHasLiveUpdates = true;
            } else {
              this.messages.push({
                user: this.username,
                msg: this.message,
                timestamp: Math.floor(Date.now() / 1000), // Current timestamp in seconds
              });
            }

            // Clear message field
            this.message = '';
          }
        } catch (error) {
          console.error('Error sending message:', error);
          if (!fetchCompleted && navigator.serviceWorker?.controller) {
            this.message = '';
            this.error = 'Message queued. It will be retried when delivery is available.';
          } else {
            this.error = 'Failed to send message. Please try again.';
          }
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
      formatMachineTimestamp(timestamp) {
        return new Date(timestamp * 1000).toISOString();
      },

      formatMessage(message) {
        if (!message) return '';

        const urls = [];
        const messageWithUrlTokens = String(message).replace(/https?:\/\/[^\s<>"']+/g, (url) => {
          const token = `\u0000CHAT_URL_${urls.length}\u0000`;
          urls.push(url);
          return token;
        });

        // Escape HTML to prevent XSS. URL tokens keep highlighting and
        // markdown formatting out of generated anchor attributes.
        let formattedMessage = this.escapeHtml(messageWithUrlTokens);
        formattedMessage = this.highlightSearchTerms(formattedMessage);

        // Format markdown-like syntax
        // Bold: **text**
        formattedMessage = formattedMessage.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // Italic: *text*
        formattedMessage = formattedMessage.replace(/\*(.*?)\*/g, '<em>$1</em>');

        // Code: `code`
        formattedMessage = formattedMessage.replace(/`(.*?)`/g, '<code>$1</code>');

        // Blockquote: > text
        formattedMessage = formattedMessage.replace(/^&gt; (.*)$/gm, '<blockquote>$1</blockquote>');

        // Restore sanitized URLs as links.
        urls.forEach((url, index) => {
          const anchor = `<a href="${this.escapeAttribute(url)}" target="_blank" rel="noopener noreferrer">${this.escapeHtml(url)}</a>`;
          formattedMessage = formattedMessage.replaceAll(`\u0000CHAT_URL_${index}\u0000`, anchor);
        });

        // Convert line breaks to <br>
        formattedMessage = formattedMessage.replace(/\n/g, '<br>');

        return formattedMessage;
      },

      escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      },

      escapeAttribute(text) {
        return String(text).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      },

      highlightSearchTerms(escapedText) {
        const term = this.normalizedSearch().text;
        if (!this.searchActive || !term) return escapedText;

        const escapedTerm = this.escapeHtml(term);
        const pattern = new RegExp(`(${this.escapeRegExp(escapedTerm)})`, 'gi');
        return escapedText.replace(pattern, '<mark>$1</mark>');
      },

      escapeRegExp(text) {
        return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
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
          textarea.setSelectionRange(selectionStart + prefix.length, selectionEnd + prefix.length);
        });
      },
    },
  };
</script>

<style lang="scss">
@use "sass:color";

.channel-sidebar {
  display: grid;
  gap: 0.5rem;
  padding: 1rem;
  border: 1px solid var(--border-color, #cbd5e1);
  border-radius: 0.75rem;
  background: var(--surface-color, #fff);

  h2 {
    margin: 0 0 0.25rem;
    font-size: 1rem;
  }
}

.channel-link {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  width: 100%;
  padding: 0.65rem 0.75rem;
  border: 1px solid transparent;
  border-radius: 0.5rem;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;

  &.active {
    border-color: #3f6398;
    background: color-mix(in srgb, #3f6398 12%, transparent);
    font-weight: 700;
  }
}

.channel-unread {
  min-width: 1.5rem;
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  background: #3f6398;
  color: #fff;
  text-align: center;
}

.dm-form {
  display: grid;
  gap: 0.35rem;
  margin-top: 0.5rem;

  div {
    display: flex;
    gap: 0.4rem;
  }

  input {
    min-width: 0;
    flex: 1;
  }
}

// Variables
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

// Global styles
* {
  box-sizing: border-box;
}

button,
input,
textarea,
.logout-btn {
  min-height: 44px;
}

button,
.logout-btn {
  min-width: 44px;
  touch-action: manipulation;
}

button:active:not(:disabled),
.logout-btn:active {
  filter: brightness(0.85);
  transform: translateY(1px);
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
  border-bottom: 1px solid color.adjust($primary-color, $lightness: -10%);
}

.user-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.welcome-text {
  font-size: 16px;
}

.chat-header .logout-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
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

// Message container
.message-container {
  position: relative;
  min-height: 300px;
  min-width: 0;
}

// Messages
.messages {
  height: 350px;
  overflow-y: auto;
  overflow-anchor: none;
  padding: 15px;
  background-color: $background-color;

  @media (max-width: 640px) {
    height: 300px;
  }
}

.history-boundary,
.history-loading {
  margin: 0 0 12px;
  color: $light-text-color;
  text-align: center;
}

.history-sentinel {
  height: 1px;
}

.message-item {
  min-width: 0;
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
  gap: 8px;
  margin-bottom: 5px;
  font-size: 14px;
}

.username {
  min-width: 0;
  overflow-wrap: anywhere;
  font-weight: bold;
  color: $primary-color;
}

.timestamp {
  flex: 0 0 auto;
  color: $light-text-color;
  font-size: 12px;
}

.message-content {
  min-width: 0;
  max-width: 100%;
  line-height: 1.5;
  word-break: break-word;
  overflow-wrap: anywhere;

  a {
    color: $primary-color;
    text-decoration: none;

    &:hover {
      text-decoration: underline;
    }
  }

  code {
    max-width: 100%;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    background-color: $background-color;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 0.9em;
  }

  blockquote {
    max-width: 100%;
    border-left: 3px solid $border-color;
    margin: 5px 0;
    padding-left: 10px;
    color: $secondary-color;
  }

  pre {
    max-width: 100%;
    overflow-x: auto;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
  }

  mark {
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
    overflow-wrap: anywhere;

    &:focus {
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

// Responsive adjustments
@media (max-width: 640px) {
  .chat-container {
    border-radius: 0;
    box-shadow: none;
    margin: 0;
    height: 100vh;
    height: 100dvh;
    width: 100%;
    max-width: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow-x: hidden;
    overflow-y: auto;
    scroll-padding-bottom: calc(120px + var(--chat-safe-area-bottom));
  }

  .chat-header {
    padding-top: calc(15px + var(--chat-safe-area-top));
    padding-right: calc(15px + var(--chat-safe-area-right));
    padding-left: calc(15px + var(--chat-safe-area-left));
  }

  .user-info {
    flex-wrap: wrap;
  }

  .welcome-text {
    flex: 1 1 100%;
  }

  .search-panel,
  .messages,
  .load-more-container,
  .typing-indicator {
    padding-right: calc(15px + var(--chat-safe-area-right));
    padding-left: calc(15px + var(--chat-safe-area-left));
  }

  .message-container {
    flex: 1 0 180px;
    min-height: 180px;
    overflow: hidden;
  }

  .messages {
    height: 100%;
  }

  .form-actions {
    flex-wrap: wrap;

    button {
      flex: 1 1 120px;
      padding: 12px 15px;
    }
  }

  .message-form-container {
    position: sticky;
    bottom: 0;
    z-index: 2;
    padding-right: calc(15px + var(--chat-safe-area-right));
    padding-bottom: calc(15px + var(--chat-safe-area-bottom));
    padding-left: calc(15px + var(--chat-safe-area-left));
    background-color: white;
  }

  #message-input {
    scroll-margin-bottom: calc(120px + var(--chat-safe-area-bottom));
  }
}
</style>
