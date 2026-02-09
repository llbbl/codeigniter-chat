/**
 * ============================================================================
 * SVELTE CHAT APPLICATION - ENTRY POINT
 * ============================================================================
 *
 * This is the main entry point for the Svelte chat application.
 * It mounts the root App component to the DOM and passes in configuration
 * from the PHP backend.
 *
 * Svelte vs Vue Entry Point Comparison:
 * -------------------------------------
 * Vue uses createApp() and app.mount(), while Svelte uses the mount() function.
 * In Svelte 5, we use the mount() function from 'svelte' to attach our component.
 *
 * The configuration pattern is similar - we read from window globals that
 * are set by the PHP view (svelteView.php).
 *
 * ============================================================================
 */

// Import the root Svelte component
import App from './App.svelte';

// Import shared CSS styles
import '../css/chat.scss';

// Import mount function from Svelte 5
import { mount } from 'svelte';

/**
 * Configuration passed from PHP
 *
 * These values are set in svelteView.php and read here.
 * This pattern allows server-side data to be passed to the frontend
 * without making additional API calls.
 */
const config = {
    // API routes for chat operations
    chatRoutes: {
        update: window.CHAT_ROUTES?.update || '/chat/update',
        api: window.CHAT_ROUTES?.svelteApi || '/chat/svelteApi'
    },

    // CSRF token name for secure form submissions
    csrfTokenName: window.CSRF_TOKEN_NAME || 'csrf_test_name',

    // Current user information from session
    username: window.CURRENT_USERNAME || 'Guest',
    userId: window.CURRENT_USER_ID || 0,

    // WebSocket authentication token (generated on login)
    wsToken: window.WEBSOCKET_TOKEN || ''
};

/**
 * Mount the Svelte App component
 *
 * In Svelte 5, we use the mount() function instead of the older
 * 'new Component()' syntax. This provides better tree-shaking
 * and aligns with modern JavaScript patterns.
 *
 * The mount() function takes:
 * - component: The Svelte component to mount
 * - options: { target, props, ... }
 *
 * We pass our configuration as props, making them available
 * inside the App component via $props().
 */
const app = mount(App, {
    // The DOM element where the app will be rendered
    target: document.getElementById('app'),

    // Props passed to the root component
    // These are accessed in App.svelte using $props()
    props: {
        config
    }
});

// Export the app instance for debugging (optional)
export default app;
