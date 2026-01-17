# CodeIgniter Chat Development Environment Setup Guide

This guide provides detailed instructions for setting up a development environment for the CodeIgniter Chat application.

## Quick Start Options

There are multiple ways to set up this project:

1. **Docker (Recommended for beginners)** - No need to install PHP, MySQL, or Node.js locally. See [DOCKER.md](../DOCKER.md) for complete instructions.

2. **SQLite (Simplest local setup)** - Use SQLite instead of MySQL for zero-config database setup. Just set `DB_DRIVER=SQLite3` in your `.env` file.

3. **Full Local Setup** - Install all dependencies locally (instructions below).

## Prerequisites

Before you begin, ensure you have the following installed on your system:

- **PHP 8.4 or higher**
  - Required extensions: `intl`, `mbstring`, `json`, `mysqlnd`, `xml`, `curl`
  - Verify your PHP version: `php -v`
  - Check installed extensions: `php -m`

- **MySQL 5.7 or higher** (or MariaDB 10.3 or higher) - *Optional if using SQLite*
  - Verify your MySQL version: `mysql --version`
  - **Alternative**: Use SQLite for simpler setup (no database server required)

- **Web Server**
  - Apache 2.4+ with `mod_rewrite` enabled
  - Or Nginx 1.16+

- **Composer** (Dependency Manager for PHP)
  - Verify your Composer installation: `composer --version`

- **Node.js and NPM** (for frontend development)
  - Recommended: Node.js 16+ and NPM 8+
  - Verify your Node.js version: `node -v`
  - Verify your NPM version: `npm -v`

- **Git** (for version control)
  - Verify your Git installation: `git --version`

## Installation Steps

### 1. Clone the Repository

```bash
git clone git@github.com:llbbl/codeigniter-chat.git
cd codeigniter-chat
```

### 2. Install PHP Dependencies

```bash
composer install
```

This will install all the required PHP packages, including CodeIgniter 4 framework and any other dependencies specified in `composer.json`.

### 3. Install Frontend Dependencies

```bash
npm install
```

This will install all the required frontend packages, including jQuery, Vue.js, and other dependencies specified in `package.json`.

### 4. Build Frontend Assets

```bash
npm run build
```

This will compile and bundle all frontend assets (JavaScript, CSS) using Vite.

For development with hot reloading:

```bash
npm run dev
```

### 5. Database Configuration

You have two options for the database: **SQLite** (simpler) or **MySQL** (production-ready).

#### Option A: SQLite (Recommended for Development)

SQLite requires no database server installation. Just configure and run migrations:

1. Edit your `.env` file and set:

```
DB_DRIVER=SQLite3
```

2. Run the database migration:

```bash
php spark migrate
```

The database file will be automatically created at `writable/database/chat.db`.

#### Option B: MySQL (Recommended for Production)

1. Create a new MySQL database for the application:

```sql
CREATE DATABASE codeigniter_chat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'chat_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON codeigniter_chat.* TO 'chat_user'@'localhost';
FLUSH PRIVILEGES;
```

2. Edit your `.env` file with your MySQL connection details:

```
DB_DRIVER=MySQLi
database.default.hostname=localhost
database.default.database=codeigniter_chat
database.default.username=chat_user
database.default.password=your_password
```

3. Run the database migration script to create the necessary tables:

```bash
php spark migrate
```

### 6. Environment Configuration

1. Copy the `env` file to `.env` and configure it for your environment:

```bash
cp env .env
```

2. Edit the `.env` file to set the appropriate values:

```
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'
```

3. Additional environment settings you may want to configure:

```
# Database
database.default.hostname = localhost
database.default.database = codeigniter_chat
database.default.username = chat_user
database.default.password = your_password

# Email
email.fromEmail = 'your-email@example.com'
email.fromName = 'CodeIgniter Chat'
```

### 7. Web Server Configuration

#### Option 1: Using PHP's Built-in Server (for development only)

```bash
php spark serve
```

This will start a development server at `http://localhost:8080/`.

#### Option 2: Apache Configuration

1. Ensure that `mod_rewrite` is enabled:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

2. Create a virtual host configuration in `/etc/apache2/sites-available/codeigniter-chat.conf`:

```apache
<VirtualHost *:80>
    ServerName codeigniter-chat.local
    DocumentRoot /path/to/codeigniter-chat/public
    
    <Directory /path/to/codeigniter-chat/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/codeigniter-chat-error.log
    CustomLog ${APACHE_LOG_DIR}/codeigniter-chat-access.log combined
</VirtualHost>
```

3. Enable the virtual host and restart Apache:

```bash
sudo a2ensite codeigniter-chat.conf
sudo systemctl restart apache2
```

4. Add the following entry to your `/etc/hosts` file:

```
127.0.0.1 codeigniter-chat.local
```

#### Option 3: Nginx Configuration

1. Create a server block configuration in `/etc/nginx/sites-available/codeigniter-chat`:

```nginx
server {
    server_name codeigniter-chat.local;
    root /path/to/codeigniter-chat/public;

    listen 80;
    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock; # Adjust this path as needed
        fastcgi_read_timeout 150;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

2. Enable the server block and restart Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/codeigniter-chat /etc/nginx/sites-enabled/
sudo systemctl restart nginx
```

3. Add the following entry to your `/etc/hosts` file:

```
127.0.0.1 codeigniter-chat.local
```

### 8. File Permissions

Ensure that the `writable` directory is writable by the web server:

```bash
chmod -R 775 writable/
```

If you're using a Unix-based system, you may need to change the owner of the directory:

```bash
sudo chown -R www-data:www-data writable/
```

### 9. Access the Application

Start your web server and navigate to:

- XML Chat: `http://codeigniter-chat.local/chat`
- JSON Chat: `http://codeigniter-chat.local/chat/json`
- HTML Chat: `http://codeigniter-chat.local/chat/html`
- Vue.js Chat: `http://codeigniter-chat.local/chat/vue`
- Svelte Chat: `http://codeigniter-chat.local/chat/svelte`

## Troubleshooting

### Common Issues and Solutions

1. **404 Not Found Error**
   - Ensure your web server is configured correctly
   - Check that mod_rewrite is enabled (Apache)
   - Verify that the .htaccess file is present in the public directory

2. **Database Connection Error**
   - Verify your database credentials in `app/Config/Database.php`
   - Ensure the MySQL server is running
   - Check that the database and user exist with proper permissions

3. **Permission Issues**
   - Ensure the `writable` directory and its subdirectories are writable by the web server
   - Check file ownership and permissions

4. **Composer Dependency Issues**
   - Try running `composer update` to update dependencies
   - Clear Composer cache: `composer clear-cache`

5. **Frontend Asset Issues**
   - Ensure Node.js and NPM are installed correctly
   - Try clearing the NPM cache: `npm cache clean --force`
   - Rebuild assets: `npm run build`

### Debugging Tips

1. Enable detailed error reporting in your `.env` file:
   ```
   CI_ENVIRONMENT = development
   ```

2. Check the error logs:
   - PHP error log (location depends on your PHP configuration)
   - CodeIgniter log files in `writable/logs/`
   - Web server error logs (Apache/Nginx)

3. Use the CodeIgniter Debug Toolbar:
   - Ensure it's enabled in `app/Config/Toolbar.php`
   - It appears at the bottom of the page in development mode

## Development Workflow

1. Start the development server:
   ```bash
   php spark serve
   ```

2. Start the Vite development server for frontend assets:
   ```bash
   npm run dev
   ```

3. Make your changes to the codebase

4. Run tests to ensure your changes don't break existing functionality:
   ```bash
   composer test
   ```

5. Build frontend assets for production:
   ```bash
   npm run build
   ```

## Alternative: Docker Setup

If you prefer not to install PHP, MySQL, and Node.js locally, you can use Docker instead. See [DOCKER.md](../DOCKER.md) for complete Docker setup instructions.

Docker provides:
- Consistent development environment across different machines
- No need to install PHP, MySQL, or Node.js locally
- Easy setup with a single `docker compose up` command

## Additional Resources

- [CodeIgniter 4 Documentation](https://codeigniter.com/user_guide/index.html)
- [Vue.js Documentation](https://vuejs.org/guide/introduction.html)
- [Svelte Documentation](https://svelte.dev/docs)
- [Vite Documentation](https://vitejs.dev/guide/)
- [Docker Documentation](https://docs.docker.com/)