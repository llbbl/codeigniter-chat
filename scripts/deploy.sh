#!/bin/bash

# CodeIgniter Chat Deployment Script
# This script helps deploy the application using Docker containers

set -e

# Configuration
ENVIRONMENT="${1:-development}"
COMPOSE_FILES="-f docker-compose.yml"

case $ENVIRONMENT in
    "development")
        COMPOSE_FILES="$COMPOSE_FILES -f docker-compose.override.yml"
        echo "🚀 Deploying in DEVELOPMENT mode..."
        ;;
    "production")
        COMPOSE_FILES="$COMPOSE_FILES -f docker-compose.prod.yml"
        echo "🚀 Deploying in PRODUCTION mode..."
        ;;
    *)
        echo "❌ Invalid environment. Use 'development' or 'production'"
        exit 1
        ;;
esac

# Build and deploy
echo "📦 Building containers..."
docker compose $COMPOSE_FILES build

echo "🔧 Starting services..."
docker compose $COMPOSE_FILES up -d

# Wait for services to be ready
echo "⏳ Waiting for services to be ready..."
sleep 10

# Health checks
echo "🩺 Running health checks..."
docker compose $COMPOSE_FILES ps

echo "✅ Deployment complete!"
echo "🌐 Application should be available at: http://localhost"
echo "📊 Database management: http://localhost:8080 (if development mode)"

# Show logs
echo "📜 Recent logs:"
docker compose $COMPOSE_FILES logs --tail=20