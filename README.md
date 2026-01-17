# CodeIgniter Chat

[![CI](https://github.com/llbbl/codeigniter-chat/actions/workflows/ci.yml/badge.svg)](https://github.com/llbbl/codeigniter-chat/actions/workflows/ci.yml)

This is a basic shoutboard built using CodeIgniter. Originally only used XML for the backend, 
but was rewritten to illustrate different types of web services. The application has been migrated from 
CodeIgniter 3.1.0 to CodeIgniter 4.

## What is CodeIgniter?

CodeIgniter is a PHP full-stack web framework that is light, fast, flexible and secure.
More information can be found at the [official site](https://codeigniter.com).

## Server Requirements

PHP version 8.4 or higher is required, with the following extensions installed:

- [intl](http://php.net/manual/en/intl.requirements.php)
- [mbstring](http://php.net/manual/en/mbstring.installation.php)
- [json](http://php.net/manual/en/json.installation.php) (enabled by default - don't turn it off)
- [sqlite3](http://php.net/manual/en/sqlite3.installation.php) for SQLite database (recommended for beginners)
- [mysqlnd](http://php.net/manual/en/mysqlnd.install.php) for MySQL database (production)
- [libcurl](http://php.net/manual/en/curl.requirements.php) if you plan to use the HTTP\CURLRequest library

## Installation

1. Clone the repository:
   ```bash
   git clone git@github.com:llbbl/codeigniter-chat.git
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install Node.js dependencies (for front-end build system):
   ```bash
   npm install
   ```

4. Build front-end assets:
   ```bash
   npm run build
   ```

5. Database Configuration:

   This application supports both **SQLite** and **MySQL** databases.

   ### Option A: SQLite (Recommended for Beginners)

   SQLite requires no database server setup - perfect for learning and local development.

   1. Copy the environment file and enable SQLite:
      ```bash
      cp .env.example .env
      ```

   2. Edit `.env` and set:
      ```
      DB_DRIVER=SQLite3
      ```

   3. Run migrations to create the database:
      ```bash
      php spark migrate
      ```

   The SQLite database file will be automatically created at `writable/database/chat.db`.

   ### Option B: MySQL (Recommended for Production)

   1. Create a MySQL database and user for the application.

   2. Copy the environment file:
      ```bash
      cp .env.example .env
      ```

   3. Edit `.env` with your MySQL credentials:
      ```
      DB_DRIVER=MySQLi
      DB_DATABASE=ci4_chat
      DB_USERNAME=your_mysql_username
      DB_PASSWORD=your_mysql_password
      ```

   4. Alternatively, edit `app/Config/Database.php` directly:
      ```php
      public array $default = [
          'hostname' => 'localhost',
          'username' => 'your_mysql_username',
          'password' => 'your_mysql_password',
          'database' => 'your_database_name',
          'DBDriver' => 'MySQLi',
          // Other settings can remain as default
      ];
      ```

   5. Run database migrations:
      ```bash
      php spark migrate
      ```

6. Additional Environment Configuration (optional):
   - Edit the `.env` file to set additional values as needed:
     ```
     CI_ENVIRONMENT = development
     app.baseURL = 'http://your-domain.com/'
     ```

## Web Server Configuration

Configure your web server to point to the `public` folder as the document root.

### Apache Configuration

If you are using Apache, there is already a `.htaccess` file in the public folder. Make sure you have mod_rewrite enabled.

### Nginx Configuration

If you are using Nginx, use a configuration similar to:

```
server {
    server_name your-domain.com;
    root /path/to/codeigniter-chat/public;

    listen 80;
    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php-fpm.sock; # Adjust this path as needed
        fastcgi_read_timeout 150;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Running the Application

Start your web server and navigate to:

- XML Chat: `http://your-domain.com/chat`
- JSON Chat: `http://your-domain.com/chat/json`
- HTML Chat: `http://your-domain.com/chat/html`

## Public API documentation

See:

- `docs/public-api.md` (HTTP + WebSocket + frontend entrypoints + reusable PHP APIs)

## Troubleshooting

If you encounter any issues:

1. Check the error logs in `writable/logs/`
2. Ensure your database configuration is correct
3. Make sure your web server is configured correctly
4. Verify that the database tables have been created properly

## Front-end Build System

This project uses [Vite](https://vitejs.dev/) as a front-end build system to process CSS and JavaScript files.

### Development Mode

To start the development server:

```bash
npm run dev
```

This will start a development server at http://localhost:5173/ that will automatically reload when you make changes to the source files.

### Building for Production

To build the assets for production:

```bash
npm run build
```

This will generate optimized files in the `public/dist/` directory.

### More Information

For more detailed information about the front-end build system, please refer to the [src/README.md](src/README.md) file.

## Migration from CodeIgniter 3 to CodeIgniter 4

For detailed information about the migration from CodeIgniter 3 to CodeIgniter 4, please refer to the [README_MIGRATION.md](README_MIGRATION.md) file.
