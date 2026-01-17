import { createApp } from 'vue';
import App from './App.vue';

// Import CSS
import '../css/chat.scss';

// Create Vue app
const app = createApp(App);

/**
 * ============================================================================
 * GLOBAL PROPERTIES
 * ============================================================================
 *
 * These properties are made available to all Vue components via `this.$propertyName`.
 * They're populated from the window object, which is set in vueView.php.
 *
 * This pattern allows us to pass server-side data (like session info and tokens)
 * to our Vue application without making additional API calls.
 *
 * ============================================================================
 */

// API routes for chat operations
app.config.globalProperties.$chatRoutes = {
    update: window.CHAT_ROUTES.update,
    api: window.CHAT_ROUTES.vueApi
};

// CSRF token name for form submissions
app.config.globalProperties.$csrfToken = window.CSRF_TOKEN_NAME;

// Current user information
app.config.globalProperties.$username = window.CURRENT_USERNAME;
app.config.globalProperties.$userId = window.CURRENT_USER_ID;

/**
 * WebSocket Authentication Credentials
 *
 * These are used to authenticate the WebSocket connection.
 * The token is generated on login and validated by the WebSocket server.
 *
 * Usage in components:
 *   const wsUrl = `ws://localhost:8080?token=${this.$wsToken}&user_id=${this.$userId}`;
 *
 * @see App.vue connectWebSocket() method for implementation
 */
app.config.globalProperties.$wsToken = window.WEBSOCKET_TOKEN;

// Mount the app
app.mount('#app');
