# AI Chat Application API Client Libraries

This directory contains official client libraries for the AI Chat Application API in multiple programming languages. These libraries provide a convenient way to interact with the API from your applications.

## Available Languages

- **JavaScript/TypeScript** - For web browsers and Node.js
- **Python** - For Python applications and scripts  
- **PHP** - For PHP web applications

## Quick Start

### JavaScript/Node.js

```javascript
const AiChatApiClient = require('./javascript/ai-chat-api-client.js');

// Initialize client
const client = new AiChatApiClient({
    baseUrl: 'https://api.example.com',
    timeout: 30000
});

// Login
const loginResponse = await client.login('user@example.com', 'password123');
if (loginResponse.success) {
    console.log('Logged in:', loginResponse.user);
}

// Create conversation and send message
const conversation = await client.createConversation({
    title: 'My First Chat'
});

const messageResponse = await client.sendMessage(
    conversation.conversation.id,
    'Hello, how can you help me today?'
);
```

### Python

```python
from ai_chat_api_client import AiChatApiClient

# Initialize client
client = AiChatApiClient(
    base_url="https://api.example.com",
    timeout=30
)

# Login
login_response = client.login("user@example.com", "password123")
if login_response.success:
    print(f"Logged in: {login_response.data['user']}")

# Create conversation and send message
conversation_response = client.create_conversation(
    title="My First Chat"
)

if conversation_response.success:
    conversation_id = conversation_response.data['conversation']['id']
    
    message_response = client.send_message(
        conversation_id,
        "Hello, how can you help me today?"
    )
```

### PHP

```php
use AiChat\ApiClient\AiChatApiClient;

// Initialize client
$client = new AiChatApiClient('https://api.example.com');

// Login
$loginResponse = $client->login('user@example.com', 'password123');
if ($loginResponse->success) {
    echo "Logged in: " . json_encode($loginResponse->data['user']) . "\n";
}

// Create conversation and send message
$conversationResponse = $client->createConversation('My First Chat');
if ($conversationResponse->success) {
    $conversationId = $conversationResponse->data['conversation']['id'];
    
    $messageResponse = $client->sendMessage(
        $conversationId,
        'Hello, how can you help me today?'
    );
}
```

## Features

All client libraries provide the following features:

### Authentication
- User login/logout
- User registration
- JWT token management
- Current user information

### Conversation Management
- List conversations with pagination
- Create new conversations
- Get conversation details with messages
- Update conversation metadata
- Delete conversations

### Messaging
- Send messages to conversations
- Get message history with pagination
- Support for file attachments

### File Upload
- Upload files to conversations
- Support for various file types

### Real-time Communication
- WebSocket support for real-time updates
- Event-driven message handling

## Installation

### JavaScript/Node.js

```bash
# No installation required - just include the file
# For Node.js projects, you may need:
npm install node-fetch  # If using Node.js < 18
```

### Python

```bash
# Install dependencies
pip install requests websocket-client
```

### PHP

```bash
# Install via Composer
composer require guzzlehttp/guzzle
```

## Configuration

### Base URL
Set the base URL of your API server:

- **Development**: `http://localhost:8080`
- **Production**: `https://your-api-domain.com`

### Authentication
All clients support JWT token authentication. Tokens are automatically managed after login.

### Timeout
Configure request timeouts based on your needs:

- **Default**: 30 seconds
- **File uploads**: Consider increasing for large files
- **WebSocket**: Persistent connection

## Error Handling

All clients provide consistent error handling:

### JavaScript
```javascript
try {
    const response = await client.login(email, password);
    if (!response.success) {
        console.error('Login failed:', response.message);
    }
} catch (error) {
    console.error('Request failed:', error.message);
}
```

### Python
```python
response = client.login(email, password)
if not response.success:
    print(f"Login failed: {response.message}")
    if response.errors:
        print(f"Errors: {response.errors}")
```

### PHP
```php
$response = $client->login($email, $password);
if (!$response->success) {
    echo "Login failed: " . $response->message . "\n";
    if ($response->errors) {
        echo "Errors: " . json_encode($response->errors) . "\n";
    }
}
```

## WebSocket Usage

### JavaScript
```javascript
const ws = client.connectWebSocket({
    onMessage: (data) => {
        console.log('New message:', data);
    },
    onError: (error) => {
        console.error('WebSocket error:', error);
    },
    onClose: () => {
        console.log('WebSocket closed');
    }
});
```

### Python
```python
from ai_chat_api_client import AiChatWebSocketClient

ws_client = AiChatWebSocketClient(
    base_url="wss://api.example.com",
    token=client.token
)

def on_message(data):
    print(f"New message: {data}")

def on_error(error):
    print(f"WebSocket error: {error}")

ws_client.on_message = on_message
ws_client.on_error = on_error

ws_client.connect()
ws_client.run_forever()
```

## File Upload Example

### JavaScript
```javascript
const fileInput = document.getElementById('file-input');
const file = fileInput.files[0];

const uploadResponse = await client.uploadFile(file, conversationId);
if (uploadResponse.success) {
    console.log('File uploaded:', uploadResponse.file);
}
```

### Python
```python
upload_response = client.upload_file('/path/to/file.pdf', conversation_id)
if upload_response.success:
    print(f"File uploaded: {upload_response.data['file']}")
```

### PHP
```php
$uploadResponse = $client->uploadFile('/path/to/file.pdf', $conversationId);
if ($uploadResponse->success) {
    echo "File uploaded: " . json_encode($uploadResponse->data['file']) . "\n";
}
```

## Best Practices

### Token Management
- Store tokens securely (localStorage for web, secure storage for mobile)
- Handle token expiration gracefully
- Implement automatic token refresh if available

### Error Handling
- Always check response success status
- Log errors appropriately
- Provide user-friendly error messages

### Rate Limiting
- Implement client-side rate limiting if needed
- Handle 429 responses appropriately
- Use exponential backoff for retries

### File Uploads
- Validate file types and sizes before upload
- Show upload progress for large files
- Handle upload failures gracefully

## Examples

See the `/examples` directory for complete working examples in each language:

- `javascript/complete-chat-app.html` - Full chat application
- `python/chat_bot.py` - Command-line chat bot
- `php/chat_integration.php` - Web application integration

## Support

For questions and support:

- Check the [API Documentation](../api-docs.html)
- Review the [OpenAPI Specification](../openapi.yaml)
- Create an issue in the project repository

## License

These client libraries are released under the same license as the main project.