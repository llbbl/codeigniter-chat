"""
AI Chat Application API Client (Python)

A comprehensive client library for interacting with the AI Chat Application API.
Supports authentication, conversation management, and real-time messaging.

Version: 1.0.0
Author: AI Chat Team
"""

import json
import requests
import websocket
from typing import Dict, List, Optional, Union, Any, Callable
from dataclasses import dataclass
from urllib.parse import urljoin, urlencode


@dataclass
class ApiResponse:
    """Standard API response wrapper"""
    success: bool
    data: Any
    message: Optional[str] = None
    errors: Optional[Dict[str, List[str]]] = None


class AiChatApiClient:
    """
    AI Chat Application API Client
    
    A comprehensive client for interacting with the AI Chat Application API.
    Provides methods for authentication, conversation management, messaging, and file uploads.
    """
    
    def __init__(self, base_url: str = "http://localhost:8080", 
                 token: Optional[str] = None, timeout: int = 30):
        """
        Initialize the API client
        
        Args:
            base_url: Base URL of the API
            token: JWT authentication token
            timeout: Request timeout in seconds
        """
        self.base_url = base_url.rstrip('/')
        self.token = token
        self.timeout = timeout
        self.session = requests.Session()
        
        # Set default headers
        self.session.headers.update({
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        })
    
    def set_token(self, token: str) -> None:
        """Set the authentication token"""
        self.token = token
        if token:
            self.session.headers['Authorization'] = f'Bearer {token}'
        elif 'Authorization' in self.session.headers:
            del self.session.headers['Authorization']
    
    def _make_request(self, method: str, endpoint: str, 
                     data: Optional[Dict] = None, 
                     params: Optional[Dict] = None,
                     files: Optional[Dict] = None) -> ApiResponse:
        """
        Make HTTP request to the API
        
        Args:
            method: HTTP method
            endpoint: API endpoint
            data: Request data
            params: Query parameters
            files: Files to upload
            
        Returns:
            ApiResponse object
            
        Raises:
            requests.RequestException: For HTTP errors
        """
        url = urljoin(self.base_url, endpoint)
        
        request_kwargs = {
            'timeout': self.timeout,
            'params': params
        }
        
        if files:
            # Remove Content-Type for file uploads
            headers = dict(self.session.headers)
            if 'Content-Type' in headers:
                del headers['Content-Type']
            request_kwargs['headers'] = headers
            request_kwargs['files'] = files
            if data:
                request_kwargs['data'] = data
        elif data:
            request_kwargs['json'] = data
        
        try:
            response = self.session.request(method, url, **request_kwargs)
            response.raise_for_status()
            
            response_data = response.json()
            return ApiResponse(
                success=response_data.get('success', True),
                data=response_data,
                message=response_data.get('message'),
                errors=response_data.get('errors')
            )
            
        except requests.exceptions.HTTPError as e:
            try:
                error_data = response.json()
                return ApiResponse(
                    success=False,
                    data=error_data,
                    message=error_data.get('message', str(e)),
                    errors=error_data.get('errors')
                )
            except json.JSONDecodeError:
                return ApiResponse(
                    success=False,
                    data=None,
                    message=str(e)
                )
        except requests.exceptions.RequestException as e:
            return ApiResponse(
                success=False,
                data=None,
                message=str(e)
            )
    
    # Authentication Methods
    
    def login(self, email: str, password: str) -> ApiResponse:
        """
        Login user
        
        Args:
            email: User email
            password: User password
            
        Returns:
            ApiResponse with token and user info
        """
        response = self._make_request('POST', '/auth/login', {
            'email': email,
            'password': password
        })
        
        if response.success and response.data.get('token'):
            self.set_token(response.data['token'])
        
        return response
    
    def register(self, name: str, email: str, password: str) -> ApiResponse:
        """
        Register new user
        
        Args:
            name: User name
            email: User email
            password: User password
            
        Returns:
            ApiResponse with registration result
        """
        return self._make_request('POST', '/auth/register', {
            'name': name,
            'email': email,
            'password': password
        })
    
    def logout(self) -> ApiResponse:
        """
        Logout user
        
        Returns:
            ApiResponse with logout result
        """
        response = self._make_request('POST', '/auth/logout')
        if response.success:
            self.set_token(None)
        return response
    
    def get_current_user(self) -> ApiResponse:
        """
        Get current user information
        
        Returns:
            ApiResponse with user information
        """
        return self._make_request('GET', '/auth/me')
    
    # Conversation Methods
    
    def get_conversations(self, page: int = 1, limit: int = 20) -> ApiResponse:
        """
        Get all conversations
        
        Args:
            page: Page number
            limit: Items per page
            
        Returns:
            ApiResponse with conversations list and pagination
        """
        params = {'page': page, 'limit': limit}
        return self._make_request('GET', '/chat/conversations', params=params)
    
    def create_conversation(self, title: Optional[str] = None, 
                          description: Optional[str] = None) -> ApiResponse:
        """
        Create new conversation
        
        Args:
            title: Conversation title
            description: Conversation description
            
        Returns:
            ApiResponse with created conversation
        """
        data = {}
        if title:
            data['title'] = title
        if description:
            data['description'] = description
        
        return self._make_request('POST', '/chat/conversations', data)
    
    def get_conversation(self, conversation_id: int) -> ApiResponse:
        """
        Get specific conversation with messages
        
        Args:
            conversation_id: Conversation ID
            
        Returns:
            ApiResponse with conversation and messages
        """
        return self._make_request('GET', f'/chat/conversations/{conversation_id}')
    
    def update_conversation(self, conversation_id: int, 
                          title: Optional[str] = None,
                          description: Optional[str] = None) -> ApiResponse:
        """
        Update conversation
        
        Args:
            conversation_id: Conversation ID
            title: New title
            description: New description
            
        Returns:
            ApiResponse with updated conversation
        """
        data = {}
        if title:
            data['title'] = title
        if description:
            data['description'] = description
        
        return self._make_request('PUT', f'/chat/conversations/{conversation_id}', data)
    
    def delete_conversation(self, conversation_id: int) -> ApiResponse:
        """
        Delete conversation
        
        Args:
            conversation_id: Conversation ID
            
        Returns:
            ApiResponse with deletion result
        """
        return self._make_request('DELETE', f'/chat/conversations/{conversation_id}')
    
    # Message Methods
    
    def get_messages(self, conversation_id: int, page: int = 1, 
                    limit: int = 50) -> ApiResponse:
        """
        Get messages for a conversation
        
        Args:
            conversation_id: Conversation ID
            page: Page number
            limit: Items per page
            
        Returns:
            ApiResponse with messages list and pagination
        """
        params = {'page': page, 'limit': limit}
        return self._make_request('GET', f'/chat/conversations/{conversation_id}/messages', 
                                params=params)
    
    def send_message(self, conversation_id: int, content: str, 
                    attachments: Optional[List[str]] = None) -> ApiResponse:
        """
        Send message in conversation
        
        Args:
            conversation_id: Conversation ID
            content: Message content
            attachments: File attachments
            
        Returns:
            ApiResponse with sent message and AI response
        """
        data = {'content': content}
        if attachments:
            data['attachments'] = attachments
        
        return self._make_request('POST', f'/chat/conversations/{conversation_id}/messages', data)
    
    # File Upload Methods
    
    def upload_file(self, file_path: str, conversation_id: Optional[int] = None) -> ApiResponse:
        """
        Upload file
        
        Args:
            file_path: Path to file to upload
            conversation_id: Associated conversation ID
            
        Returns:
            ApiResponse with upload result
        """
        data = {}
        if conversation_id:
            data['conversation_id'] = conversation_id
        
        with open(file_path, 'rb') as f:
            files = {'file': f}
            return self._make_request('POST', '/chat/upload', data=data, files=files)


class AiChatWebSocketClient:
    """
    WebSocket client for real-time chat functionality
    """
    
    def __init__(self, base_url: str = "http://localhost:8080", token: Optional[str] = None):
        """
        Initialize WebSocket client
        
        Args:
            base_url: Base URL of the API
            token: JWT authentication token
        """
        self.base_url = base_url.replace('http', 'ws', 1)
        self.token = token
        self.ws = None
        
        # Event handlers
        self.on_message: Optional[Callable] = None
        self.on_error: Optional[Callable] = None
        self.on_close: Optional[Callable] = None
        self.on_open: Optional[Callable] = None
    
    def connect(self) -> None:
        """Connect to WebSocket"""
        ws_url = f"{self.base_url}/chat/stream"
        
        headers = {}
        if self.token:
            headers['Authorization'] = f'Bearer {self.token}'
        
        self.ws = websocket.WebSocketApp(
            ws_url,
            header=headers,
            on_message=self._on_message,
            on_error=self._on_error,
            on_close=self._on_close,
            on_open=self._on_open
        )
    
    def run_forever(self) -> None:
        """Run WebSocket connection forever"""
        if self.ws:
            self.ws.run_forever()
    
    def close(self) -> None:
        """Close WebSocket connection"""
        if self.ws:
            self.ws.close()
    
    def send_message(self, data: Dict) -> None:
        """Send message through WebSocket"""
        if self.ws:
            self.ws.send(json.dumps(data))
    
    def _on_message(self, ws, message):
        """Internal message handler"""
        try:
            data = json.loads(message)
            if self.on_message:
                self.on_message(data)
        except json.JSONDecodeError as e:
            if self.on_error:
                self.on_error(f"JSON decode error: {e}")
    
    def _on_error(self, ws, error):
        """Internal error handler"""
        if self.on_error:
            self.on_error(error)
    
    def _on_close(self, ws, close_status_code, close_msg):
        """Internal close handler"""
        if self.on_close:
            self.on_close(close_status_code, close_msg)
    
    def _on_open(self, ws):
        """Internal open handler"""
        if self.on_open:
            self.on_open()


# Example usage
if __name__ == "__main__":
    # Initialize client
    client = AiChatApiClient(
        base_url="https://api.example.com",
        timeout=30
    )
    
    # Login
    try:
        login_response = client.login("user@example.com", "password123")
        if login_response.success:
            print(f"Logged in: {login_response.data['user']}")
        else:
            print(f"Login failed: {login_response.message}")
    except Exception as e:
        print(f"Login error: {e}")
    
    # Create conversation
    conversation_response = client.create_conversation(
        title="My First Chat",
        description="Learning about AI"
    )
    
    if conversation_response.success:
        conversation_id = conversation_response.data['conversation']['id']
        
        # Send message
        message_response = client.send_message(
            conversation_id, 
            "Hello, how can you help me today?"
        )
        
        if message_response.success:
            print(f"Message sent: {message_response.data}")
    
    # WebSocket example
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
    # ws_client.run_forever()  # Uncomment to run WebSocket