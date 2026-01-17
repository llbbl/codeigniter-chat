# =============================================================================
# CodeIgniter 4 Chat Application - Multi-Stage Docker Build
# =============================================================================
# This Dockerfile creates a production-ready image for the chat application.
# It uses multi-stage builds to minimize the final image size.
#
# Stages:
#   1. composer  - Install PHP dependencies
#   2. node      - Build frontend assets with Vite
#   3. production - Final runtime image
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1: Composer Dependencies
# -----------------------------------------------------------------------------
# This stage installs PHP dependencies using Composer.
# We use a separate stage so we don't include Composer in the final image.
FROM composer:2.8 AS composer

WORKDIR /app

# Copy only the files needed for composer install
# This allows Docker to cache this layer if dependencies haven't changed
COPY composer.json composer.lock* ./

# Install dependencies without dev packages for production
# --no-scripts: Skip post-install scripts (we'll run them later)
# --no-interaction: Don't ask any questions
# --prefer-dist: Download packages as zip files (faster)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# -----------------------------------------------------------------------------
# Stage 2: Node.js Build (Frontend Assets)
# -----------------------------------------------------------------------------
# This stage builds the frontend assets using Vite.
# We use Node.js Alpine for a smaller image size.
FROM node:22-alpine AS node

WORKDIR /app

# Copy package files first (for better caching)
COPY package.json package-lock.json* pnpm-lock.yaml* ./

# Install pnpm globally and install dependencies
# Using pnpm as specified in project preferences
RUN corepack enable && corepack prepare pnpm@latest --activate \
    && pnpm install --frozen-lockfile

# Copy source files needed for the build
COPY vite.config.js ./
COPY src/ ./src/

# Build production assets
RUN pnpm build

# -----------------------------------------------------------------------------
# Stage 3: Production Runtime
# -----------------------------------------------------------------------------
# This is the final stage that runs the application.
# Using PHP 8.4 with Apache for simplicity (good for beginners).
FROM php:8.4-apache AS production

# Set labels for the image
LABEL maintainer="CodeIgniter Chat App" \
      description="CodeIgniter 4 Chat Application with WebSocket support" \
      version="1.0"

# Install system dependencies and PHP extensions
# These are required for CodeIgniter 4 and the WebSocket server (Ratchet)
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Required for intl extension
    libicu-dev \
    # Required for zip extension (used by Composer)
    libzip-dev \
    unzip \
    # Required for MySQL/MariaDB connections
    default-mysql-client \
    # Useful for debugging and health checks
    curl \
    # Clean up apt cache to reduce image size
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by CodeIgniter 4 and Ratchet
RUN docker-php-ext-install \
    # Internationalization (required by CodeIgniter 4)
    intl \
    # MySQL improved (for database connections)
    mysqli \
    # PDO MySQL (alternative database driver)
    pdo_mysql \
    # Sockets (required for WebSocket/Ratchet)
    sockets \
    # Zip (used by Composer and file handling)
    zip \
    # OPcache (improves PHP performance significantly)
    opcache

# Enable Apache modules
# - rewrite: Required for CodeIgniter's URL routing
# - headers: Required for security headers and CORS
RUN a2enmod rewrite headers

# Configure Apache to allow .htaccess overrides
# This is necessary for CodeIgniter's routing to work
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set the document root to CodeIgniter's public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Update Apache configuration to use the new document root
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configure PHP for production
# These settings improve security and performance
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Create custom PHP configuration
RUN echo 'memory_limit = 256M' >> "$PHP_INI_DIR/conf.d/custom.ini" \
    && echo 'upload_max_filesize = 64M' >> "$PHP_INI_DIR/conf.d/custom.ini" \
    && echo 'post_max_size = 64M' >> "$PHP_INI_DIR/conf.d/custom.ini" \
    && echo 'max_execution_time = 300' >> "$PHP_INI_DIR/conf.d/custom.ini" \
    && echo 'expose_php = Off' >> "$PHP_INI_DIR/conf.d/custom.ini"

# Configure OPcache for better performance
RUN echo 'opcache.enable=1' >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo 'opcache.memory_consumption=128' >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo 'opcache.interned_strings_buffer=8' >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo 'opcache.max_accelerated_files=4000' >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo 'opcache.revalidate_freq=2' >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo 'opcache.fast_shutdown=1' >> "$PHP_INI_DIR/conf.d/opcache.ini"

# Set working directory
WORKDIR /var/www/html

# Copy application code
# Note: .dockerignore excludes unnecessary files
COPY . .

# Copy Composer dependencies from the composer stage
COPY --from=composer /app/vendor ./vendor

# Copy built frontend assets from the node stage
COPY --from=node /app/public/dist ./public/dist

# Create writable directories for CodeIgniter
# These directories need write permissions for logs, cache, sessions, etc.
RUN mkdir -p writable/cache writable/logs writable/session writable/uploads writable/debugbar \
    && chown -R www-data:www-data writable \
    && chmod -R 775 writable

# Create a non-root user for running the WebSocket server
# This is a security best practice
RUN useradd -m -s /bin/bash websocket

# Expose ports
# 80: HTTP (Apache)
# 8080: WebSocket server
EXPOSE 80 8080

# Health check to verify the application is running
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Default command: Start Apache in foreground
CMD ["apache2-foreground"]
