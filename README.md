# Codeigniter Chat


This is a basic shoutboard built using codeigniter. It originally only used XML for the backend, 
but was rewritten to illustrate different types of web services. It has been updated to use 
CodeIgniter 2.1.4. A basic LAMP stack is required. It uses sessions.

## Installation

1. git clone git@github.com:llbbl/codeigniter-chat.git
2. Rename application/config/database-default.php to database.php
3. Modify database.php to include your MySQL connection details
4. Modify the encryption_key to something more secure than 'changeme' in application/config/config.php
5. Run the create.sql (includes schema create so modify if you already have a schema)

## Configuration

* Setup the Apache DocumentRoot to point to public folder inside the codeigniter chat application
* Assuming you have setup the project under the domain example.local, Open a browser and goto example.local/chat
