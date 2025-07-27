#!/bin/bash

# CodeIgniter Chat Kubernetes Deployment Script
# This script helps deploy the application to Kubernetes

set -e

# Configuration
ENVIRONMENT="${1:-development}"
NAMESPACE="codeigniter-chat"

echo "🚀 Deploying CodeIgniter Chat to Kubernetes ($ENVIRONMENT)..."

# Check if kubectl is available
if ! command -v kubectl &> /dev/null; then
    echo "❌ kubectl is not installed. Please install kubectl first."
    exit 1
fi

# Check if kustomize is available
if ! command -v kustomize &> /dev/null; then
    echo "❌ kustomize is not installed. Please install kustomize first."
    exit 1
fi

# Build Docker images first
echo "📦 Building Docker images..."
docker build -t codeigniter-chat-web:latest -f Dockerfile .
docker build -t codeigniter-chat-websocket:latest -f Dockerfile.websocket .

# Apply namespace first
echo "📁 Creating namespace..."
kubectl apply -f k8s/base/namespace.yaml

# Deploy using kustomize
echo "🔧 Deploying to Kubernetes..."
case $ENVIRONMENT in
    "development")
        kustomize build k8s/overlays/development | kubectl apply -f -
        ;;
    "production")
        kustomize build k8s/overlays/production | kubectl apply -f -
        ;;
    *)
        echo "❌ Invalid environment. Use 'development' or 'production'"
        exit 1
        ;;
esac

# Wait for deployments
echo "⏳ Waiting for deployments to be ready..."
kubectl wait --for=condition=available deployment --all -n $NAMESPACE --timeout=300s

# Show status
echo "📋 Deployment status:"
kubectl get all -n $NAMESPACE

echo "✅ Kubernetes deployment complete!"
echo "🌐 Application should be available through the configured ingress or service ports"