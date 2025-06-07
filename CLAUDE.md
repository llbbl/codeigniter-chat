# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Development
- `npm run dev` - Start Vite development server (watch mode for assets)
- `npm run build` - Build production assets with Vite
- `php spark serve` - Start CodeIgniter development server

### Testing
- `composer test` - Run all PHPUnit tests
- `composer test:unit` - Run unit tests only
- `composer test:feature` - Run feature tests only
- `composer test:database` - Run database tests only
- `composer test:coverage` - Generate HTML coverage report in `build/logs/html/`

### WebSocket Server
- `php spark websocket:start` - Start the WebSocket server for real-time chat

## Architecture Overview

This is a CodeIgniter 4 chat application demonstrating multiple frontend implementations (XML, JSON, HTML, Vue.js) with a shared backend.

### Key Components

**Chat Controller** (`app/Controllers/Chat.php`)
- Main entry point for all chat interfaces
- Methods: `index()` (XML), `json()`, `html()`, `vue()` for different implementations
- Backend methods: `backend()` (XML), `jsonBackend()`, `htmlBackend()`, `vueApi()`
- `update()` method handles message posting and WebSocket broadcasting

**Models**
- `ChatModel` - Handles message CRUD operations with pagination via `getMsgPaginated()`
- `UserModel` - Manages user authentication and data

**WebSocket Integration**
- `ChatWebSocketServer` library implements real-time messaging using Ratchet
- `WebSocketClient` library sends messages to connected WebSocket clients
- Messages are broadcast to all connected clients when posted via HTTP

### Frontend Implementations

1. **XML** (`src/js/chat.js`) - jQuery + XML AJAX requests
2. **JSON** (`src/js/chat-json.js`) - jQuery + JSON AJAX requests  
3. **HTML** (`src/js/chat-html.js`) - Traditional form submission, no JavaScript required
4. **Vue.js** (`src/vue/`) - Reactive UI with component architecture

### Asset Pipeline

Uses Vite for building frontend assets:
- Source files in `src/` directory
- Built assets output to `public/dist/`
- `ViteHelper` integrates Vite manifest with CodeIgniter views
- SCSS compilation and JS bundling

### Database

MySQL database with migrations in `app/Database/Migrations/`:
- `users` table for authentication
- `messages` table for chat messages with user foreign key and timestamps

### Security & Validation

- `AuthFilter` protects routes requiring authentication
- `RateLimitFilter` prevents spam and DoS attacks
- `ChatHelper::validateMessage()` for input validation
- CSRF protection enabled for forms
- Input sanitization and output escaping

### Helpers

- `ChatHelper` - Message validation and formatting (XML/JSON output)
- `ViteHelper` - Asset loading integration
- Various utility helpers for common operations

When making changes, follow the existing MVC patterns and use the appropriate helper methods for validation and formatting.