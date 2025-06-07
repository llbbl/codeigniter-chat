# CodeIgniter Chat Architecture Overview

This document provides a comprehensive overview of the CodeIgniter Chat application's architecture, including component interactions, data flow, and design patterns.

## System Architecture

CodeIgniter Chat follows the Model-View-Controller (MVC) architectural pattern, which is the foundation of the CodeIgniter 4 framework. This separation of concerns helps maintain a clean and organized codebase.

### High-Level Architecture

```
+----------------------------------+
|           Client Side            |
|  (Browser with HTML/CSS/JS/Vue)  |
+----------------------------------+
              ↑   ↓
              HTTP
              ↑   ↓
+----------------------------------+
|           Web Server             |
|      (Apache/Nginx + PHP)        |
+----------------------------------+
              ↑   ↓
+----------------------------------+
|        CodeIgniter Core          |
| (Routing, Filters, Services)     |
+----------------------------------+
    ↑     ↑     ↑     ↑     ↑
    |     |     |     |     |
+-----+ +-----+ +-----+ +-----+ +-----+
|Model| |View | |Cntrl| |Hlpr | |Libs |
+-----+ +-----+ +-----+ +-----+ +-----+
    ↑
    |
+----------------------------------+
|          Database Layer          |
|            (MySQL)               |
+----------------------------------+
```

## Component Breakdown

### 1. Client-Side Components

The application offers multiple client-side implementations:

#### XML Implementation
- Uses jQuery for DOM manipulation
- Communicates with the server using AJAX with XML format
- Located in `src/js/chat.js`

#### JSON Implementation
- Uses jQuery for DOM manipulation
- Communicates with the server using AJAX with JSON format
- Located in `src/js/chat-json.js`

#### HTML Implementation
- Uses traditional form submission
- Full page reloads for updates
- Located in `src/js/chat-html.js`

#### Vue.js Implementation
- Uses Vue.js 3 for reactive UI
- Component-based architecture
- Communicates with the server using AJAX with JSON format
- Located in `src/vue/App.vue` and related components

### 2. Server-Side Components

#### Controllers

Controllers handle incoming HTTP requests and return responses. The main controllers are:

- **ChatController** (`app/Controllers/Chat.php`)
  - Handles chat-related requests
  - Provides endpoints for different chat implementations (XML, JSON, HTML, Vue)
  - Manages message retrieval and submission

- **AuthController** (`app/Controllers/Auth.php`)
  - Handles user authentication
  - Manages login, logout, and registration

#### Models

Models handle data access and business logic:

- **ChatModel** (`app/Models/ChatModel.php`)
  - Manages chat message data
  - Provides methods for retrieving and storing messages

- **UserModel** (`app/Models/UserModel.php`)
  - Manages user data
  - Provides methods for user authentication and management

#### Views

Views render the HTML output:

- **Chat Views** (`app/Views/chat/`)
  - XML view (`xmlView.php`)
  - JSON view (`jsonView.php`)
  - HTML view (`htmlView.php`)
  - Vue view (`vueView.php`)

- **Auth Views** (`app/Views/auth/`)
  - Login view (`login.php`)
  - Registration view (`register.php`)

#### Helpers

Helpers provide utility functions:

- **TextHelper** (`app/Helpers/TextHelper.php`)
  - Provides text manipulation functions
  - Used for formatting chat messages

- **ViteHelper** (`app/Helpers/ViteHelper.php`)
  - Integrates Vite with CodeIgniter
  - Provides functions for loading assets built with Vite

#### Libraries

Custom libraries extend functionality:

- **ChatLibrary** (`app/Libraries/ChatLibrary.php`)
  - Provides advanced chat functionality
  - Handles message formatting and filtering

#### Filters

Filters process HTTP requests and responses:

- **AuthFilter** (`app/Filters/AuthFilter.php`)
  - Ensures users are authenticated before accessing protected routes
  - Redirects unauthenticated users to the login page

- **ThrottleFilter** (`app/Filters/ThrottleFilter.php`)
  - Implements rate limiting to prevent spam and DoS attacks
  - Limits the number of requests a user can make in a given time period

## Data Flow

### Message Posting Flow

```
+-------------+     +-------------+     +-------------+     +-------------+
| Client-side |     |  Controller |     |    Model    |     |  Database   |
|    Form     | --> |   (Chat)    | --> | (ChatModel) | --> |  (MySQL)    |
+-------------+     +-------------+     +-------------+     +-------------+
      |                    |                  |                   |
      |                    |                  |                   |
      |                    |                  |                   |
+-------------+     +-------------+     +-------------+     +-------------+
| Client-side | <-- |    View     | <-- |  Controller  | <-- |    Model    |
|   Display   |     |             |     |   (Chat)     |     | (ChatModel) |
+-------------+     +-------------+     +-------------+     +-------------+
```

1. User submits a message via the client-side form
2. The Chat controller receives the request
3. The controller validates the input and passes it to the ChatModel
4. The ChatModel stores the message in the database
5. The controller retrieves the updated message list from the model
6. The view renders the updated message list
7. The client-side displays the updated messages

### Authentication Flow

```
+-------------+     +-------------+     +-------------+     +-------------+
| Login Form  |     |  Controller |     |    Model    |     |  Database   |
|             | --> |   (Auth)    | --> | (UserModel) | --> |  (MySQL)    |
+-------------+     +-------------+     +-------------+     +-------------+
      |                    |                  |                   |
      |                    |                  |                   |
      |                    |                  |                   |
+-------------+     +-------------+     +-------------+
| Redirect to | <-- |  Controller  | <-- |    Model    |
|  Chat Page  |     |   (Auth)     |     | (UserModel) |
+-------------+     +-------------+     +-------------+
```

1. User submits login credentials via the login form
2. The Auth controller receives the request
3. The controller passes the credentials to the UserModel for verification
4. The UserModel checks the credentials against the database
5. If valid, the controller creates a session and redirects to the chat page
6. If invalid, the controller returns to the login page with an error message

## Design Patterns

The CodeIgniter Chat application implements several design patterns:

### 1. Model-View-Controller (MVC)

The application follows the MVC pattern, separating concerns into:
- **Models**: Handle data access and business logic
- **Views**: Handle presentation and user interface
- **Controllers**: Handle request processing and coordination

### 2. Repository Pattern

The models act as repositories, abstracting the data access layer:
- **ChatModel**: Repository for chat messages
- **UserModel**: Repository for user data

### 3. Factory Pattern

The application uses factories to create objects:
- **ViewFactory**: Creates view instances based on the requested format (XML, JSON, HTML, Vue)

### 4. Dependency Injection

The application uses dependency injection to provide components with their dependencies:
- Controllers receive models through constructor injection
- Services receive repositories through constructor injection

### 5. Observer Pattern

The Vue.js implementation uses the observer pattern for reactive updates:
- Components observe data changes and update automatically

## Database Schema

For a detailed description of the database schema, see [database-schema.md](database-schema.md).

## Component Diagrams

### Chat Controller Interaction Diagram

```
                                +-------------------+
                                |  ChatController   |
                                +-------------------+
                                | - index()         |
                                | - json()          |
                                | - html()          |
                                | - vue()           |
                                | - getMessages()   |
                                | - sendMessage()   |
                                +-------------------+
                                         |
                 +------------------------+------------------------+
                 |                        |                        |
        +-------------------+    +-------------------+    +-------------------+
        |     ChatModel     |    |    UserModel      |    |    ViewRenderer   |
        +-------------------+    +-------------------+    +-------------------+
        | - getMessages()   |    | - getUser()       |    | - render()        |
        | - saveMessage()   |    | - authenticate()  |    | - renderJson()    |
        +-------------------+    +-------------------+    +-------------------+
                 |                        |                        |
        +-------------------+    +-------------------+    +-------------------+
        |     Database      |    |     Database      |    |       Views       |
        |    (messages)     |    |      (users)      |    |                   |
        +-------------------+    +-------------------+    +-------------------+
```

### Authentication Flow Diagram

```
+-------------+     +-------------+     +-------------+
|   Browser   |     |    Auth     |     |  UserModel  |
|             |     | Controller  |     |             |
+-------------+     +-------------+     +-------------+
       |                  |                   |
       | Login Request    |                   |
       |----------------->|                   |
       |                  | Validate          |
       |                  |------------------>|
       |                  |                   |
       |                  |     Result        |
       |                  |<------------------|
       |                  |                   |
       |                  | Create Session    |
       |                  |------------------>|
       |                  |                   |
       |                  |     Success       |
       |                  |<------------------|
       |                  |                   |
       | Redirect         |                   |
       |<-----------------|                   |
       |                  |                   |
```

## Frontend Architecture

### Vue.js Component Hierarchy

```
+-------------------+
|      App.vue      |
+-------------------+
         |
         v
+-------------------+
|   ChatContainer   |
+-------------------+
         |
    +----+----+
    |         |
    v         v
+-------+ +----------+
|Message| |MessageForm|
+-------+ +----------+
```

### Asset Build Pipeline

```
+-------------+     +-------------+     +-------------+
|  Source JS  |     |    Vite     |     | Bundled JS  |
|  & SCSS     | --> | (Build Tool)| --> | & CSS       |
+-------------+     +-------------+     +-------------+
                          |
                          v
                    +-------------+
                    |   Manifest  |
                    |    File     |
                    +-------------+
                          |
                          v
                    +-------------+
                    | ViteHelper  |
                    | (PHP)       |
                    +-------------+
                          |
                          v
                    +-------------+
                    |  HTML Tags  |
                    | in Views    |
                    +-------------+
```

## Security Architecture

The application implements several security measures:

1. **Input Validation**
   - All user inputs are validated and sanitized
   - Form validation rules are defined in controllers

2. **Authentication**
   - User authentication is required for chat access
   - Passwords are hashed using secure algorithms

3. **CSRF Protection**
   - CSRF tokens are required for all forms
   - Implemented using CodeIgniter's built-in CSRF protection

4. **Rate Limiting**
   - Throttling is applied to prevent abuse
   - Implemented using the ThrottleFilter

5. **Output Escaping**
   - All output is escaped to prevent XSS attacks
   - Implemented using CodeIgniter's built-in escaping functions

6. **Content Security Policy**
   - CSP headers restrict resource loading
   - Defined in the application's security configuration

## Performance Optimizations

The application implements several performance optimizations:

1. **Caching**
   - Frequently accessed data is cached
   - Implemented using CodeIgniter's caching library

2. **Database Optimization**
   - Queries are optimized for performance
   - Proper indexes are defined on database tables

3. **Asset Optimization**
   - Frontend assets are minified and bundled
   - Implemented using Vite

4. **Lazy Loading**
   - Older messages are loaded only when needed
   - Implemented in the frontend JavaScript

## Deployment Architecture

The application can be deployed in various environments:

### Development Environment

```
+-------------+     +-------------+     +-------------+
| Local Web   |     | PHP Built-in|     |   Local     |
| Browser     | --> |   Server    | --> |  Database   |
+-------------+     +-------------+     +-------------+
```

### Production Environment

```
+-------------+     +-------------+     +-------------+     +-------------+
| Web Browser | --> | Load        | --> | Web Servers | --> |  Database   |
|             |     | Balancer    |     | (Multiple)  |     |  Cluster    |
+-------------+     +-------------+     +-------------+     +-------------+
```

## Future Architecture Considerations

The architecture is designed to be extensible for future enhancements:

1. **WebSockets Integration**
   - Real-time communication instead of polling
   - Would require a WebSocket server component

2. **Microservices Architecture**
   - Breaking down the monolithic application into microservices
   - Would improve scalability and maintainability

3. **Mobile Application Support**
   - API endpoints for mobile applications
   - Would require authentication token support

4. **Cloud Deployment**
   - Containerization for cloud deployment
   - Would improve scalability and reliability

## Conclusion

The CodeIgniter Chat application follows a well-structured MVC architecture that leverages the strengths of the CodeIgniter 4 framework. The separation of concerns, use of design patterns, and implementation of security measures create a robust and maintainable application.

The multiple client-side implementations (XML, JSON, HTML, Vue) demonstrate different approaches to building interactive web applications, while the server-side components provide a solid foundation for handling data and business logic.