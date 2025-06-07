# CodeIgniter Chat

This is a basic shoutboard built using CodeIgniter. Originally only used XML for the backend, 
but was rewritten to illustrate different types of web services. The application has been migrated from 
CodeIgniter 3.1.0 to CodeIgniter 4.

## What is CodeIgniter?

CodeIgniter is a PHP full-stack web framework that is light, fast, flexible and secure.
More information can be found at the [official site](https://codeigniter.com).

## Server Requirements

PHP version 8.1 or higher is required, with the following extensions installed:

- [intl](http://php.net/manual/en/intl.requirements.php)
- [mbstring](http://php.net/manual/en/mbstring.installation.php)
- [json](http://php.net/manual/en/json.installation.php) (enabled by default - don't turn it off)
- [mysqlnd](http://php.net/manual/en/mysqlnd.install.php) for MySQL database
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
   - Edit `app/Config/Database.php` to include your MySQL connection details:
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
   - Run the `create.sql` script to create the necessary tables in your database

3. Environment Configuration:
   - Copy the `env` file to `.env` and configure it for your environment:
     ```bash
     cp env .env
     ```
   - Edit the `.env` file to set the appropriate values:
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
