# Codeigniter Chat

This is a basic shoutboard built using codeigniter. Originally only used XML for the backend, 
but was rewritten to illustrate different types of web services. It has been updated to use 
CodeIgniter 3.0.3.

## Installation

1. git clone git@github.com:llbbl/codeigniter-chat.git
2. Rename application/config/database-default.php to database.php
3. Modify database.php to include your MySQL connection details
4. Run the create.sql (does not include schema create)

## Configuration

* Setup the Webserver DocumentRoot to point to public folder inside the codeigniter chat folder
* Assuming you have setup the project under the domain example.local, Open a browser and goto example.local/chat (See Below for Webserver configuration)

## Web Server Configuration

If you are using nginx, configure the example below and put in sites available/enabled folders. 

If you are using Apache there is already a .htaccess file in the public folder ready to start working. Make sure you have mod_rewrite enabled. 


nginx

```
server{
	server_name default.local;
	root /Web/default;
	
	listen 80;
	index index.php index.html index.htm;
	
    access_log /Web/logs/default.access.log;
    error_log /Web/logs/default.error.log;
	
	location / {
		try_files $uri $uri/ /index.php?$query_string;
	}

	location ~ \.php$ {
		try_files $uri =404;
			
		fastcgi_split_path_info ^(.+\.php)(/.+)$;
		fastcgi_pass unix:/var/run/php5-fpm.sock;
		fastcgi_read_timeout 150;
		fastcgi_index index.php;
		fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
		include fastcgi_params;
	}
	
}
```

