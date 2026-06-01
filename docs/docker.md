# Docker Setup Guide

This guide explains how to run the CodeIgniter 4 Chat Application using Docker. Docker allows you to run the entire application stack (web server, database, and WebSocket server) in isolated containers without installing PHP, MySQL, or Node.js on your machine.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Understanding the Setup](#understanding-the-setup)
- [Configuration](#configuration)
- [Common Commands](#common-commands)
- [Development Workflow](#development-workflow)
- [Troubleshooting](#troubleshooting)
- [Architecture Overview](#architecture-overview)

## Prerequisites

Before you begin, make sure you have the following installed:

1. **Docker Desktop** (includes Docker Engine and Docker Compose)
   - [Download for Mac](https://docs.docker.com/desktop/install/mac-install/)
   - [Download for Windows](https://docs.docker.com/desktop/install/windows-install/)
   - [Download for Linux](https://docs.docker.com/desktop/install/linux-install/)

2. **Git** (to clone the repository)
   - [Download Git](https://git-scm.com/downloads)

To verify Docker is installed correctly, run:

```bash
docker --version
docker compose version
```

## Quick Start

Follow these steps to get the application running:

### 1. Clone the Repository

```bash
git clone <repository-url>
cd codeigniter-chat
```

### 2. Create Environment File

Copy the example environment file and customize it:

```bash
cp .env.example .env
```

The default values in `.env.example` work out of the box, but you can customize them if needed.

### 3. Build and Start the Containers

```bash
docker compose up -d --build
```

This command:
- Builds the Docker images (first time only, or when Dockerfile changes)
- Starts all three services (web, database, websocket)
- Runs in detached mode (`-d`) so you get your terminal back

### 4. Run Database Migrations

After the containers are running, set up the database:

```bash
docker compose exec web php spark migrate
```

### 5. Access the Application

Open your browser and navigate to:
- **Web Application**: http://localhost:8000
- **WebSocket Server**: ws://localhost:8080 (used by the chat feature)

## Understanding the Setup

### What is Docker?

Docker packages your application and its dependencies into "containers" - lightweight, portable environments that run consistently anywhere. Think of containers as lightweight virtual machines that share the host's operating system.

### Services Defined

The `docker-compose.yml` file defines three services:

| Service | Description | Port |
|---------|-------------|------|
| `web` | PHP 8.4 + Apache web server running CodeIgniter 4 | 8000 |
| `db` | MySQL 8.0 database server | 3307 |
| `websocket` | PHP process running the Ratchet WebSocket server | 8080 |

### Directory Structure

```
codeigniter-chat/
├── Dockerfile           # Instructions to build the PHP/Apache image
├── docker-compose.yml   # Defines all services and their configuration
├── .dockerignore        # Files to exclude from Docker builds
├── .env.example         # Example environment configuration
├── .env                 # Your local environment configuration (not in git)
└── writable/            # Logs, cache, sessions (mounted as volume)
```

## Configuration

### Environment Variables

Edit the `.env` file to configure the application:

| Variable | Default | Description |
|----------|---------|-------------|
| `CI_ENVIRONMENT` | development | CodeIgniter environment mode |
| `APP_URL` | http://localhost:8000 | Application URL |
| `APP_PORT` | 8000 | Port for the web application |
| `DB_DATABASE` | ci4_chat | Database name |
| `DB_USERNAME` | ci4_user | Database username |
| `DB_PASSWORD` | ci4_password | Database password |
| `DB_ROOT_PASSWORD` | rootpassword | MySQL root password |
| `DB_PORT` | 3307 | Port to access MySQL from host |
| `WEBSOCKET_PORT` | 8080 | WebSocket server port |

### Changing Ports

If port 8000 is already in use on your machine, change `APP_PORT` in `.env`:

```env
APP_PORT=8001
```

Then restart the containers:

```bash
docker compose down
docker compose up -d
```

## Common Commands

### Starting and Stopping

```bash
# Start all services
docker compose up -d

# Stop all services (keeps data)
docker compose down

# Stop all services and remove volumes (deletes database!)
docker compose down -v

# Restart all services
docker compose restart

# Restart a specific service
docker compose restart web
```

### Viewing Logs

```bash
# View logs from all services
docker compose logs

# Follow logs in real-time (Ctrl+C to exit)
docker compose logs -f

# View logs from a specific service
docker compose logs web
docker compose logs websocket
docker compose logs db
```

### Running Commands Inside Containers

```bash
# Run CodeIgniter spark commands
docker compose exec web php spark migrate
docker compose exec web php spark db:seed

# Open a bash shell in the web container
docker compose exec web bash

# Run Composer commands
docker compose exec web composer install
docker compose exec web composer update

# Run MySQL commands
docker compose exec db mysql -u ci4_user -pci4_password ci4_chat
```

### Rebuilding Images

If you change the Dockerfile or add new dependencies:

```bash
# Rebuild and restart
docker compose up -d --build

# Force rebuild without cache
docker compose build --no-cache
docker compose up -d
```

## Development Workflow

### Making Code Changes

For development, you have two options:

#### Option 1: Rebuild on Changes (Simple)

Make your changes, then rebuild:

```bash
docker compose up -d --build
```

This is slower but ensures consistency.

#### Option 2: Mount Source Directory (Faster)

Edit `docker-compose.yml` and uncomment the volume mount:

```yaml
volumes:
  - ./writable:/var/www/html/writable
  # Uncomment for live code changes:
  - .:/var/www/html
```

This mounts your local code into the container, so changes take effect immediately. Restart the containers after this change:

```bash
docker compose down
docker compose up -d
```

### Frontend Development

For developing frontend assets with hot reload:

1. Build assets inside the container:
   ```bash
   docker compose exec web pnpm build
   ```

2. Or, uncomment the `node` service in `docker-compose.yml` for Vite's development server with hot module replacement.

### Database Management

Connect to the database using a GUI tool (like TablePlus, DBeaver, or MySQL Workbench):

- **Host**: localhost
- **Port**: 3307 (or your configured `DB_PORT`)
- **User**: ci4_user (or your configured `DB_USERNAME`)
- **Password**: ci4_password (or your configured `DB_PASSWORD`)
- **Database**: ci4_chat (or your configured `DB_DATABASE`)

### Running Tests

```bash
# Run all tests
docker compose exec web composer test

# Run specific test suites
docker compose exec web composer test:unit
docker compose exec web composer test:feature
docker compose exec web composer test:database
```

## Troubleshooting

### "Port already in use" Error

If you see an error about ports being in use:

```bash
# Find what's using port 8000
lsof -i :8000

# Change the port in .env
APP_PORT=8001

# Restart
docker compose down
docker compose up -d
```

### "Connection refused" to Database

The database might still be initializing. Wait 30 seconds and try again, or check the logs:

```bash
docker compose logs db
```

### WebSocket Not Connecting

1. Check if the WebSocket container is running:
   ```bash
   docker compose ps
   ```

2. Check the WebSocket logs:
   ```bash
   docker compose logs websocket
   ```

3. Verify the `WEBSOCKET_URL` in your `.env` matches what the browser can access.

### Permission Issues with `writable/` Directory

If you see permission errors:

```bash
# Fix permissions on the host
sudo chown -R $(whoami):$(whoami) writable/

# Or fix inside the container
docker compose exec web chown -R www-data:www-data writable/
```

### Container Keeps Restarting

Check the logs to see why:

```bash
docker compose logs web --tail=50
```

### Clearing Everything and Starting Fresh

```bash
# Stop everything and remove volumes
docker compose down -v

# Remove the built images
docker compose down --rmi all

# Remove all Docker artifacts (use with caution!)
docker system prune -a
```

## Architecture Overview

```
                                     +------------------+
                                     |                  |
    Browser ----HTTP:8000----------->|   Web (Apache)   |
       |                             |    PHP 8.4       |
       |                             |   CodeIgniter 4  |
       |                             +--------+---------+
       |                                      |
       |                                      | Port 3306
       |                                      v
       |                             +------------------+
       |                             |                  |
       |                             |   MySQL 8.0     |
       |                             |   (Database)     |
       |                             +--------+---------+
       |                                      ^
       |                                      |
       |                                      | Port 3306
       |                             +--------+---------+
       |                             |                  |
       +-----WS:8080---------------->|   WebSocket     |
                                     |   Server (PHP)   |
                                     |   Ratchet        |
                                     +------------------+
```

### How It Works

1. **Browser** makes HTTP requests to the **Web** container on port 8000
2. **Web** container runs Apache + PHP, serving the CodeIgniter application
3. **Web** container connects to **MySQL** on the internal Docker network
4. **Browser** connects directly to the **WebSocket** container for real-time chat
5. **WebSocket** container also connects to **MySQL** to store/retrieve messages

### Volumes

- `mysql-data`: Persists the MySQL database between restarts
- `./writable`: Mounted from host for logs, cache, and sessions

### Network

All containers communicate on a private Docker network (`ci4-chat-network`). Only the specified ports are exposed to your machine.

---

## Need Help?

If you encounter issues not covered here:

1. Check the [CodeIgniter 4 Documentation](https://codeigniter.com/user_guide/)
2. Check the [Docker Documentation](https://docs.docker.com/)
3. Review the container logs: `docker compose logs`
4. Open an issue in the repository
