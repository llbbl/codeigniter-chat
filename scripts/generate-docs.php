#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenApi\Generator;
use Symfony\Component\Finder\Finder;

/**
 * Generate OpenAPI documentation from PHP annotations
 */
class DocumentationGenerator
{
    private string $basePath;
    private string $outputPath;

    public function __construct()
    {
        $this->basePath = dirname(__DIR__);
        $this->outputPath = $this->basePath . '/docs';
    }

    /**
     * Generate OpenAPI documentation
     */
    public function generate(): void
    {
        echo "Generating OpenAPI documentation...\n";

        // Define paths to scan for annotations
        $scanPaths = [
            $this->basePath . '/app/Controllers',
            $this->basePath . '/app/Models',
        ];

        // Generate OpenAPI documentation
        $openapi = Generator::scan($scanPaths);

        // Write JSON specification
        $jsonPath = $this->outputPath . '/openapi.json';
        file_put_contents($jsonPath, $openapi->toJson());
        echo "Generated JSON specification: $jsonPath\n";

        // Write YAML specification
        $yamlPath = $this->outputPath . '/openapi-generated.yaml';
        file_put_contents($yamlPath, $openapi->toYaml());
        echo "Generated YAML specification: $yamlPath\n";

        // Generate interactive HTML documentation
        $this->generateHtmlDocs();

        echo "Documentation generation complete!\n";
    }

    /**
     * Generate interactive HTML documentation
     */
    private function generateHtmlDocs(): void
    {
        $htmlContent = $this->generateSwaggerUI();
        $htmlPath = $this->outputPath . '/api-docs.html';
        file_put_contents($htmlPath, $htmlContent);
        echo "Generated interactive HTML documentation: $htmlPath\n";
    }

    /**
     * Generate Swagger UI HTML
     */
    private function generateSwaggerUI(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat Application API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui.css" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin:0;
            background: #fafafa;
        }
        .swagger-ui .topbar {
            background-color: #2c3e50;
        }
        .custom-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        .custom-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 300;
        }
        .custom-header p {
            margin: 0.5rem 0 0 0;
            font-size: 1.2rem;
            opacity: 0.9;
        }
        .api-info {
            max-width: 1200px;
            margin: 0 auto 2rem auto;
            padding: 0 2rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .info-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-card h3 {
            color: #2c3e50;
            margin-top: 0;
        }
        .feature-list {
            list-style: none;
            padding: 0;
        }
        .feature-list li {
            padding: 0.25rem 0;
            position: relative;
            padding-left: 1.5rem;
        }
        .feature-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #27ae60;
            font-weight: bold;
        }
        .auth-example {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="custom-header">
        <h1>🤖 AI Chat Application API</h1>
        <p>Interactive API Documentation</p>
    </div>
    
    <div class="api-info">
        <div class="info-grid">
            <div class="info-card">
                <h3>📋 API Features</h3>
                <ul class="feature-list">
                    <li>User Authentication & Authorization</li>
                    <li>Real-time Chat Messaging</li>
                    <li>Conversation Management</li>
                    <li>File Upload Support</li>
                    <li>WebSocket Integration</li>
                    <li>Pagination & Filtering</li>
                </ul>
            </div>
            
            <div class="info-card">
                <h3>🔐 Authentication</h3>
                <p>This API uses JWT Bearer token authentication. Include the token in your requests:</p>
                <div class="auth-example">
                    Authorization: Bearer YOUR_JWT_TOKEN
                </div>
            </div>
            
            <div class="info-card">
                <h3>🌐 Base URLs</h3>
                <p><strong>Development:</strong> http://localhost:8080</p>
                <p><strong>Production:</strong> https://api.example.com</p>
            </div>
            
            <div class="info-card">
                <h3>📚 Getting Started</h3>
                <ol>
                    <li>Register a new user account</li>
                    <li>Login to get your JWT token</li>
                    <li>Use the token to access protected endpoints</li>
                    <li>Start creating conversations and sending messages</li>
                </ol>
            </div>
        </div>
    </div>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: './openapi.yaml',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                defaultModelsExpandDepth: 2,
                defaultModelExpandDepth: 2,
                docExpansion: "list",
                operationsSorter: "alpha",
                tagsSorter: "alpha",
                filter: true,
                supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch'],
                requestInterceptor: function(request) {
                    // Add any custom headers here
                    return request;
                },
                onComplete: function() {
                    console.log("Swagger UI loaded successfully!");
                },
                tryItOutEnabled: true
            });

            // Add custom styling after UI loads
            setTimeout(function() {
                const style = document.createElement('style');
                style.innerHTML = `
                    .swagger-ui .topbar { display: none; }
                    .swagger-ui .info { margin-bottom: 2rem; }
                    .swagger-ui .scheme-container { margin: 2rem 0; }
                `;
                document.head.appendChild(style);
            }, 1000);
        };
    </script>
</body>
</html>
HTML;
    }

    /**
     * Generate Redoc HTML documentation
     */
    public function generateRedocDocs(): void
    {
        $htmlContent = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>AI Chat Application API - Redoc</title>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,700|Roboto:300,400,700" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .header h1 { margin: 0; font-size: 2.5rem; font-weight: 300; }
        .header p { margin: 0.5rem 0 0 0; font-size: 1.2rem; opacity: 0.9; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🤖 AI Chat Application API</h1>
        <p>Comprehensive API Documentation</p>
    </div>
    <redoc spec-url='./openapi.yaml' theme='{"colors": {"primary": {"main": "#667eea"}}}'></redoc>
    <script src="https://cdn.jsdelivr.net/npm/redoc/bundles/redoc.standalone.js"></script>
</body>
</html>
HTML;

        $htmlPath = $this->outputPath . '/api-docs-redoc.html';
        file_put_contents($htmlPath, $htmlContent);
        echo "Generated Redoc documentation: $htmlPath\n";
    }
}

// Run the documentation generator
try {
    $generator = new DocumentationGenerator();
    $generator->generate();
    $generator->generateRedocDocs();
} catch (Exception $e) {
    echo "Error generating documentation: " . $e->getMessage() . "\n";
    exit(1);
}