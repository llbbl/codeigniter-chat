# CodeIgniter Chat API Documentation

This document provides comprehensive documentation for the CodeIgniter Chat API. The API allows clients to retrieve and post chat messages in various formats.

## Base URL

All API endpoints are relative to the base URL of your application, which is configured in `.env` or `app/Config/App.php`.

Example: `http://your-domain.com/`

## Authentication

Most endpoints require authentication. To authenticate, you must first log in using the `/auth/processLogin` endpoint, which will set a session cookie. This cookie will be used for subsequent requests.

## API Endpoints

### Chat Messages

#### Get Messages (XML)

Retrieves chat messages in XML format.

- **URL**: `/chat/backend`
- **Method**: `GET`
- **Authentication**: Optional
- **Parameters**:
  - `page` (optional): Page number for pagination (default: 1)
  - `per_page` (optional): Number of messages per page (default: 10)
- **Response Format**: XML
- **Example Response**:
  ```xml
  <messages>
    <message>
      <id>1</id>
      <author>User1</author>
      <text>Hello, world!</text>
      <time>1623456789</time>
    </message>
    <message>
      <id>2</id>
      <author>User2</author>
      <text>Hi there!</text>
      <time>1623456790</time>
    </message>
    <pagination>
      <page>1</page>
      <perPage>10</perPage>
      <totalItems>2</totalItems>
      <totalPages>1</totalPages>
      <hasNext>false</hasNext>
      <hasPrev>false</hasPrev>
    </pagination>
  </messages>
  ```
- **Error Responses**:
  - `400 Bad Request`: Invalid parameters
  - `500 Internal Server Error`: Server error

#### Get Messages (JSON)

Retrieves chat messages in JSON format.

- **URL**: `/chat/jsonBackend`
- **Method**: `GET`
- **Authentication**: Optional
- **Parameters**:
  - `page` (optional): Page number for pagination (default: 1)
  - `per_page` (optional): Number of messages per page (default: 10)
- **Response Format**: JSON
- **Example Response**:
  ```json
  {
    "messages": [
      {
        "id": 1,
        "user": "User1",
        "msg": "Hello, world!",
        "time": 1623456789
      },
      {
        "id": 2,
        "user": "User2",
        "msg": "Hi there!",
        "time": 1623456790
      }
    ],
    "pagination": {
      "page": 1,
      "perPage": 10,
      "totalItems": 2,
      "totalPages": 1,
      "hasNext": false,
      "hasPrev": false
    }
  }
  ```
- **Error Responses**:
  - `400 Bad Request`: Invalid parameters
  - `500 Internal Server Error`: Server error

#### Get Messages (HTML)

Retrieves chat messages in HTML format.

- **URL**: `/chat/htmlBackend`
- **Method**: `GET`
- **Authentication**: Optional
- **Parameters**:
  - `page` (optional): Page number for pagination (default: 1)
  - `per_page` (optional): Number of messages per page (default: 10)
- **Response Format**: HTML
- **Notes**: This endpoint is primarily used internally by the HTML chat interface.

#### Post Message

Posts a new chat message.

- **URL**: `/chat/update`
- **Method**: `POST`
- **Authentication**: Required
- **Parameters**:
  - `message` (required): The message text
  - `action` (required): Must be "postmsg"
  - `html_redirect` (optional): If "true", redirects to the HTML chat interface after posting
- **Response Format**: JSON (for AJAX requests) or redirect (for form submissions)
- **Example Response** (JSON):
  ```json
  {
    "success": true
  }
  ```
- **Error Responses**:
  - `400 Bad Request`: Invalid parameters
  - `401 Unauthorized`: Not authenticated
  - `500 Internal Server Error`: Server error

### Vue.js API

#### Get Messages (Vue.js)

Retrieves chat messages for the Vue.js interface.

- **URL**: `/chat/vueApi`
- **Method**: `GET`
- **Authentication**: Required
- **Parameters**:
  - `page` (optional): Page number for pagination (default: 1)
  - `per_page` (optional): Number of messages per page (default: 10)
- **Response Format**: JSON
- **Example Response**: Same as `/chat/jsonBackend`
- **Error Responses**:
  - `400 Bad Request`: Invalid parameters
  - `401 Unauthorized`: Not authenticated
  - `500 Internal Server Error`: Server error

### Authentication

#### Register

Registers a new user.

- **URL**: `/auth/processRegistration`
- **Method**: `POST`
- **Authentication**: None
- **Parameters**:
  - `username` (required): The username
  - `email` (required): The email address
  - `password` (required): The password
  - `password_confirm` (required): The password confirmation
- **Response Format**: Redirect
- **Success Response**: Redirects to `/auth/login` with a success message
- **Error Responses**:
  - `400 Bad Request`: Invalid parameters (redirects back to the form with error messages)
  - `500 Internal Server Error`: Server error

#### Login

Logs in a user.

- **URL**: `/auth/processLogin`
- **Method**: `POST`
- **Authentication**: None
- **Parameters**:
  - `username` (required): The username
  - `password` (required): The password
- **Response Format**: Redirect
- **Success Response**: Redirects to `/chat` with a session cookie
- **Error Responses**:
  - `400 Bad Request`: Invalid parameters (redirects back to the form with error messages)
  - `401 Unauthorized`: Invalid credentials (redirects back to the form with error messages)
  - `500 Internal Server Error`: Server error

#### Logout

Logs out a user.

- **URL**: `/auth/logout`
- **Method**: `GET`
- **Authentication**: Required
- **Parameters**: None
- **Response Format**: Redirect
- **Success Response**: Redirects to `/auth/login` with a success message
- **Error Responses**:
  - `500 Internal Server Error`: Server error

## WebSocket API

The application also provides a WebSocket API for real-time communication. The WebSocket server is started using the `php spark chat:websocket` command.

### WebSocket Endpoints

#### Connect

Establishes a WebSocket connection.

- **URL**: `ws://your-domain.com:8080`
- **Authentication**: None
- **Notes**: After connecting, you can send and receive messages using the WebSocket protocol.

#### Get Messages

Retrieves chat messages via WebSocket.

- **Message Format**:
  ```json
  {
    "action": "getMessages",
    "page": 1,
    "perPage": 10
  }
  ```
- **Response Format**:
  ```json
  {
    "action": "messages",
    "data": {
      "messages": [
        {
          "id": 1,
          "user": "User1",
          "msg": "Hello, world!",
          "time": 1623456789
        },
        {
          "id": 2,
          "user": "User2",
          "msg": "Hi there!",
          "time": 1623456790
        }
      ],
      "pagination": {
        "page": 1,
        "perPage": 10,
        "totalItems": 2,
        "totalPages": 1,
        "hasNext": false,
        "hasPrev": false
      }
    }
  }
  ```

#### Send Message

Sends a chat message via WebSocket.

- **Message Format**:
  ```json
  {
    "action": "sendMessage",
    "username": "User1",
    "message": "Hello, world!"
  }
  ```
- **Response Format**:
  ```json
  {
    "action": "newMessage",
    "data": {
      "user": "User1",
      "msg": "Hello, world!",
      "timestamp": 1623456789
    }
  }
  ```

## Error Handling

The API uses standard HTTP status codes to indicate the success or failure of a request. In addition, error responses include a JSON object with more details about the error.

### Common Error Codes

- `400 Bad Request`: The request was invalid or cannot be served. The exact error is explained in the response body.
- `401 Unauthorized`: Authentication is required and has failed or has not been provided.
- `403 Forbidden`: The request is understood, but it has been refused or access is not allowed.
- `404 Not Found`: The requested resource could not be found.
- `500 Internal Server Error`: An error occurred on the server.

### Error Response Format

```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field1": "Error message for field1",
    "field2": "Error message for field2"
  }
}
```

## Rate Limiting

The API implements rate limiting to prevent abuse. If you exceed the rate limit, you will receive a `429 Too Many Requests` response.

## CORS

The API supports Cross-Origin Resource Sharing (CORS) for AJAX requests from any origin. The following headers are included in all responses:

- `Access-Control-Allow-Origin: *`
- `Access-Control-Allow-Methods: GET, POST, OPTIONS`
- `Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-TOKEN`

## Content Security Policy

The API implements a Content Security Policy (CSP) to prevent cross-site scripting (XSS) and other code injection attacks. The CSP is configured in `app/Config/ContentSecurityPolicy.php`.