/**
 * AI Chat Application API Client (JavaScript)
 * 
 * A comprehensive client library for interacting with the AI Chat Application API.
 * Supports authentication, conversation management, and real-time messaging.
 * 
 * @version 1.0.0
 * @author AI Chat Team
 */

class AiChatApiClient {
    /**
     * Initialize the API client
     * @param {Object} config - Configuration options
     * @param {string} config.baseUrl - Base URL of the API
     * @param {string} [config.token] - JWT authentication token
     * @param {number} [config.timeout=30000] - Request timeout in milliseconds
     */
    constructor(config) {
        this.baseUrl = config.baseUrl || 'http://localhost:8080';
        this.token = config.token || null;
        this.timeout = config.timeout || 30000;
        
        // Default headers
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    }

    /**
     * Set the authentication token
     * @param {string} token - JWT token
     */
    setToken(token) {
        this.token = token;
    }

    /**
     * Get headers with authentication
     * @returns {Object} Headers object
     */
    getHeaders() {
        const headers = { ...this.defaultHeaders };
        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }
        return headers;
    }

    /**
     * Make HTTP request
     * @param {string} method - HTTP method
     * @param {string} endpoint - API endpoint
     * @param {Object} [data] - Request data
     * @returns {Promise<Object>} Response data
     */
    async request(method, endpoint, data = null) {
        const url = `${this.baseUrl}${endpoint}`;
        const config = {
            method: method.toUpperCase(),
            headers: this.getHeaders(),
            timeout: this.timeout
        };

        if (data && ['POST', 'PUT', 'PATCH'].includes(config.method)) {
            config.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, config);
            const responseData = await response.json();

            if (!response.ok) {
                throw new Error(responseData.message || `HTTP ${response.status}`);
            }

            return responseData;
        } catch (error) {
            console.error('API Request Error:', error);
            throw error;
        }
    }

    // Authentication Methods

    /**
     * Login user
     * @param {string} email - User email
     * @param {string} password - User password
     * @returns {Promise<Object>} Login response with token and user info
     */
    async login(email, password) {
        const response = await this.request('POST', '/auth/login', {
            email,
            password
        });
        
        if (response.success && response.token) {
            this.setToken(response.token);
        }
        
        return response;
    }

    /**
     * Register new user
     * @param {string} name - User name
     * @param {string} email - User email
     * @param {string} password - User password
     * @returns {Promise<Object>} Registration response
     */
    async register(name, email, password) {
        return await this.request('POST', '/auth/register', {
            name,
            email,
            password
        });
    }

    /**
     * Logout user
     * @returns {Promise<Object>} Logout response
     */
    async logout() {
        const response = await this.request('POST', '/auth/logout');
        this.token = null;
        return response;
    }

    /**
     * Get current user information
     * @returns {Promise<Object>} User information
     */
    async getCurrentUser() {
        return await this.request('GET', '/auth/me');
    }

    // Conversation Methods

    /**
     * Get all conversations
     * @param {Object} [params] - Query parameters
     * @param {number} [params.page=1] - Page number
     * @param {number} [params.limit=20] - Items per page
     * @returns {Promise<Object>} Conversations list with pagination
     */
    async getConversations(params = {}) {
        const query = new URLSearchParams({
            page: params.page || 1,
            limit: params.limit || 20
        });
        
        return await this.request('GET', `/chat/conversations?${query}`);
    }

    /**
     * Create new conversation
     * @param {Object} data - Conversation data
     * @param {string} [data.title] - Conversation title
     * @param {string} [data.description] - Conversation description
     * @returns {Promise<Object>} Created conversation
     */
    async createConversation(data = {}) {
        return await this.request('POST', '/chat/conversations', data);
    }

    /**
     * Get specific conversation with messages
     * @param {number} conversationId - Conversation ID
     * @returns {Promise<Object>} Conversation with messages
     */
    async getConversation(conversationId) {
        return await this.request('GET', `/chat/conversations/${conversationId}`);
    }

    /**
     * Update conversation
     * @param {number} conversationId - Conversation ID
     * @param {Object} data - Update data
     * @param {string} [data.title] - New title
     * @param {string} [data.description] - New description
     * @returns {Promise<Object>} Updated conversation
     */
    async updateConversation(conversationId, data) {
        return await this.request('PUT', `/chat/conversations/${conversationId}`, data);
    }

    /**
     * Delete conversation
     * @param {number} conversationId - Conversation ID
     * @returns {Promise<Object>} Deletion response
     */
    async deleteConversation(conversationId) {
        return await this.request('DELETE', `/chat/conversations/${conversationId}`);
    }

    // Message Methods

    /**
     * Get messages for a conversation
     * @param {number} conversationId - Conversation ID
     * @param {Object} [params] - Query parameters
     * @param {number} [params.page=1] - Page number
     * @param {number} [params.limit=50] - Items per page
     * @returns {Promise<Object>} Messages list with pagination
     */
    async getMessages(conversationId, params = {}) {
        const query = new URLSearchParams({
            page: params.page || 1,
            limit: params.limit || 50
        });
        
        return await this.request('GET', `/chat/conversations/${conversationId}/messages?${query}`);
    }

    /**
     * Send message in conversation
     * @param {number} conversationId - Conversation ID
     * @param {Object} data - Message data
     * @param {string} data.content - Message content
     * @param {string[]} [data.attachments] - File attachments
     * @returns {Promise<Object>} Sent message and AI response
     */
    async sendMessage(conversationId, data) {
        return await this.request('POST', `/chat/conversations/${conversationId}/messages`, data);
    }

    // File Upload Methods

    /**
     * Upload file
     * @param {File} file - File to upload
     * @param {number} [conversationId] - Associated conversation ID
     * @returns {Promise<Object>} Upload response
     */
    async uploadFile(file, conversationId = null) {
        const formData = new FormData();
        formData.append('file', file);
        
        if (conversationId) {
            formData.append('conversation_id', conversationId);
        }

        const url = `${this.baseUrl}/chat/upload`;
        const headers = { ...this.getHeaders() };
        delete headers['Content-Type']; // Let browser set it for FormData

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers,
                body: formData
            });

            const responseData = await response.json();

            if (!response.ok) {
                throw new Error(responseData.message || `HTTP ${response.status}`);
            }

            return responseData;
        } catch (error) {
            console.error('File Upload Error:', error);
            throw error;
        }
    }

    // WebSocket Methods

    /**
     * Connect to WebSocket for real-time chat
     * @param {Object} [options] - WebSocket options
     * @param {Function} [options.onMessage] - Message handler
     * @param {Function} [options.onError] - Error handler
     * @param {Function} [options.onClose] - Close handler
     * @returns {WebSocket} WebSocket connection
     */
    connectWebSocket(options = {}) {
        const wsUrl = this.baseUrl.replace(/^http/, 'ws') + '/chat/stream';
        const ws = new WebSocket(wsUrl);

        ws.onopen = function(event) {
            console.log('WebSocket connected');
            if (options.onOpen) options.onOpen(event);
        };

        ws.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                if (options.onMessage) options.onMessage(data);
            } catch (e) {
                console.error('WebSocket message parse error:', e);
            }
        };

        ws.onerror = function(event) {
            console.error('WebSocket error:', event);
            if (options.onError) options.onError(event);
        };

        ws.onclose = function(event) {
            console.log('WebSocket closed');
            if (options.onClose) options.onClose(event);
        };

        return ws;
    }
}

// Export for different module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AiChatApiClient;
} else if (typeof define === 'function' && define.amd) {
    define([], function() { return AiChatApiClient; });
} else {
    window.AiChatApiClient = AiChatApiClient;
}

/**
 * Usage Examples:
 * 
 * // Initialize client
 * const client = new AiChatApiClient({
 *     baseUrl: 'https://api.example.com',
 *     timeout: 30000
 * });
 * 
 * // Login
 * try {
 *     const loginResponse = await client.login('user@example.com', 'password123');
 *     console.log('Logged in:', loginResponse.user);
 * } catch (error) {
 *     console.error('Login failed:', error.message);
 * }
 * 
 * // Create conversation
 * const conversation = await client.createConversation({
 *     title: 'My First Chat',
 *     description: 'Learning about AI'
 * });
 * 
 * // Send message
 * const messageResponse = await client.sendMessage(conversation.conversation.id, {
 *     content: 'Hello, how can you help me today?'
 * });
 * 
 * // Connect WebSocket for real-time updates
 * const ws = client.connectWebSocket({
 *     onMessage: (data) => {
 *         console.log('New message:', data);
 *     },
 *     onError: (error) => {
 *         console.error('WebSocket error:', error);
 *     }
 * });
 */