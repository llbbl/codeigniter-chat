import { createApp } from 'vue';
import App from './App.vue';

// Import CSS
import '../css/chat.scss';

// Create Vue app
const app = createApp(App);

// Define global properties
app.config.globalProperties.$chatRoutes = {
    update: window.CHAT_ROUTES.update,
    api: window.CHAT_ROUTES.vueApi
};
app.config.globalProperties.$csrfToken = window.CSRF_TOKEN_NAME;
app.config.globalProperties.$username = window.CURRENT_USERNAME;

// Mount the app
app.mount('#app');