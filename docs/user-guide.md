# CodeIgniter Chat User Guide

This user guide provides comprehensive instructions for using the CodeIgniter Chat application. It covers all available chat interfaces and features, with examples to help you get started quickly.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Chat Interfaces](#chat-interfaces)
   - [XML Chat](#xml-chat)
   - [JSON Chat](#json-chat)
   - [HTML Chat](#html-chat)
   - [Vue.js Chat](#vuejs-chat)
3. [User Account Management](#user-account-management)
   - [Registration](#registration)
   - [Login](#login)
   - [Logout](#logout)
4. [Chat Features](#chat-features)
   - [Sending Messages](#sending-messages)
   - [Message Formatting](#message-formatting)
   - [Loading Older Messages](#loading-older-messages)
   - [Real-time Updates](#real-time-updates)
5. [Troubleshooting](#troubleshooting)
6. [FAQ](#faq)

## Getting Started

CodeIgniter Chat is a web-based chat application that allows users to communicate in real-time. It offers multiple interfaces, each demonstrating different web technologies.

### System Requirements

- Modern web browser (Chrome, Firefox, Safari, Edge)
- JavaScript enabled
- Internet connection

### Accessing the Chat

After installation, you can access the chat application at:

```
http://your-domain.com/
```

You'll be presented with the home page, from which you can navigate to the different chat interfaces.

## Chat Interfaces

CodeIgniter Chat offers four different interfaces, each demonstrating a different approach to building web applications.

### XML Chat

**URL**: `/chat`

The XML chat interface uses jQuery with AJAX to load and post messages in XML format. This is a traditional approach to building dynamic web applications.

**Features**:
- Dynamic updates without page reloads
- Efficient data exchange using XML
- Pagination for message history

**Example Usage**:
1. Navigate to `/chat`
2. Log in if prompted
3. Type your message in the input field
4. Click "Send" or press Enter to send your message
5. Messages will appear in the chat window automatically

### JSON Chat

**URL**: `/chat/json`

The JSON chat interface is similar to the XML interface but uses JSON for data exchange, which is more lightweight and easier to work with in JavaScript.

**Features**:
- Dynamic updates without page reloads
- Efficient data exchange using JSON
- Pagination for message history

**Example Usage**:
1. Navigate to `/chat/json`
2. Log in if prompted
3. Type your message in the input field
4. Click "Send" or press Enter to send your message
5. Messages will appear in the chat window automatically

### HTML Chat

**URL**: `/chat/html`

The HTML chat interface uses traditional form submission with page reloads. This approach works even without JavaScript and is the most compatible with older browsers.

**Features**:
- Works without JavaScript
- Simple implementation
- Pagination for message history

**Example Usage**:
1. Navigate to `/chat/html`
2. Log in if prompted
3. Type your message in the input field
4. Click "Send" to submit your message
5. The page will reload to show your message

### Vue.js Chat

**URL**: `/chat/vue`

The Vue.js chat interface uses the Vue.js framework for a modern, reactive user interface. This approach provides the best user experience with real-time updates and a component-based architecture.

**Features**:
- Real-time updates using WebSockets
- Modern, responsive design
- Message formatting with Markdown
- Lazy loading of older messages
- Timestamps and user information

**Example Usage**:
1. Navigate to `/chat/vue`
2. Log in if prompted
3. Type your message in the input field
4. Use the formatting buttons to add formatting to your message
5. Click "Send" or press Enter to send your message
6. Messages will appear in the chat window in real-time

## User Account Management

### Registration

To use the chat application, you need to create an account.

1. Navigate to `/auth/register`
2. Fill in the registration form:
   - Username: Choose a unique username (alphanumeric characters only)
   - Email: Enter a valid email address
   - Password: Choose a secure password (at least 8 characters)
   - Confirm Password: Re-enter your password
3. Click "Register" to create your account
4. You'll be redirected to the login page

### Login

Once you have an account, you can log in to use the chat application.

1. Navigate to `/auth/login`
2. Enter your username and password
3. Click "Login" to access the chat
4. You'll be redirected to the chat interface

### Logout

To log out of the chat application:

1. Click the "Logout" link in the chat interface
2. You'll be redirected to the login page

## Chat Features

### Sending Messages

To send a message:

1. Type your message in the input field
2. Click "Send" or press Enter to send your message
3. Your message will appear in the chat window

### Message Formatting

The Vue.js chat interface supports message formatting using Markdown-like syntax:

- **Bold**: Surround text with double asterisks (`**bold**`)
- *Italic*: Surround text with single asterisks (`*italic*`)
- `Code`: Surround text with backticks (`` `code` ``)
- > Blockquote: Start a line with `>` followed by a space (`> quote`)

You can also use the formatting buttons above the message input field to apply formatting to your selected text.

### Loading Older Messages

By default, the chat shows the most recent messages. To load older messages:

1. Scroll to the top of the chat window
2. Click the "Load More Messages" button
3. Older messages will be loaded and displayed

### Real-time Updates

The Vue.js chat interface uses WebSockets for real-time communication. This means:

- New messages from other users appear instantly
- No need to refresh the page
- Efficient use of network resources

## Troubleshooting

### Messages Not Appearing

If your messages are not appearing in the chat:

1. Check your internet connection
2. Make sure you're logged in
3. Try refreshing the page
4. Check the browser console for errors

### WebSocket Connection Issues

If you're experiencing issues with real-time updates in the Vue.js chat:

1. Check if your browser supports WebSockets
2. Make sure the WebSocket server is running
3. Check if your network allows WebSocket connections
4. Try using the JSON or HTML chat interface as a fallback

### Login Issues

If you're having trouble logging in:

1. Make sure you're using the correct username and password
2. Check if cookies are enabled in your browser
3. Try clearing your browser cache and cookies
4. If you've forgotten your password, contact the administrator

## FAQ

### Which chat interface should I use?

- For the best user experience, use the Vue.js chat interface (`/chat/vue`)
- If you're on a slow connection, the JSON chat interface (`/chat/json`) is a good alternative
- If you're using an older browser or have JavaScript disabled, use the HTML chat interface (`/chat/html`)

### Are my messages private?

No, this is a public chat application. All messages are visible to all users.

### How many messages can I send?

There is a rate limit to prevent spam. If you send too many messages in a short period, you may be temporarily blocked.

### Can I delete my messages?

Currently, there is no feature to delete messages once they've been sent.

### Is my password secure?

Yes, passwords are securely hashed using modern cryptographic techniques before being stored in the database.