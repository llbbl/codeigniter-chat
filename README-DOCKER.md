# CodeIgniter Chat - Container Orchestration

This document provides comprehensive guidance for deploying the CodeIgniter Chat application using Docker containers and Kubernetes orchestration.

## 🐳 Docker Container Architecture

The application is containerized into multiple components:

### Core Services
- **Web Application** (`Dockerfile`): PHP 8.4 with Apache, serving the main application
- **WebSocket Server** (`Dockerfile.websocket`): PHP CLI running the real-time chat server
- **MySQL Database**: Persistent data storage for messages and sessions
- **Redis**: Session storage and caching layer
- **Nginx**: Load balancer and reverse proxy

### Support Services
- **Frontend Assets** (`Dockerfile.frontend`): Node.js build system for static assets
- **phpMyAdmin**: Database management (development only)
- **Redis Commander**: Redis management (development only)

## 🚀 Quick Start

### Prerequisites
- Docker and Docker Compose installed
- At least 4GB RAM available
- Ports 80, 8000, 8080, 8081 available

### Development Deployment
```bash
# Make deployment script executable
chmod +x scripts/deploy.sh

# Deploy in development mode
./scripts/deploy.sh development
```

### Production Deployment
```bash
# Deploy in production mode
./scripts/deploy.sh production
```

## 📋 Container Details

### Web Application Container
- **Base Image**: php:8.4-apache
- **Port**: 80
- **Features**:
  - PHP extensions: intl, mbstring, json, pdo_mysql, mysqli, zip
  - Apache with mod_rewrite enabled
  - Composer for dependency management
  - Security headers configured
  - Health checks implemented

### WebSocket Server Container
- **Base Image**: php:8.4-cli
- **Port**: 8080
- **Features**:
  - Ratchet WebSocket server
  - Socket extension enabled
  - Real-time message broadcasting
  - Connection management

### Database Container
- **Base Image**: mysql:8.0
- **Port**: 3306 (internal only in production)
- **Features**:
  - Persistent volume for data
  - Automatic schema initialization
  - Health checks with mysqladmin
  - Optimized configuration

### Redis Container
- **Base Image**: redis:7-alpine
- **Port**: 6379 (internal only in production)
- **Features**:
  - Persistent volume for data
  - Password protection in production
  - AOF persistence enabled

## 🔧 Configuration

### Environment Variables
Set these in your `.env` file or docker-compose environment:

```bash
# Database
MYSQL_ROOT_PASSWORD=your_secure_root_password
MYSQL_DATABASE=ci4_chat
MYSQL_USER=ci4user
MYSQL_PASSWORD=your_secure_password

# Redis
REDIS_PASSWORD=your_redis_password

# Application
ENCRYPTION_KEY=your_32_character_encryption_key
CI_ENVIRONMENT=production
```

### Volume Mounts
- `mysql_data`: Database persistence
- `redis_data`: Cache persistence
- `web_logs`: Apache logs
- `nginx_logs`: Nginx logs
- `./writable`: Application writable directory

## 🌐 Service Endpoints

### Development Mode
- **Main Application**: http://localhost
- **Nginx Proxy**: http://localhost:8000
- **Frontend Dev Server**: http://localhost:5173
- **phpMyAdmin**: http://localhost:8080
- **Redis Commander**: http://localhost:8081
- **WebSocket**: ws://localhost:8080

### Production Mode
- **Application**: http://localhost (via Nginx)
- **WebSocket**: ws://localhost/websocket (via Nginx proxy)

## 🔍 Monitoring and Health Checks

### Health Check Endpoints
- **Web**: `GET /` - Returns 200 if application is running
- **Nginx**: `GET /health` - Returns "healthy" status
- **MySQL**: `mysqladmin ping` - Database connectivity
- **Redis**: `redis-cli ping` - Cache connectivity

### Logging
All services log to dedicated volumes:
- Web logs: `/var/log/apache2/`
- Nginx logs: `/var/log/nginx/`
- Application logs: `./writable/logs/`

### Viewing Logs
```bash
# View all service logs
docker compose logs -f

# View specific service logs
docker compose logs -f web
docker compose logs -f websocket
docker compose logs -f mysql
```

## 📊 Scaling and Performance

### Horizontal Scaling
```bash
# Scale web application
docker compose up -d --scale web=3

# Scale WebSocket servers
docker compose up -d --scale websocket=2
```

### Resource Limits
Production containers have resource limits:
- **Web**: 1 CPU, 512MB RAM
- **WebSocket**: 0.5 CPU, 256MB RAM
- **Nginx**: 0.5 CPU, 256MB RAM

## 🛡️ Security Features

### Network Security
- Isolated Docker network
- Internal service communication
- External ports only on load balancer

### Application Security
- Security headers (X-Frame-Options, CSP, etc.)
- Rate limiting on API endpoints
- Input validation and sanitization
- Secure session management with Redis

### Container Security
- Non-root user execution where possible
- Read-only root filesystem for stateless services
- Minimal base images (Alpine Linux)
- Regular security updates

## 🚨 Troubleshooting

### Common Issues

#### Database Connection Errors
```bash
# Check MySQL container
docker compose logs mysql

# Verify database is accessible
docker compose exec web php spark migrate:status
```

#### WebSocket Connection Issues
```bash
# Check WebSocket server logs
docker compose logs websocket

# Test WebSocket connectivity
docker compose exec web php spark chat:websocket --port=8080
```

#### Permission Issues
```bash
# Fix writable directory permissions
docker compose exec web chown -R www-data:www-data /var/www/html/writable
docker compose exec web chmod -R 777 /var/www/html/writable
```

### Performance Issues
```bash
# Monitor resource usage
docker stats

# Check container health
docker compose ps
```

## 🔄 Updates and Maintenance

### Updating Application
```bash
# Pull latest code
git pull origin main

# Rebuild containers
docker compose build --no-cache

# Deploy with zero downtime
docker compose up -d --force-recreate
```

### Database Maintenance
```bash
# Backup database
docker compose exec mysql mysqldump -u root -p ci4_chat > backup.sql

# Restore database
docker compose exec -i mysql mysql -u root -p ci4_chat < backup.sql
```

### Container Cleanup
```bash
# Remove unused containers and images
docker system prune -a

# Remove application volumes (⚠️ DATA LOSS)
docker compose down -v
```

## 📈 Production Considerations

### Load Balancing
- Nginx configured for round-robin load balancing
- WebSocket sticky sessions handled
- Health checks ensure traffic to healthy containers

### Backup Strategy
- Database: Automated daily backups
- Redis: AOF persistence enabled
- Application: Git-based deployments

### Monitoring Integration
- ELK stack for log aggregation
- Prometheus for metrics collection
- Grafana for visualization
- Health check endpoints for uptime monitoring

## 🎯 Next Steps

1. **Kubernetes Deployment**: See `README-KUBERNETES.md` for advanced orchestration
2. **CI/CD Integration**: Set up automated deployments with GitHub Actions
3. **SSL/TLS**: Configure HTTPS with Let's Encrypt certificates
4. **Monitoring**: Implement comprehensive monitoring with Prometheus/Grafana
5. **Backup Automation**: Set up automated backup and disaster recovery procedures