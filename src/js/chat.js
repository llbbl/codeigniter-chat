/**
 * JavaScript for the XML version of the chat application
 *
 * This file demonstrates how to build a chat application using:
 * - Native Fetch API for AJAX requests (no jQuery needed!)
 * - XML response parsing
 * - Vanilla JavaScript DOM manipulation
 *
 * The Fetch API is a modern, promise-based way to make HTTP requests
 * that is built into all modern browsers.
 */

// Import CSS styles
import '../css/chat.css';

// ============================================================================
// CONFIGURATION
// ============================================================================

// Track current page for lazy loading (pagination)
let currentPage = 1;
const messagesPerPage = 10;
let hasMoreMessages = true;

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Escapes HTML special characters to prevent XSS attacks
 * This is important when displaying user-generated content!
 *
 * @param {string} text - The text to escape
 * @returns {string} - The escaped text safe for HTML display
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Shorthand function to select a single DOM element
 * Similar to jQuery's $() but returns only the first match
 *
 * @param {string} selector - CSS selector string
 * @returns {Element|null} - The matched element or null
 */
function $(selector) {
    return document.querySelector(selector);
}

/**
 * Shorthand function to select multiple DOM elements
 * Similar to jQuery's $() when multiple elements match
 *
 * @param {string} selector - CSS selector string
 * @returns {NodeList} - List of matched elements
 */
function $$(selector) {
    return document.querySelectorAll(selector);
}

/**
 * Gets the CSRF token from the meta tag
 * CSRF tokens protect against Cross-Site Request Forgery attacks
 *
 * @returns {string} - The CSRF token value
 */
function getCsrfToken() {
    return $('meta[name="csrf-token"]').getAttribute('content');
}

// ============================================================================
// UI HELPER FUNCTIONS
// ============================================================================

/**
 * Shows the loading indicator and hides the form inputs
 * Called when submitting a message to provide visual feedback
 */
function showLoading() {
    $('#contentLoading').style.display = 'block';
    $('#txt').style.display = 'none';
}

/**
 * Hides the loading indicator and shows the form inputs
 * Called after a message submission completes
 */
function hideLoading() {
    $('#contentLoading').style.display = 'none';
    $('#txt').style.display = 'block';
}

/**
 * Clears all error messages and removes error styling from inputs
 */
function clearErrors() {
    // Clear error text
    $$('.error').forEach(el => el.textContent = '');
    // Remove error styling from inputs
    $$('input').forEach(el => el.classList.remove('error-field'));
}

/**
 * Displays an error message for a specific field
 *
 * @param {string} fieldId - The ID of the error display element
 * @param {string} message - The error message to display
 * @param {string} inputId - The ID of the input field to highlight
 */
function showError(fieldId, message, inputId) {
    $(fieldId).textContent = message;
    $(inputId).classList.add('error-field');
}

/**
 * Creates a message element safely using DOM methods
 * This avoids innerHTML and ensures proper escaping
 *
 * @param {string} author - The message author's name
 * @param {string} message - The message content
 * @returns {DocumentFragment} - A document fragment containing the message elements
 */
function createMessageElement(author, message) {
    const fragment = document.createDocumentFragment();

    // Create bold element for author name
    const bold = document.createElement('b');
    bold.textContent = author;

    // Create text node for ": " and message
    const textNode = document.createTextNode(': ' + message);

    // Create line break
    const br = document.createElement('br');

    // Assemble the fragment
    fragment.appendChild(bold);
    fragment.appendChild(textNode);
    fragment.appendChild(br);

    return fragment;
}

// ============================================================================
// MESSAGE HANDLING FUNCTIONS
// ============================================================================

/**
 * Parses XML response and adds messages to the chat window
 *
 * XML responses have this structure:
 * <messages>
 *   <message>
 *     <author>Username</author>
 *     <text>Message content</text>
 *   </message>
 *   <pagination>
 *     <totalPages>5</totalPages>
 *   </pagination>
 * </messages>
 *
 * @param {Document} xml - The parsed XML document
 * @param {boolean} append - If true, adds to the end; if false, adds to the beginning
 * @returns {number} - The number of messages added
 */
function addMessages(xml, append = false) {
    let messagesAdded = 0;
    const messageWindow = $('#messagewindow');

    // Find all <message> elements in the XML
    const messages = xml.querySelectorAll('message');

    messages.forEach(message => {
        // Extract author and text from the XML structure
        const author = message.querySelector('author').textContent;
        const msg = message.querySelector('text').textContent;

        // Create message element safely (no innerHTML)
        const messageFragment = createMessageElement(author, msg);

        if (append) {
            // Add to the end of the message window
            messageWindow.appendChild(messageFragment);
        } else {
            // Add to the end (for initial load, messages come in order)
            messageWindow.appendChild(messageFragment);
        }
        messagesAdded++;
    });

    // Check pagination info from XML
    const totalPagesEl = xml.querySelector('pagination totalPages');
    const totalPages = totalPagesEl ? parseInt(totalPagesEl.textContent) : 0;
    hasMoreMessages = currentPage < totalPages;

    // Update the "Load More" button visibility
    const loadMoreBtn = $('#load-more-btn');
    if (hasMoreMessages) {
        loadMoreBtn.style.display = 'block';
        loadMoreBtn.textContent = 'Load More Messages';
    } else {
        loadMoreBtn.style.display = 'none';
    }

    return messagesAdded;
}

// ============================================================================
// API FUNCTIONS (using Fetch API)
// ============================================================================

/**
 * Loads messages from the server using the Fetch API
 *
 * The Fetch API returns a Promise, which we handle with .then()
 * For XML responses, we use response.text() and then parse it
 *
 * @param {number} page - The page number to load
 */
function loadMsg(page = 1) {
    // Build the URL with query parameters for pagination
    const url = `${CHAT_ROUTES.backend}?page=${page}&per_page=${messagesPerPage}`;

    // Use fetch() to make a GET request
    // fetch() returns a Promise that resolves to a Response object
    fetch(url, {
        method: 'GET',
        headers: {
            // Include CSRF token for security
            'X-CSRF-TOKEN': getCsrfToken()
        }
    })
    .then(response => {
        // Check if the request was successful
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        // For XML responses, we get the raw text first
        return response.text();
    })
    .then(xmlText => {
        // Parse the XML text into a Document object
        const parser = new DOMParser();
        const xml = parser.parseFromString(xmlText, 'text/xml');

        // Remove the "Loading..." text
        const loading = $('#loading');
        if (loading) {
            loading.remove();
        }

        // Add the messages to the chat window
        addMessages(xml);
    })
    .catch(error => {
        // Handle any errors that occurred during the fetch
        console.error('Error loading messages:', error);
    });
}

/**
 * Loads older messages when the user clicks "Load More"
 *
 * @param {number} page - The page number to load
 */
function loadOlderMessages(page) {
    const url = `${CHAT_ROUTES.backend}?page=${page}&per_page=${messagesPerPage}`;
    const loadMoreBtn = $('#load-more-btn');

    fetch(url, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken()
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(xmlText => {
        const parser = new DOMParser();
        const xml = parser.parseFromString(xmlText, 'text/xml');

        const messagesAdded = addMessages(xml, true);

        if (messagesAdded === 0) {
            loadMoreBtn.textContent = 'No more messages';
            loadMoreBtn.disabled = true;
        } else {
            loadMoreBtn.textContent = 'Load More Messages';
        }
    })
    .catch(error => {
        // If loading fails, revert the page counter and show error
        currentPage--;
        loadMoreBtn.textContent = 'Failed to load. Try again.';
        console.error('Error loading older messages:', error);
    });
}

/**
 * Sends a new message to the server
 *
 * For POST requests with form data, we use FormData or URLSearchParams
 * Here we use URLSearchParams for application/x-www-form-urlencoded format
 *
 * @param {string} message - The message content to send
 */
function postMessage(message) {
    showLoading();

    // Create the form data to send
    // URLSearchParams creates application/x-www-form-urlencoded data
    const formData = new URLSearchParams();
    formData.append('message', message);
    formData.append('action', 'postmsg');
    formData.append(CSRF_TOKEN_NAME, getCsrfToken());

    // Make a POST request with fetch()
    fetch(CHAT_ROUTES.update, {
        method: 'POST',
        headers: {
            // This header tells the server we're sending form data
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: formData.toString()
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        // The server returns JSON for the response
        return response.json();
    })
    .then(data => {
        hideLoading();

        if (data && data.success === false) {
            // Display server-side validation errors
            if (data.errors && data.errors.message) {
                showError('#content-error', data.errors.message, '#content');
            }
        } else {
            // Success! Add the message to the chat window
            const messageWindow = $('#messagewindow');
            const messageFragment = createMessageElement(CURRENT_USERNAME, message);

            // prepend = add to the beginning (newest messages first)
            messageWindow.insertBefore(messageFragment, messageWindow.firstChild);

            // Clear the input field and refocus it
            const contentInput = $('#content');
            contentInput.value = '';
            contentInput.focus();
        }
    })
    .catch(error => {
        hideLoading();
        alert('An error occurred while sending your message. Please try again.');
        console.error('Error posting message:', error);
    });
}

// ============================================================================
// FORM VALIDATION
// ============================================================================

/**
 * Validates the message before submission
 *
 * @param {string} message - The message to validate
 * @returns {boolean} - True if valid, false otherwise
 */
function validateMessage(message) {
    if (message === '') {
        showError('#content-error', 'Message is required', '#content');
        return false;
    }

    if (message.length > 500) {
        showError('#content-error', 'Message cannot exceed 500 characters', '#content');
        return false;
    }

    return true;
}

// ============================================================================
// INITIALIZATION
// ============================================================================

/**
 * Initialize the chat application when the DOM is fully loaded
 *
 * DOMContentLoaded is similar to jQuery's $(document).ready()
 * It fires when the HTML is parsed, before images and stylesheets are loaded
 */
document.addEventListener('DOMContentLoaded', function() {
    // Load initial messages
    loadMsg(currentPage);
    hideLoading();

    // Create and add the "Load More" button
    const messageWindow = $('#messagewindow');
    const loadMoreContainer = document.createElement('div');
    loadMoreContainer.id = 'load-more-container';

    const loadMoreBtn = document.createElement('button');
    loadMoreBtn.id = 'load-more-btn';
    loadMoreBtn.style.display = 'none';
    loadMoreBtn.textContent = 'Load More Messages';
    loadMoreContainer.appendChild(loadMoreBtn);

    messageWindow.after(loadMoreContainer);

    // Add click handler for the "Load More" button
    $('#load-more-btn').addEventListener('click', function() {
        if (hasMoreMessages) {
            currentPage++;
            this.textContent = 'Loading...';
            loadOlderMessages(currentPage);
        }
    });

    // Handle form submission
    $('form#chatform').addEventListener('submit', function(event) {
        // Prevent the default form submission (page reload)
        event.preventDefault();

        // Clear any previous errors
        clearErrors();

        // Get and trim the message value
        const message = $('#content').value.trim();

        // Validate and send if valid
        if (validateMessage(message)) {
            postMessage(message);
        }
    });
});
