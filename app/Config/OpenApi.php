<?php

namespace App\Config;

use OpenApi\Attributes as OA;

/**
 * OpenAPI Configuration and Global Schemas
 */
#[OA\Info(
    version: "1.0.0",
    title: "AI Chat Application API",
    description: "A comprehensive API for an AI chat application that supports real-time messaging, conversation management, and user authentication."
)]
#[OA\Server(
    url: "http://localhost:8080",
    description: "Development server"
)]
#[OA\Server(
    url: "https://api.example.com",
    description: "Production server"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
class OpenApi
{
    // Schema definitions
}

/**
 * User Schema
 */
#[OA\Schema(
    schema: "User",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "John Doe"),
        new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2024-01-15T10:30:00Z"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2024-01-15T10:30:00Z")
    ]
)]
class UserSchema {}

/**
 * Conversation Schema
 */
#[OA\Schema(
    schema: "Conversation",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "title", type: "string", example: "AI Discussion"),
        new OA\Property(property: "description", type: "string", example: "General AI conversation"),
        new OA\Property(property: "user_id", type: "integer", example: 1),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2024-01-15T10:30:00Z"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2024-01-15T10:30:00Z"),
        new OA\Property(property: "message_count", type: "integer", example: 5)
    ]
)]
class ConversationSchema {}

/**
 * Message Schema
 */
#[OA\Schema(
    schema: "Message",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "conversation_id", type: "integer", example: 1),
        new OA\Property(property: "content", type: "string", example: "Hello, how can you help me?"),
        new OA\Property(property: "role", type: "string", enum: ["user", "assistant", "system"], example: "user"),
        new OA\Property(property: "attachments", type: "array", items: new OA\Items(type: "string"), example: ["file1.pdf"]),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2024-01-15T10:30:00Z"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2024-01-15T10:30:00Z")
    ]
)]
class MessageSchema {}

/**
 * Error Schema
 */
#[OA\Schema(
    schema: "Error",
    properties: [
        new OA\Property(property: "success", type: "boolean", example: false),
        new OA\Property(property: "message", type: "string", example: "Error message"),
        new OA\Property(
            property: "errors", 
            type: "object",
            additionalProperties: new OA\AdditionalProperties(
                type: "array",
                items: new OA\Items(type: "string")
            )
        )
    ]
)]
class ErrorSchema {}